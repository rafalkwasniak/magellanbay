<?php

namespace Tests\Feature\Storefront;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Enums\VatRate;
use App\Exceptions\CartNeedsReviewException;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rabat w kasie i na zamówieniu. Reguła nadrzędna: kwota, którą klient widzi w
 * podsumowaniu, MUSI być tą, którą zapisze zamówienie — dlatego kod jest
 * sprawdzany po raz drugi na finalnych pozycjach, a nie przepisywany z koszyka.
 */
class CheckoutDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function shop(): Shop
    {
        return Shop::factory()->withCourierShipping()->create([
            'courier_enabled' => true,
            'courier_cost' => 20,
            'bank_transfer_enabled' => true,
            'bank_account_number' => '11111111111111111111111111',
        ]);
    }

    private function product(Shop $shop, float $price = 200, VatRate $vat = VatRate::R23): Product
    {
        return Product::factory()->create([
            'shop_id' => $shop->id,
            'price_gross' => $price,
            'vat_rate' => $vat,
            'stock' => 10,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderData(): array
    {
        return [
            'buyer_name' => 'Anna',
            'buyer_surname' => 'Kowalska',
            'buyer_email' => 'anna@przyklad.test',
            'buyer_phone' => '500600700',
            'delivery_method' => DeliveryMethod::Courier->value,
            'payment_method' => PaymentMethod::BankTransfer->value,
            'ship_street' => 'Kwiatowa',
            'ship_building_number' => '5',
            'ship_postal_code' => '00-001',
            'ship_city' => 'Warszawa',
        ];
    }

    public function test_discount_lands_on_the_order_with_its_snapshot(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop, 200);
        $code = DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10', 'value' => 10]);

        app(CartService::class)->add($product, 1);
        app(CartService::class)->setDiscountCode($shop->id, 'LATO10');

        $order = app(OrderService::class)->place($shop, $this->orderData());

        $this->assertSame($code->id, $order->discount_code_id);
        $this->assertSame('LATO10', $order->discount_code);       // migawka przeżyje skasowanie kodu
        $this->assertSame(20.00, (float) $order->discount_amount);
        $this->assertSame(200.00, (float) $order->items_total);   // produkty PRZED rabatem
        $this->assertSame(200.00, (float) $order->total_gross);   // 200 − 20 + 20 dostawy
    }

    public function test_free_shipping_code_zeroes_the_delivery_cost(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop, 100);
        DiscountCode::factory()->freeShipping()->create(['shop_id' => $shop->id, 'code' => 'DARMOWA']);

        app(CartService::class)->add($product, 1);
        app(CartService::class)->setDiscountCode($shop->id, 'DARMOWA');

        $order = app(OrderService::class)->place($shop, $this->orderData());

        $this->assertSame(0.00, (float) $order->delivery_cost);   // mimo cennika 20 zł
        $this->assertSame(0.00, (float) $order->discount_amount); // produkty bez zmian
        $this->assertSame(100.00, (float) $order->total_gross);
    }

    public function test_discount_splits_across_vat_rates_on_the_order(): void
    {
        $shop = $this->shop();
        $expensive = $this->product($shop, 300, VatRate::R23);
        $cheap = $this->product($shop, 100, VatRate::R5);
        DiscountCode::factory()->amount(100)->create(['shop_id' => $shop->id, 'code' => 'STOWA']);

        app(CartService::class)->add($expensive, 1);
        app(CartService::class)->add($cheap, 1);
        app(CartService::class)->setDiscountCode($shop->id, 'STOWA');

        $order = app(OrderService::class)->place($shop, $this->orderData());

        // Rabat 100 zł z 400 zł: 75 zł z linii 23%, 25 zł z linii 5%.
        $expectedNet = round(225 / 1.23, 2) + round(75 / 1.05, 2);
        $this->assertSame(round($expectedNet, 2), (float) $order->total_net);
        $this->assertSame(round(300 - $expectedNet, 2), (float) $order->total_vat);
        $this->assertSame(320.00, (float) $order->total_gross);   // 400 − 100 + 20 dostawy
    }

    public function test_code_used_up_between_cart_and_checkout_stops_the_order(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop, 200);
        $code = DiscountCode::factory()->limitedTo(1)->create(['shop_id' => $shop->id, 'code' => 'OSTATNI']);

        app(CartService::class)->add($product, 1);
        app(CartService::class)->setDiscountCode($shop->id, 'OSTATNI');

        // Ktoś inny wykorzystuje ostatnie użycie kodu.
        Order::factory()->create(['shop_id' => $shop->id, 'discount_code_id' => $code->id]);

        try {
            app(OrderService::class)->place($shop, $this->orderData());
            $this->fail('Zamówienie nie powinno powstać z kodem, który przestał działać.');
        } catch (CartNeedsReviewException $e) {
            $this->assertStringContainsString('OSTATNI', $e->messages[0]);
            $this->assertStringContainsString('został już wykorzystany', $e->messages[0]);
        }

        // Zamówienie nie powstało (jest tylko to cudze, które zużyło kod),
        // a martwy kod zniknął z koszyka.
        $this->assertSame(0, $shop->orders()->where('buyer_email', 'anna@przyklad.test')->count());
        $this->assertNull(app(CartService::class)->discountCode($shop->id));
    }

    public function test_cart_without_a_code_places_the_order_as_before(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop, 200);

        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, $this->orderData());

        $this->assertNull($order->discount_code_id);
        $this->assertNull($order->discount_code);
        $this->assertSame(0.00, (float) $order->discount_amount);
        $this->assertSame(220.00, (float) $order->total_gross);
    }

    public function test_placing_an_order_uses_up_the_code(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop, 200);
        $code = DiscountCode::factory()->limitedTo(2)->create(['shop_id' => $shop->id, 'code' => 'DWARAZY']);

        app(CartService::class)->add($product, 1);
        app(CartService::class)->setDiscountCode($shop->id, 'DWARAZY');
        app(OrderService::class)->place($shop, $this->orderData());

        $this->assertSame(1, $code->fresh()->usedCount());
        $this->assertSame(1, $code->fresh()->remainingUses());
    }
}
