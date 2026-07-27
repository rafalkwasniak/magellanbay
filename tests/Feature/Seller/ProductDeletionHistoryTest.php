<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Usuwanie produktu a historia sprzedaży. Produkt, który wystąpił w zamówieniu,
 * kasujemy MIĘKKO — bo `order_items.product_id` ma `nullOnDelete`, więc trwałe
 * usunięcie zrywa powiązanie pozycji z katalogiem: bestsellery w analityce
 * przestają widzieć produkt, a moduł zwrotów traci flagę art. 38.
 *
 * Do 2026-07-27 `hasBeenOrdered()` było zaślepką zwracającą `false`, więc KAŻDY
 * produkt kasował się trwale — te testy pilnują, żeby zaślepka nie wróciła.
 */
class ProductDeletionHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();

        return [$seller, Shop::factory()->create(['owner_id' => $seller->id])];
    }

    private function orderProduct(Shop $shop, Product $product, OrderStatus $status = OrderStatus::New): void
    {
        $order = Order::factory()->for($shop)->create(['status' => $status]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price_gross' => 100,
            'vat_rate' => '23',
            'quantity' => 1,
            'line_total_gross' => 100,
        ]);
    }

    public function test_product_without_orders_is_purged(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $this->assertFalse($product->hasBeenOrdered());

        $this->actingAs($seller)->post(route('seller.products.destroy', $product))->assertRedirect();

        // Śmieć po testach — znika na dobre, razem z rekordami pobocznymi.
        $this->assertSame(0, Product::withTrashed()->whereKey($product->id)->count());
    }

    public function test_ordered_product_is_only_soft_deleted(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $this->orderProduct($shop, $product);

        $this->assertTrue($product->fresh()->hasBeenOrdered());

        $this->actingAs($seller)->post(route('seller.products.destroy', $product))->assertRedirect();

        $this->assertSoftDeleted($product);
        $this->assertNotNull(Product::withTrashed()->find($product->id));
    }

    public function test_order_item_keeps_pointing_at_the_deleted_product(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $this->orderProduct($shop, $product);

        $this->actingAs($seller)->post(route('seller.products.destroy', $product))->assertRedirect();

        // Sedno: powiązanie przeżywa usunięcie — bez tego analityka i zwroty
        // przestają rozpoznawać produkt.
        $item = $shop->orders()->first()->items()->first();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame($product->id, $item->product()->withTrashed()->first()->id);
    }

    public function test_cancelled_order_also_counts_as_history(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $this->orderProduct($shop, $product, OrderStatus::Cancelled);

        // Anulowane zamówienie nie liczy się do statystyk, ale JEST śladem —
        // trwałe usunięcie produktu zerwałoby powiązanie także jemu.
        $this->assertTrue($product->fresh()->hasBeenOrdered());

        $this->actingAs($seller)->post(route('seller.products.destroy', $product))->assertRedirect();

        $this->assertSoftDeleted($product);
    }
}
