<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anulowane zamówienie nie liczy się jako zakup w ŻADNYCH ilościach ani kwotach
 * (ustalenie Rafała 2026-07-14) — ani na Pulpicie, ani w karcie „Twoja sprzedaż",
 * ani na koncie klienta. Zostaje przy tym widoczne na listach: to ślad, że taka
 * sytuacja miała miejsce (zamówienie mogło być opłacone i dopiero potem anulowane).
 */
class CancelledOrdersNotCountedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);

        return [$seller, $shop];
    }

    /**
     * Zamówienie na 100 zł z dwiema sztukami produktu.
     */
    private function order(Shop $shop, OrderStatus $status, int $number): Order
    {
        $order = Order::factory()->for($shop)->create([
            'number' => $number,
            'status' => $status,
            'total_gross' => 100.00,
        ]);
        OrderItem::factory()->for($order)->create(['quantity' => 2]);

        return $order;
    }

    public function test_sales_card_ignores_cancelled_orders(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $this->order($shop, OrderStatus::Completed, 1);
        $this->order($shop, OrderStatus::Cancelled, 2);

        $response = $this->actingAs($seller)->get(route('seller.orders.index'))->assertOk();

        $stats = $response->viewData('stats');
        $this->assertSame(1, $stats['orders']);          // nie 2
        $this->assertSame(100.0, $stats['revenue']);     // nie 200
        $this->assertSame(2.0, $stats['products']);      // nie 4
    }

    public function test_cancelled_order_still_shows_on_the_list(): void
    {
        // Nie liczymy go, ale zostaje w historii — musi być widoczny.
        [$seller, $shop] = $this->sellerWithShop();
        $this->order($shop, OrderStatus::Cancelled, 7);

        $this->actingAs($seller)
            ->get(route('seller.orders.index'))
            ->assertOk()
            ->assertSee('Anulowane')
            ->assertSee('#7');
    }

    public function test_shop_with_only_cancelled_orders_still_renders_the_list(): void
    {
        // `$total` steruje pustym ekranem — to nie jest miara sprzedaży, więc
        // anulowane muszą się tam liczyć, inaczej lista zniknęłaby razem z nimi.
        [$seller, $shop] = $this->sellerWithShop();
        $this->order($shop, OrderStatus::Cancelled, 1);

        $response = $this->actingAs($seller)->get(route('seller.orders.index'))->assertOk();

        $this->assertSame(1, $response->viewData('total'));
        $this->assertSame(0, $response->viewData('stats')['orders']);
    }

    public function test_dashboard_ignores_cancelled_orders(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $this->order($shop, OrderStatus::Completed, 1);
        $this->order($shop, OrderStatus::Cancelled, 2);

        $response = $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk();

        $this->assertSame(1, $response->viewData('orderCount'));
        $this->assertSame(100.0, $response->viewData('revenue'));
    }

    public function test_customer_account_does_not_count_cancelled_as_spending(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        $paid = $this->order($shop, OrderStatus::Completed, 1);
        $cancelled = $this->order($shop, OrderStatus::Cancelled, 2);
        $paid->update(['customer_id' => $customer->id]);
        $cancelled->update(['customer_id' => $customer->id]);

        $response = $this->actingAs($customer, 'customer')
            ->get('https://'.$shop->host().'/moje-konto')
            ->assertOk();

        $this->assertSame(1, $response->viewData('ordersCount'));
        // assertEquals, nie assertSame: sum() oddaje liczbę o typie zależnym od
        // sterownika bazy (SQLite w testach vs MySQL na produkcji).
        $this->assertEquals(100.0, $response->viewData('totalSpent'));
    }
}
