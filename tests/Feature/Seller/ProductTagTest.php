<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTagTest extends TestCase
{
    use RefreshDatabase;

    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);

        return [$seller, $shop];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pierścionek',
            'price_gross' => '120,00',
            'vat_rate' => '23',
            'track_stock' => '1',
            'stock' => '5',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_tags_are_created_and_attached(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)->post(route('seller.products.store'), $this->payload([
            'tags' => 'biżuteria, srebro, prezent',
        ]));

        $product = $shop->products()->first();
        $this->assertSame(3, $product->tags()->count());
        $this->assertDatabaseHas('tags', ['shop_id' => $shop->id, 'name' => 'srebro']);
    }

    public function test_tags_are_reused_within_shop(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)->post(route('seller.products.store'), $this->payload(['name' => 'A', 'tags' => 'Srebro']));
        $this->actingAs($seller)->post(route('seller.products.store'), $this->payload(['name' => 'B', 'tags' => 'srebro, nowy']));

        // „Srebro" i „srebro" → ten sam slug → jeden tag.
        $this->assertSame(1, $shop->tags()->where('slug', 'srebro')->count());
        $this->assertSame(2, $shop->tags()->count());
    }

    public function test_updating_product_replaces_tags(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $product->tags()->attach(
            collect(['stare', 'inne'])->map(fn ($n) => $shop->tags()->create(['name' => $n, 'slug' => $n])->id)
        );

        $this->actingAs($seller)->post(route('seller.products.update', $product), $this->payload(['tags' => 'nowy']));

        $tags = $product->fresh()->tags;
        $this->assertSame(1, $tags->count());
        $this->assertSame('nowy', $tags->first()->name);
    }
}
