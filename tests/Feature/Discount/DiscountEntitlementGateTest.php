<?php

namespace Tests\Feature\Discount;

use App\Exceptions\CartNeedsReviewException;
use App\Livewire\Cart;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\DiscountResolver;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Brama pakietu na FRONCIE. Kody rabatowe to funkcja Pawilonu, więc sklep bez
 * uprawnienia `discount_codes` nie pokazuje nawet pola w koszyku, a kody, które
 * zostały mu po zejściu z pakietu, przestają działać NATYCHMIAST — zgodnie z
 * zasadą „funkcje gasną od razu przy zejściu" (plan pakietów).
 *
 * Bramka siedzi w DiscountResolver, bo to jedyna droga do naliczenia rabatu —
 * ukrycie samego pola w widoku byłoby kosmetyką.
 */
class DiscountEntitlementGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Shop, 1: Product}
     */
    private function shopWithoutEntitlement(): array
    {
        $shop = Shop::factory()->create();   // Kram: discount_codes = false
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'price_gross' => 200,
            'stock' => 10,
        ]);

        app(CartService::class)->add($product, 1);

        return [$shop, $product];
    }

    public function test_cart_has_no_discount_field_without_the_entitlement(): void
    {
        [$shop] = $this->shopWithoutEntitlement();

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->assertViewHas('discountsEnabled', false)
            ->assertDontSee('Kod rabatowy')
            ->assertDontSee('Masz kod rabatowy');
    }

    public function test_cart_shows_the_field_with_the_entitlement(): void
    {
        $shop = Shop::factory()->withDiscountCodes()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'stock' => 10]);
        app(CartService::class)->add($product, 1);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->assertViewHas('discountsEnabled', true)
            ->assertSee('Kod rabatowy');
    }

    public function test_code_left_after_a_downgrade_stops_working(): void
    {
        [$shop] = $this->shopWithoutEntitlement();
        // Kod z czasów Pawilonu — sam wiersz w bazie zostaje po zejściu z pakietu.
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10', 'value' => 10]);

        $result = app(DiscountResolver::class)->resolve(
            $shop,
            'LATO10',
            app(CartService::class)->lines($shop->id),
        );

        $this->assertFalse($result->accepted());
        // Kupującemu nie mówimy nic o pakiecie sprzedawcy.
        $this->assertSame('Nie znamy takiego kodu.', $result->error);
    }

    public function test_order_cannot_be_placed_with_a_code_from_a_downgraded_shop(): void
    {
        [$shop] = $this->shopWithoutEntitlement();
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10', 'value' => 10]);

        // Kod wbity do sesji z czasów, gdy sklep miał jeszcze pakiet.
        app(CartService::class)->setDiscountCode($shop->id, 'LATO10');

        try {
            app(OrderService::class)->place($shop, [
                'buyer_name' => 'Anna',
                'buyer_surname' => 'Kowalska',
                'buyer_email' => 'anna@przyklad.test',
                'buyer_phone' => '500600700',
                'delivery_method' => \App\Enums\DeliveryMethod::Pickup->value,
                'payment_method' => \App\Enums\PaymentMethod::PayOnPickup->value,
            ]);
            $this->fail('Zamówienie nie powinno powstać z kodem sklepu bez uprawnienia.');
        } catch (CartNeedsReviewException $e) {
            $this->assertStringContainsString('LATO10', $e->messages[0]);
        }

        $this->assertSame(0, $shop->orders()->count());
    }
}
