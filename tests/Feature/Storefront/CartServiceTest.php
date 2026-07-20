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

        // Licznik = liczba POZYCJI (jeden produkt), ilość zsumowana w koszyku.
        $this->assertSame(1, $this->cart()->count($shop->id));
        $this->assertSame([$product->id => 5.0], $this->cart()->raw($shop->id));
    }

    public function test_add_caps_to_tracked_stock(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop, ['track_stock' => true, 'stock' => 4]);

        $this->cart()->add($product, 10);

        $this->assertSame([$product->id => 4.0], $this->cart()->raw($shop->id));
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

        $this->assertSame(3.0, $lines->first()['quantity']);
        $this->assertSame(1, $this->cart()->count($shop->id));
    }

    public function test_reconcile_reports_quantity_adjustment_when_stock_drops(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop, ['name' => 'Bukiet', 'track_stock' => true, 'stock' => 9]);
        $this->cart()->add($product, 8);

        $product->update(['stock' => 3]);
        $result = $this->cart()->reconcile($shop->id);

        $this->assertSame(3.0, $result['lines']->first()['quantity']);
        $this->assertCount(1, $result['notices']);
        $this->assertStringContainsString('Bukiet', $result['notices'][0]);
        $this->assertStringContainsString('dostosowana do dostępności', $result['notices'][0]);
    }

    public function test_reconcile_reports_removed_product(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop, ['name' => 'Świeca']);
        $this->cart()->add($product, 2);

        $product->update(['is_active' => false]);
        $result = $this->cart()->reconcile($shop->id);

        $this->assertTrue($result['lines']->isEmpty());
        $this->assertContains('Jeden lub więcej produktów nie jest już dostępnych i został usunięty z koszyka.', $result['notices']);
    }

    public function test_reconcile_reports_sold_out_product(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop, ['name' => 'Kubek', 'track_stock' => true, 'stock' => 4]);
        $this->cart()->add($product, 4);

        $product->update(['stock' => 0]);
        $result = $this->cart()->reconcile($shop->id);

        $this->assertTrue($result['lines']->isEmpty());
        $this->assertCount(1, $result['notices']);
        $this->assertStringContainsString('Kubek', $result['notices'][0]);
        $this->assertStringContainsString('wyprzedany', $result['notices'][0]);
    }

    public function test_reconcile_has_no_notices_when_nothing_changed(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop, ['track_stock' => true, 'stock' => 10]);
        $this->cart()->add($product, 3);

        $result = $this->cart()->reconcile($shop->id);

        $this->assertSame(3.0, $result['lines']->first()['quantity']);
        $this->assertSame([], $result['notices']);
    }

    public function test_weight_product_takes_fractional_quantity_and_caps_to_stock(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop, [
            'sale_unit' => \App\Enums\SaleUnit::Weight,
            'track_stock' => true,
            'stock' => 2.50,
            'price_gross' => 20.00,
        ]);

        // Krok 0,5 kg + dokładna waga z palca sumują się jak liczby.
        $this->cart()->add($product, 0.5);
        $this->cart()->setQuantity($shop->id, $product->id, 1.20);

        $lines = $this->cart()->lines($shop->id);
        $this->assertSame(1.20, $lines->first()['quantity']);
        $this->assertSame(24.0, $lines->first()['line_total']);   // 1,20 kg × 20,00 zł

        // Powyżej stanu przycina do dostępnej wagi (2,50 kg).
        $this->cart()->setQuantity($shop->id, $product->id, 9.0);
        $this->assertSame(2.50, $this->cart()->lines($shop->id)->first()['quantity']);
    }

    public function test_weight_quantity_below_minimum_removes_line(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->product($shop, ['sale_unit' => \App\Enums\SaleUnit::Weight]);
        $this->cart()->add($product, 1.0);

        // Podłoga = 0,5 kg; wpisane 0,2 kg schodzi poniżej minimum → usunięcie.
        $this->cart()->setQuantity($shop->id, $product->id, 0.2);

        $this->assertSame(0, $this->cart()->count($shop->id));
    }
}
