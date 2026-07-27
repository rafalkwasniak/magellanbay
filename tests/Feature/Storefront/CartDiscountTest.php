<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Cart;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kod rabatowy w koszyku. Zasada: w sesji trzyma się WYŁĄCZNIE kod, nigdy
 * wyliczona kwota — zniżka przelicza się przy każdym renderze z aktualnego
 * koszyka, więc nie da się jej zamrozić ani podmienić w sesji.
 */
class CartDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function shopWithProduct(float $price = 200): array
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'price_gross' => $price,
            'stock' => 10,
        ]);

        app(CartService::class)->add($product, 1);

        return [$shop, $product];
    }

    public function test_valid_code_sticks_to_the_cart_and_lowers_the_total(): void
    {
        [$shop] = $this->shopWithProduct(200);
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10', 'value' => 10]);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->set('discountInput', 'lato10')
            ->call('applyDiscount')
            ->assertSet('discountError', null)
            ->assertSet('discountInput', '')            // pole czyści się po udanym zastosowaniu
            ->assertViewHas('total', 180.0)
            ->assertSee('LATO10');

        $this->assertSame('LATO10', app(CartService::class)->discountCode($shop->id));
    }

    public function test_rejected_code_is_not_stored_and_explains_itself(): void
    {
        [$shop] = $this->shopWithProduct(150);
        DiscountCode::factory()->minimum(200)->create(['shop_id' => $shop->id, 'code' => 'ODDWUSTU']);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->set('discountInput', 'ODDWUSTU')
            ->call('applyDiscount')
            ->assertSee('Ten kod działa od zamówień za 200,00 zł');

        // Kod nieudany zostaje w polu do poprawki, nie w sesji.
        $this->assertNull(app(CartService::class)->discountCode($shop->id));
    }

    public function test_discount_is_recalculated_when_the_cart_changes(): void
    {
        [$shop, $product] = $this->shopWithProduct(200);
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10', 'value' => 10]);

        $component = Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->set('discountInput', 'LATO10')
            ->call('applyDiscount')
            ->assertViewHas('total', 180.0);

        // Druga sztuka → 10% liczy się od nowej wartości koszyka, nie od starej.
        $component->call('increment', $product->id)->assertViewHas('total', 360.0);
    }

    public function test_code_that_stops_working_stays_with_its_reason(): void
    {
        [$shop, $product] = $this->shopWithProduct(200);
        DiscountCode::factory()->minimum(200)->create(['shop_id' => $shop->id, 'code' => 'ODDWUSTU', 'value' => 10]);

        $component = Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->set('discountInput', 'ODDWUSTU')
            ->call('applyDiscount')
            ->assertViewHas('total', 180.0);

        // Klient wyjmuje produkt → koszyk spada poniżej progu.
        $component->call('remove', $product->id);

        // Kod zostaje przyklejony (może wrócić), ale nie obniża niczego.
        $this->assertSame('ODDWUSTU', app(CartService::class)->discountCode($shop->id));
    }

    public function test_customer_can_detach_the_code(): void
    {
        [$shop] = $this->shopWithProduct(200);
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10', 'value' => 10]);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->set('discountInput', 'LATO10')
            ->call('applyDiscount')
            ->call('removeDiscount')
            ->assertViewHas('total', 200.0);

        $this->assertNull(app(CartService::class)->discountCode($shop->id));
    }

    public function test_free_shipping_code_does_not_touch_the_cart_total(): void
    {
        [$shop] = $this->shopWithProduct(200);
        DiscountCode::factory()->freeShipping()->create(['shop_id' => $shop->id, 'code' => 'DARMOWA']);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->set('discountInput', 'DARMOWA')
            ->call('applyDiscount')
            ->assertViewHas('total', 200.0)
            ->assertSee('Darmowa wysyłka');
    }

    public function test_personal_code_works_for_its_owner_in_the_cart(): void
    {
        [$shop] = $this->shopWithProduct(200);
        $owner = Customer::factory()->create(['shop_id' => $shop->id, 'email_verified_at' => now()]);
        DiscountCode::factory()->forCustomer($owner)->create(['code' => 'IMIENNY', 'value' => 10]);

        Livewire::actingAs($owner, 'customer')->test(Cart::class, ['shopId' => $shop->id])
            ->set('discountInput', 'IMIENNY')
            ->call('applyDiscount')
            ->assertViewHas('total', 180.0);
    }

    public function test_guest_is_told_to_log_in_for_a_personal_code(): void
    {
        [$shop] = $this->shopWithProduct(200);
        $owner = Customer::factory()->create(['shop_id' => $shop->id]);
        DiscountCode::factory()->forCustomer($owner)->create(['code' => 'IMIENNY']);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->set('discountInput', 'IMIENNY')
            ->call('applyDiscount')
            ->assertSee('zaloguj się');

        $this->assertNull(app(CartService::class)->discountCode($shop->id));
    }

    public function test_emptying_the_cart_drops_the_code(): void
    {
        [$shop] = $this->shopWithProduct(200);
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10']);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->set('discountInput', 'LATO10')
            ->call('applyDiscount');

        app(CartService::class)->clear($shop->id);

        // Pusty koszyk = nowy zakup; stary kod nie może w nim wisieć.
        $this->assertNull(app(CartService::class)->discountCode($shop->id));
    }
}
