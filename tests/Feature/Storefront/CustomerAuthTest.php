<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Konta klientów storefrontu (Krok 2): rejestracja bez hasła + mail aktywacyjny,
 * aktywacja z podpisanego linku (ustawienie hasła, przypięcie wcześniejszych
 * zamówień gościa, auto-login), logowanie/wylogowanie scope'owane do sklepu oraz
 * pełna izolacja kont między sklepami.
 */
class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    private function activationUrl(Shop $shop, Customer $customer): string
    {
        return URL::temporarySignedRoute('storefront.activation', now()->addDay(), [
            'shop' => $shop->slug,
            'customer' => $customer->id,
        ]);
    }

    public function test_registration_creates_unactivated_customer_and_queues_branded_email(): void
    {
        $shop = Shop::factory()->create();

        $this->post($this->host($shop).'/rejestracja', ['email' => 'nowy@example.com'])
            ->assertRedirect('/rejestracja/potwierdzenie');

        $customer = Customer::where('shop_id', $shop->id)->where('email', 'nowy@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertFalse($customer->isActivated());
        $this->assertNull($customer->password);

        $this->assertDatabaseHas('email_messages', [
            'shop_id' => $shop->id,
            'to_email' => 'nowy@example.com',
            'from_name' => $shop->name,
        ]);
    }

    public function test_registration_rejects_duplicate_email_in_same_shop(): void
    {
        $shop = Shop::factory()->create();
        Customer::factory()->for($shop)->create(['email' => 'zajety@example.com']);

        $this->post($this->host($shop).'/rejestracja', ['email' => 'zajety@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_same_email_can_register_in_two_shops(): void
    {
        $a = Shop::factory()->create();
        $b = Shop::factory()->create();
        Customer::factory()->for($a)->create(['email' => 'ktos@example.com']);

        $this->post($this->host($b).'/rejestracja', ['email' => 'ktos@example.com'])
            ->assertRedirect('/rejestracja/potwierdzenie');

        $this->assertDatabaseHas('customers', ['shop_id' => $b->id, 'email' => 'ktos@example.com']);
    }

    public function test_activation_sets_password_logs_in_and_claims_guest_orders(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->unactivated()->create(['email' => 'klient@example.com']);

        $mine1 = Order::factory()->for($shop)->create(['buyer_email' => 'klient@example.com', 'customer_id' => null]);
        $mine2 = Order::factory()->for($shop)->create(['buyer_email' => 'KLIENT@example.com', 'customer_id' => null]);
        $otherEmail = Order::factory()->for($shop)->create(['buyer_email' => 'inny@example.com', 'customer_id' => null]);
        $otherShop = Order::factory()->create(['buyer_email' => 'klient@example.com', 'customer_id' => null]);

        $url = $this->activationUrl($shop, $customer);

        $this->get($url)->assertOk()->assertSee('Ustaw hasło');

        $this->post($url, [
            'password' => 'sekret-haslo-123',
            'password_confirmation' => 'sekret-haslo-123',
            'name' => 'Ala',
        ])->assertRedirect('/');

        $customer->refresh();
        $this->assertTrue($customer->isActivated());
        $this->assertTrue(Hash::check('sekret-haslo-123', $customer->password));
        $this->assertSame('Ala', $customer->name);

        // Przypięte: oba na ten e-mail (bez względu na wielkość liter), w tym sklepie.
        $this->assertSame($customer->id, $mine1->fresh()->customer_id);
        $this->assertSame($customer->id, $mine2->fresh()->customer_id);
        // Nietknięte: inny e-mail oraz to samo, ale w innym sklepie.
        $this->assertNull($otherEmail->fresh()->customer_id);
        $this->assertNull($otherShop->fresh()->customer_id);

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_activation_link_requires_valid_signature(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->unactivated()->create();

        $this->get($this->host($shop).'/aktywacja/'.$customer->id)->assertForbidden();
    }

    public function test_activation_rejects_customer_from_other_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $customer = Customer::factory()->for($shopA)->unactivated()->create();

        // Poprawnie podpisany link, ale na subdomenę innego sklepu — obrona w głąb.
        $url = $this->activationUrl($shopB, $customer);
        $this->get($url)->assertNotFound();
    }

    public function test_already_activated_link_redirects_to_login(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        $this->get($this->activationUrl($shop, $customer))->assertRedirect('/logowanie');
    }

    public function test_login_and_logout(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create([
            'email' => 'log@example.com',
            'password' => Hash::make('haslo123!'),
        ]);

        $this->post($this->host($shop).'/logowanie', [
            'email' => 'log@example.com',
            'password' => 'haslo123!',
        ])->assertRedirect('/moje-konto');
        $this->assertAuthenticatedAs($customer, 'customer');

        $this->post($this->host($shop).'/wyloguj')->assertRedirect('/');
        $this->assertGuest('customer');
    }

    public function test_login_is_scoped_to_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        Customer::factory()->for($shopA)->create([
            'email' => 'x@example.com',
            'password' => Hash::make('haslo123!'),
        ]);

        $this->post($this->host($shopB).'/logowanie', [
            'email' => 'x@example.com',
            'password' => 'haslo123!',
        ])->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }

    public function test_unactivated_account_cannot_login(): void
    {
        $shop = Shop::factory()->create();
        Customer::factory()->for($shop)->unactivated()->create(['email' => 'pending@example.com']);

        $this->post($this->host($shop).'/logowanie', [
            'email' => 'pending@example.com',
            'password' => 'cokolwiek',
        ])->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }
}
