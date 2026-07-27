<?php

namespace Tests\Feature\Storefront;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rabat widoczny wszędzie tam, gdzie klient ogląda rachunek: potwierdzenie
 * zakupu, konto klienta i strona płatności. Bez tego matematyka na ekranie się
 * nie spina („produkty + dostawa ≠ razem") i wygląda na błąd sklepu.
 */
class OrderDiscountVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function discountedOrder(Shop $shop, ?Customer $customer = null): Order
    {
        $order = Order::factory()->for($shop)->create([
            'customer_id' => $customer?->id,
            'delivery_method' => DeliveryMethod::Courier,
            'delivery_cost' => 20,
            'payment_method' => PaymentMethod::BankTransfer,
            'status' => OrderStatus::New,
            'items_total' => 200,
            'discount_code' => 'LATO10',
            'discount_amount' => 20,
            'total_net' => 146.34,
            'total_vat' => 33.66,
            'total_gross' => 200,
        ]);

        $order->items()->create([
            'name' => 'Doniczka', 'unit_price_gross' => 200, 'vat_rate' => '23',
            'quantity' => 1, 'line_total_gross' => 200,
        ]);

        return $order;
    }

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    public function test_confirmation_page_shows_the_discount(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $order = $this->discountedOrder($shop);

        $response = $this->withSession(['recent_order_id' => $order->id])
            ->get($this->host($shop).'/kasa/dziekujemy')
            ->assertOk();

        $response->assertSee('Rabat', escape: false);
        $response->assertSee('LATO10');
        $response->assertSee('−20,00 zł', escape: false);
        // Wartość produktów przed rabatem — inaczej suma wygląda na pomyłkę.
        $response->assertSee('200,00 zł');
    }

    public function test_customer_account_shows_the_discount(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $customer = Customer::factory()->create(['shop_id' => $shop->id, 'email_verified_at' => now()]);
        $order = $this->discountedOrder($shop, $customer);

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto/zamowienia/'.$order->id)
            ->assertOk()
            ->assertSee('LATO10')
            ->assertSee('−20,00 zł', escape: false);
    }

    public function test_payment_page_shows_the_discount(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $order = $this->discountedOrder($shop);
        $order->update(['payment_method' => PaymentMethod::Online, 'status' => OrderStatus::AwaitingPayment]);

        $this->get($this->host($shop).'/platnosc/'.$order->paymentToken())
            ->assertOk()
            ->assertSee('LATO10')
            ->assertSee('−20,00 zł', escape: false);
    }

    public function test_order_without_a_discount_has_no_extra_rows(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $order = Order::factory()->for($shop)->create([
            'delivery_method' => DeliveryMethod::Pickup,
            'delivery_cost' => 0,
            'items_total' => 100,
            'total_gross' => 100,
            'total_net' => 81.30,
        ]);
        $order->items()->create([
            'name' => 'Doniczka', 'unit_price_gross' => 100, 'vat_rate' => '23',
            'quantity' => 1, 'line_total_gross' => 100,
        ]);

        $this->withSession(['recent_order_id' => $order->id])
            ->get($this->host($shop).'/kasa/dziekujemy')
            ->assertOk()
            ->assertDontSee('Rabat');
    }
}
