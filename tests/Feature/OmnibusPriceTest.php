<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OmnibusPriceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Buduje produktowi kontrolowaną historię cen: czyści wpis założony przez
     * obserwer i wstawia własne, z jawnym recorded_at.
     *
     * @param  array<int, array{price: float|int, days_ago: int}>  $points
     */
    private function withPriceHistory(Product $product, array $points): Product
    {
        $product->priceHistory()->delete();

        foreach ($points as $point) {
            $product->priceHistory()->create([
                'price_gross' => $point['price'],
                'recorded_at' => now()->subDays($point['days_ago']),
            ]);
        }

        return $product->load('priceHistory');
    }

    public function test_creating_a_product_records_the_initial_price(): void
    {
        $product = Product::factory()->create(['price_gross' => 50]);

        $this->assertSame(1, $product->priceHistory()->count());
        $this->assertSame(50.0, (float) $product->priceHistory()->first()->price_gross);
    }

    public function test_changing_the_price_records_a_new_entry(): void
    {
        $product = Product::factory()->create(['price_gross' => 50]);

        $product->update(['price_gross' => 45]);

        $this->assertSame(2, $product->priceHistory()->count());
        $this->assertSame(45.0, (float) $product->priceHistory()->reorder()->orderByDesc('id')->first()->price_gross);
    }

    public function test_changing_a_non_price_field_records_nothing(): void
    {
        $product = Product::factory()->create(['price_gross' => 50]);

        $product->update(['name' => 'Inna nazwa']);

        $this->assertSame(1, $product->priceHistory()->count());
    }

    public function test_shows_the_earlier_price_after_a_recent_reduction(): void
    {
        $product = Product::factory()->create(['price_gross' => 50]);
        $this->withPriceHistory($product, [
            ['price' => 60, 'days_ago' => 10],
            ['price' => 50, 'days_ago' => 2],
        ]);

        $this->assertSame(60.0, $product->lowestPriceLast30Days());
    }

    public function test_takes_the_lowest_of_several_prices_in_the_window(): void
    {
        $product = Product::factory()->create(['price_gross' => 45]);
        $this->withPriceHistory($product, [
            ['price' => 60, 'days_ago' => 20],
            ['price' => 55, 'days_ago' => 10],
            ['price' => 45, 'days_ago' => 1],
        ]);

        $this->assertSame(55.0, $product->lowestPriceLast30Days());
    }

    public function test_is_silent_when_the_reduction_is_older_than_30_days(): void
    {
        $product = Product::factory()->create(['price_gross' => 50]);
        $this->withPriceHistory($product, [
            ['price' => 60, 'days_ago' => 40],
            ['price' => 50, 'days_ago' => 35],
        ]);

        $this->assertNull($product->lowestPriceLast30Days());
    }

    public function test_is_silent_on_a_price_increase(): void
    {
        $product = Product::factory()->create(['price_gross' => 60]);
        $this->withPriceHistory($product, [
            ['price' => 50, 'days_ago' => 10],
            ['price' => 60, 'days_ago' => 2],
        ]);

        $this->assertNull($product->lowestPriceLast30Days());
    }

    public function test_is_silent_for_a_fresh_product_without_price_changes(): void
    {
        $product = Product::factory()->create(['price_gross' => 50]);

        $this->assertNull($product->lowestPriceLast30Days());
    }
}
