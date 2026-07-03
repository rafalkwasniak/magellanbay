<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Model zamówienia: numeracja per-sklep (ciągła, nieodzyskiwana), relacje i
 * migawka pozycji. Fundament modułu zamówień (spec „Numeracja zamówień").
 */
class OrderModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_numbering_is_per_shop_and_starts_at_one(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        $this->assertSame(1, $shopA->allocateOrderNumber());
        $this->assertSame(2, $shopA->allocateOrderNumber());
        $this->assertSame(3, $shopA->allocateOrderNumber());

        // Osobna, niezależna numeracja drugiego sklepu.
        $this->assertSame(1, $shopB->allocateOrderNumber());

        $this->assertSame(3, $shopA->fresh()->last_order_number);
        $this->assertSame(1, $shopB->fresh()->last_order_number);
    }

    public function test_numbering_is_continuous_and_not_reused_after_delete(): void
    {
        $shop = Shop::factory()->create();

        $first = $shop->allocateOrderNumber();
        $order = Order::factory()->for($shop)->create(['number' => $first]);
        $order->delete();   // usunięcie logiczne

        // Kolejny numer nie wraca do usuniętego — licznik jest monotoniczny.
        $this->assertSame(2, $shop->allocateOrderNumber());
    }

    public function test_order_has_items_and_casts(): void
    {
        $shop = Shop::factory()->create();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::New,
            'total_gross' => 123.45,
        ]);
        OrderItem::factory()->count(2)->for($order)->create();

        $fresh = $order->fresh();

        $this->assertInstanceOf(OrderStatus::class, $fresh->status);
        $this->assertSame(OrderStatus::New, $fresh->status);
        $this->assertSame('123.45', $fresh->total_gross);
        $this->assertCount(2, $fresh->items);
        $this->assertTrue($shop->orders()->whereKey($order->getKey())->exists());
    }

    public function test_soft_deleted_order_is_hidden_but_kept(): void
    {
        $order = Order::factory()->create();
        $order->delete();

        $this->assertSame(0, Order::count());
        $this->assertSame(1, Order::withTrashed()->count());
    }
}
