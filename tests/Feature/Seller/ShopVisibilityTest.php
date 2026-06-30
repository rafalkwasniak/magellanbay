<?php

namespace Tests\Feature\Seller;

use App\Enums\ShopStatus;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Auto-publikacja: status sklepu (= jego widoczność) jest utrzymywany
 * automatycznie z liczby aktywnych produktów, w obie strony. Sprzedawca nie
 * przełącza statusu ręcznie — robi to ProductObserver na każdej zmianie produktu.
 */
class ShopVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_shop_without_products_is_hidden(): void
    {
        $shop = Shop::factory()->create();

        $this->assertSame(ShopStatus::Draft, $shop->status);
        $this->assertFalse($shop->isVisible());
    }

    public function test_first_active_product_publishes_shop(): void
    {
        $shop = Shop::factory()->create();

        Product::factory()->for($shop)->create(['is_active' => true]);

        $this->assertSame(ShopStatus::Active, $shop->refresh()->status);
        $this->assertTrue($shop->isVisible());
    }

    public function test_inactive_product_does_not_publish_shop(): void
    {
        $shop = Shop::factory()->create();

        Product::factory()->for($shop)->hidden()->create();

        $this->assertSame(ShopStatus::Draft, $shop->refresh()->status);
    }

    public function test_deactivating_last_active_product_hides_shop(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->for($shop)->create(['is_active' => true]);
        $this->assertSame(ShopStatus::Active, $shop->refresh()->status);

        $product->update(['is_active' => false]);

        $this->assertSame(ShopStatus::Draft, $shop->refresh()->status);
    }

    public function test_deleting_last_active_product_hides_shop(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->for($shop)->create(['is_active' => true]);
        $this->assertSame(ShopStatus::Active, $shop->refresh()->status);

        $product->delete();

        $this->assertSame(ShopStatus::Draft, $shop->refresh()->status);
    }

    public function test_shop_stays_visible_while_one_active_product_remains(): void
    {
        $shop = Shop::factory()->create();
        $keep = Product::factory()->for($shop)->create(['is_active' => true]);
        $drop = Product::factory()->for($shop)->create(['is_active' => true]);

        $drop->delete();

        $this->assertSame(ShopStatus::Active, $shop->refresh()->status);
    }

    public function test_restoring_an_active_product_republishes_shop(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->for($shop)->create(['is_active' => true]);
        $product->delete();
        $this->assertSame(ShopStatus::Draft, $shop->refresh()->status);

        $product->restore();

        $this->assertSame(ShopStatus::Active, $shop->refresh()->status);
    }
}
