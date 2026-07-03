<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logika koszyka (sesja): dodawanie, ograniczanie stanem, uzgadnianie pozycji
 * (odsiew produktów zdjętych/wyprzedanych) oraz sumy. Trzon całego flow zakupu.
 */
class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private function cart(): CartService
    {
        return app(CartService::class);
    }

    private function product(Shop $shop, array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'is_active' => true,
            'track_stock' => false,
            'stock' => null,
            'price_gross' => 10.00,
        ], $attrs));
    }

    public function test_add_accumulates_quantity(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop);

        $this->cart()->add($product, 2);
        $this->cart()->add($product, 3);

        $this->assertSame(5, $this->cart()->count($shop->id));
        $this->assertSame([$product->id => 5], $this->cart()->raw($shop->id));
    }

    public function test_add_caps_to_tracked_stock(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop, ['track_stock' => true, 'stock' => 4]);

        $this->cart()->add($product, 10);

        $this->assertSame(4, $this->cart()->count($shop->id));
    }

    public function test_inactive_or_foreign_products_are_ignored(): void
    {
        $shop = Shop::factory()->create();
        $inactive = $this->product($shop, ['is_active' => false]);

        $this->cart()->add($inactive, 1);

        $this->assertSame(0, $this->cart()->count($shop->id));
    }

    public function test_set_quantity_zero_removes_line(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop);
        $this->cart()->add($product, 3);

        $this->cart()->setQuantity($shop->id, $product->id, 0);

        $this->assertSame(0, $this->cart()->count($shop->id));
    }

    public function test_lines_compute_totals_from_fresh_price(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop, ['price_gross' => 12.50]);
        $this->cart()->add($product, 2);

        $lines = $this->cart()->lines($shop->id);

        $this->assertCount(1, $lines);
        $this->assertSame(25.0, $lines->first()['line_total']);
        $this->assertSame(25.0, $this->cart()->total($shop->id));
    }

    public function test_lines_drop_products_that_became_inactive(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop);
        $this->cart()->add($product, 2);

        $product->update(['is_active' => false]);
        $lines = $this->cart()->lines($shop->id);

        $this->assertTrue($lines->isEmpty());
        // Uzgodnienie czyści też sesję.
        $this->assertSame(0, $this->cart()->count($shop->id));
    }

    public function test_lines_cap_quantity_when_stock_drops(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop, ['track_stock' => true, 'stock' => 9]);
        $this->cart()->add($product, 8);

        $product->update(['stock' => 3]);
        $lines = $this->cart()->lines($shop->id);

        $this->assertSame(3, $lines->first()['quantity']);
        $this->assertSame(3, $this->cart()->count($shop->id));
    }
}
