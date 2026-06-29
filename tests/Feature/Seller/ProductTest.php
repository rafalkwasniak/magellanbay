<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
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
            'name' => 'Kubek ceramiczny',
            'description' => 'Ręcznie robiony.',
            'price_gross' => '49,99',
            'vat_rate' => '23',
            'track_stock' => '1',
            'stock' => '10',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_seller_can_view_products_list(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)->get(route('seller.products.index'))->assertOk();
    }

    public function test_seller_can_create_product_with_normalised_price(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $response = $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload());

        $product = $shop->products()->firstOrFail();
        $response->assertRedirect(route('seller.products.edit', $product))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->id,
            'name' => 'Kubek ceramiczny',
            'price_gross' => '49.99',
            'vat_rate' => '23',
            'stock' => 10,
            'is_active' => true,
        ]);
    }

    public function test_creates_product_with_images(): void
    {
        Storage::fake('public');
        [$seller, $shop] = $this->sellerWithShop();

        $response = $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload([
                'images' => [
                    UploadedFile::fake()->image('a.jpg', 800, 600),
                    UploadedFile::fake()->image('b.jpg', 800, 600),
                ],
            ]));

        $product = $shop->products()->first();
        $response->assertRedirect(route('seller.products.edit', $product));
        $this->assertSame(2, $product->images()->count());
        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_create_rejects_more_than_eight_images(): void
    {
        Storage::fake('public');
        [$seller] = $this->sellerWithShop();
        $images = [];
        for ($i = 0; $i < 9; $i++) {
            $images[] = UploadedFile::fake()->image("p{$i}.jpg", 400, 400);
        }

        $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload(['images' => $images]))
            ->assertSessionHasErrors('images');
    }

    public function test_product_description_html_is_sanitised(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)->post(route('seller.products.store'), $this->payload([
            'description' => '<strong>Solidny</strong><script>alert(1)</script>',
        ]));

        $this->assertSame('<strong>Solidny</strong>', $shop->products()->first()->description);
    }

    public function test_stock_is_nulled_when_tracking_disabled(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)->post(route('seller.products.store'), $this->payload([
            'track_stock' => null,
            'stock' => '5',
        ]));

        $this->assertDatabaseHas('products', ['shop_id' => $shop->id, 'track_stock' => false, 'stock' => null]);
    }

    public function test_free_plan_limits_number_of_products(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Product::factory()->count((int) config('shop.packages.free.max_products'))->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload())
            ->assertSessionHas('error');

        $this->assertSame((int) config('shop.packages.free.max_products'), $shop->products()->count());
    }

    public function test_seller_cannot_edit_another_shops_product(): void
    {
        [$seller] = $this->sellerWithShop();
        $otherProduct = Product::factory()->create();

        $this->actingAs($seller)->get(route('seller.products.edit', $otherProduct))->assertForbidden();
    }

    public function test_seller_can_delete_own_product(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)
            ->post(route('seller.products.destroy', $product))
            ->assertRedirect(route('seller.products.index'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
