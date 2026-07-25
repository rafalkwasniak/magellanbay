<?php

namespace Tests\Feature\Seller;

use App\Livewire\Seller\DiscountCodeForm;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * „Wystaw kod dla klienta" ze szczegółów zamówienia — rekompensata po wpadce
 * albo podziękowanie za zakupy. To ten sam formularz co w dziale Kody rabatowe,
 * tylko z wypełnionym klientem i trybem jednorazowym; nowej ścieżki zapisu nie ma.
 */
class DiscountCodeFromOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(bool $allowed = true): array
    {
        $seller = User::factory()->consented()->create();
        $factory = $allowed ? Shop::factory()->withDiscountCodes() : Shop::factory();

        return [$seller, $factory->create(['owner_id' => $seller->id])];
    }

    public function test_order_of_a_registered_customer_offers_a_personal_code(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $customer = Customer::factory()->create(['shop_id' => $shop->id, 'email_verified_at' => now()]);
        $order = Order::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id]);

        $this->actingAs($seller)->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('Kod dla klienta')
            ->assertSee(route('seller.discounts.create', ['klient' => $customer->id]), escape: false);
    }

    public function test_guest_order_offers_a_one_off_code_instead(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->create(['shop_id' => $shop->id, 'customer_id' => null]);

        $this->actingAs($seller)->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee(route('seller.discounts.create', ['jednorazowy' => 1]), escape: false)
            ->assertSee('nie da się przypisać imiennie', escape: false);
    }

    public function test_shop_without_the_entitlement_gets_no_offer(): void
    {
        [$seller, $shop] = $this->sellerWithShop(allowed: false);
        $order = Order::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Kod dla klienta');
    }

    public function test_form_opens_prefilled_for_that_customer(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $customer = Customer::factory()->create(['shop_id' => $shop->id, 'email_verified_at' => now()]);

        $response = $this->actingAs($seller)
            ->get(route('seller.discounts.create', ['klient' => $customer->id]))
            ->assertOk();

        $this->assertSame(
            ['customer_id' => $customer->id, 'uses_mode' => 'jednorazowy'],
            $response->viewData('prefill'),
        );
    }

    public function test_foreign_customer_in_the_link_is_ignored(): void
    {
        [$seller] = $this->sellerWithShop();
        $foreign = Customer::factory()->create();

        $response = $this->actingAs($seller)
            ->get(route('seller.discounts.create', ['klient' => $foreign->id]))
            ->assertOk();

        $this->assertSame([], $response->viewData('prefill'));
    }

    public function test_prefilled_form_saves_a_personal_one_off_code(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $customer = Customer::factory()->create(['shop_id' => $shop->id, 'email_verified_at' => now()]);

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, [
            'shop' => $shop,
            'prefill' => ['customer_id' => $customer->id, 'uses_mode' => 'jednorazowy'],
        ])
            ->assertSet('customer_id', $customer->id)
            ->assertSet('uses_mode', 'jednorazowy')
            ->set('value', '20')
            ->call('save')
            ->assertHasNoErrors();

        $code = $shop->discountCodes()->sole();
        $this->assertSame($customer->id, $code->customer_id);
        $this->assertSame(1, $code->max_uses);
        $this->assertTrue($code->isPersonal());
    }
}
