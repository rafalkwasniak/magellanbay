<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private function sellerProduct(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        return [$seller, $product];
    }

    public function test_seller_can_upload_optimised_images(): void
    {
        Storage::fake('public');
        [$seller, $product] = $this->sellerProduct();

        $this->actingAs($seller)
            ->post(route('seller.products.images.store', $product), [
                'images' => [
                    UploadedFile::fake()->image('a.jpg', 2000, 1500),
                    UploadedFile::fake()->image('b.png', 800, 800),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, $product->images()->count());
        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_image_limit_is_enforced(): void
    {
        Storage::fake('public');
        [$seller, $product] = $this->sellerProduct();
        foreach (range(1, 5) as $i) {
            $product->images()->create(['path' => "products/{$product->id}/{$i}.jpg", 'position' => $i]);
        }

        $this->actingAs($seller)
            ->post(route('seller.products.images.store', $product), [
                'images' => [UploadedFile::fake()->image('x.jpg', 600, 600)],
            ])
            ->assertSessionHas('error');

        $this->assertSame(5, $product->images()->count());
    }

    public function test_set_main_moves_image_to_front(): void
    {
        [$seller, $product] = $this->sellerProduct();
        $first = $product->images()->create(['path' => 'products/a.jpg', 'position' => 1]);
        $second = $product->images()->create(['path' => 'products/b.jpg', 'position' => 2]);

        $this->actingAs($seller)->post(route('seller.products.images.main', [$product, $second]));

        $this->assertSame($second->id, $product->fresh()->mainImage()->id);
    }

    public function test_delete_removes_file_and_row(): void
    {
        Storage::fake('public');
        [$seller, $product] = $this->sellerProduct();
        Storage::disk('public')->put('products/del.jpg', 'x');
        $image = $product->images()->create(['path' => 'products/del.jpg', 'position' => 1]);

        $this->actingAs($seller)->post(route('seller.products.images.destroy', [$product, $image]));

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing('products/del.jpg');
    }

    public function test_other_shop_cannot_upload_images(): void
    {
        Storage::fake('public');
        [$seller] = $this->sellerProduct();
        $foreign = Product::factory()->create();

        $this->actingAs($seller)
            ->post(route('seller.products.images.store', $foreign), [
                'images' => [UploadedFile::fake()->image('a.jpg', 600, 600)],
            ])
            ->assertForbidden();
    }
}
