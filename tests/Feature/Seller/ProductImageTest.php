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
            ->postJson(route('seller.products.images.store', $product), [
                'images' => [
                    UploadedFile::fake()->image('a.jpg', 2000, 1500),
                    UploadedFile::fake()->image('b.png', 800, 800),
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'images');

        $this->assertSame(2, $product->images()->count());
        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists($image->path);
            // Każdy format wejściowy (jpg/png) jest przekodowany na WebP.
            $this->assertStringEndsWith('.webp', $image->path);
        }
    }

    public function test_image_limit_is_enforced(): void
    {
        Storage::fake('public');
        [$seller, $product] = $this->sellerProduct();

        // Próg bierzemy z konfiguracji, a nie wpisujemy liczbą: sklep dedykowany
        // podnosi go w `.env` do stu, więc test na sztywne osiem przestałby
        // sprawdzać REGUŁĘ, a zaczął sprawdzać wartość akurat wpisaną w kodzie.
        // Ustawiamy własną, niską — test jest wtedy szybki i niezależny od tego,
        // jak skonfigurowano środowisko, w którym akurat leci.
        config()->set('shop.product_images.max_per_product', 3);
        $limit = (int) config('shop.product_images.max_per_product');

        foreach (range(1, $limit) as $i) {
            $product->images()->create(['path' => "products/{$product->id}/{$i}.jpg", 'position' => $i]);
        }

        $this->actingAs($seller)
            ->postJson(route('seller.products.images.store', $product), [
                'images' => [UploadedFile::fake()->image('x.jpg', 600, 600)],
            ])
            ->assertStatus(422);

        $this->assertSame($limit, $product->images()->count());
    }

    public function test_reorder_sets_positions(): void
    {
        [$seller, $product] = $this->sellerProduct();
        $a = $product->images()->create(['path' => 'products/a.jpg', 'position' => 0]);
        $b = $product->images()->create(['path' => 'products/b.jpg', 'position' => 1]);
        $c = $product->images()->create(['path' => 'products/c.jpg', 'position' => 2]);

        $this->actingAs($seller)
            ->postJson(route('seller.products.images.reorder', $product), ['order' => [$c->id, $a->id, $b->id]])
            ->assertOk();

        $this->assertSame([$c->id, $a->id, $b->id], $product->images()->pluck('id')->all());
    }

    public function test_other_shop_cannot_reorder(): void
    {
        [$seller] = $this->sellerProduct();
        $foreign = Product::factory()->create();
        $image = $foreign->images()->create(['path' => 'products/x.jpg', 'position' => 0]);

        $this->actingAs($seller)
            ->postJson(route('seller.products.images.reorder', $foreign), ['order' => [$image->id]])
            ->assertForbidden();
    }

    public function test_delete_removes_file_and_row(): void
    {
        Storage::fake('public');
        [$seller, $product] = $this->sellerProduct();
        Storage::disk('public')->put('products/del.jpg', 'x');
        $image = $product->images()->create(['path' => 'products/del.jpg', 'position' => 1]);

        $this->actingAs($seller)
            ->postJson(route('seller.products.images.destroy', [$product, $image]))
            ->assertOk();

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing('products/del.jpg');
    }

    public function test_other_shop_cannot_upload_images(): void
    {
        Storage::fake('public');
        [$seller] = $this->sellerProduct();
        $foreign = Product::factory()->create();

        $this->actingAs($seller)
            ->postJson(route('seller.products.images.store', $foreign), [
                'images' => [UploadedFile::fake()->image('a.jpg', 600, 600)],
            ])
            ->assertForbidden();
    }
}
