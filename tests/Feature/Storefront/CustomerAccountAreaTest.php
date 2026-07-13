<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Panel „Moje konto" (Krok 4): dostęp tylko dla zalogowanego klienta, historia i
 * szczegół zamówień scope'owane do konta, edycja danych, zmiana hasła oraz
 * usunięcie konta (zamówienia odpinane do gościa, nie kasowane).
 */
class CustomerAccountAreaTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $shop = Shop::factory()->create();

        $this->get($this->host($shop).'/moje-konto')->assertRedirect('/logowanie');
    }

    public function test_customer_sees_only_their_orders(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $other = Customer::factory()->for($shop)->create();

        $mine = Order::factory()->for($shop)->create(['customer_id' => $customer->id, 'number' => 101]);
        $theirs = Order::factory()->for($shop)->create(['customer_id' => $other->id, 'number' => 202]);
        $guest = Order::factory()->for($shop)->create(['customer_id' => null, 'number' => 303]);

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto')
            ->assertOk()
            ->assertSee('#101')
            ->assertDontSee('#202')
            ->assertDontSee('#303');
    }

    public function test_order_detail_is_scoped_to_customer(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $other = Customer::factory()->for($shop)->create();

        $mine = Order::factory()->for($shop)->create(['customer_id' => $customer->id]);
        $theirs = Order::factory()->for($shop)->create(['customer_id' => $other->id]);

        $this->actingAs($customer, 'customer');

        $this->get($this->host($shop).'/moje-konto/zamowienia/'.$mine->id)->assertOk()->assertSee('#'.$mine->number);
        $this->get($this->host($shop).'/moje-konto/zamowienia/'.$theirs->id)->assertNotFound();
    }

    public function test_profile_can_be_updated(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        $this->actingAs($customer, 'customer')
            ->post($this->host($shop).'/moje-konto/dane', [
                'name' => 'Nowa', 'surname' => 'Nazwa', 'phone' => '+48 500 600 700',
            ])
            ->assertRedirect('/moje-konto/dane');

        $customer->refresh();
        $this->assertSame('Nowa', $customer->name);
        $this->assertSame('Nazwa', $customer->surname);
        $this->assertSame('48500600700', $customer->phone);
    }

    public function test_password_change_requires_correct_current(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['password' => Hash::make('password')]);

        $this->actingAs($customer, 'customer');

        // Złe obecne hasło → błąd, hasło niezmienione.
        $this->post($this->host($shop).'/moje-konto/haslo', [
            'current_password' => 'zle-haslo',
            'password' => 'nowe-haslo-123',
            'password_confirmation' => 'nowe-haslo-123',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $customer->fresh()->password));

        // Poprawne obecne hasło → zmiana.
        $this->post($this->host($shop).'/moje-konto/haslo', [
            'current_password' => 'password',
            'password' => 'nowe-haslo-123',
            'password_confirmation' => 'nowe-haslo-123',
        ])->assertRedirect('/moje-konto/dane');
        $this->assertTrue(Hash::check('nowe-haslo-123', $customer->fresh()->password));
    }

    public function test_account_deletion_detaches_orders_and_logs_out(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->create(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer')
            ->post($this->host($shop).'/moje-konto/usun')
            ->assertRedirect('/');

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertNull($order->fresh()->customer_id);
        $this->assertGuest('customer');
    }
}
