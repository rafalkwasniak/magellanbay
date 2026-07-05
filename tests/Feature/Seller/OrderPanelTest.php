<?php

namespace Tests\Feature\Seller;

use App\Enums\SaleUnit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel zamówień sprzedawcy: lista i szczegół. Zamówienia powstają w kasie
 * (OrderService) — tu tylko je oglądamy, wyłącznie własne, z ilością i sumami
 * z migawki (w tym waga „2,50 kg").
 */
class OrderPanelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(array $shopAttributes = []): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(array_merge(['owner_id' => $seller->id], $shopAttributes));

        return [$seller, $shop];
    }

    public function test_orders_tab_is_reachable(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->get(route('seller.orders.index'))
            ->assertOk()
            ->assertSee('Zamówienia');
    }

    public function test_empty_state_when_no_orders(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->get(route('seller.orders.index'))
            ->assertOk()
            ->assertSee('Nie masz jeszcze zamówień');
    }

    public function test_list_shows_only_own_shop_orders(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $mine = Order::factory()->for($shop)->create(['number' => 7, 'buyer_name' => 'Anna', 'buyer_surname' => 'Kowalska']);
        $foreign = Order::factory()->create(['number' => 999, 'buyer_name' => 'Obca', 'buyer_surname' => 'Firma']);

        $this->actingAs($seller)
            ->get(route('seller.orders.index'))
            ->assertOk()
            ->assertSee('#7')
            ->assertSee('Anna Kowalska')
            ->assertDontSee('#999')
            ->assertDontSee('Obca Firma');
    }

    public function test_detail_shows_snapshot_with_weight_formatting(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create([
            'number' => 12,
            'buyer_name' => 'Jan',
            'buyer_surname' => 'Nowak',
            'buyer_email' => 'jan@example.com',
            'total_gross' => 45.00,
        ]);
        OrderItem::factory()->for($order)->create([
            'name' => 'Kiełbasa swojska',
            'sale_unit' => SaleUnit::Weight,
            'quantity' => 2.50,
            'unit_price_gross' => 18.00,
            'line_total_gross' => 45.00,
        ]);

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('Zamówienie #12')
            ->assertSee('Jan Nowak')
            ->assertSee('jan@example.com')
            ->assertSee('Kiełbasa swojska')
            ->assertSee('2,50 kg');
    }

    public function test_pickup_order_has_no_delivery_cost_line(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        // Fabryka domyślnie tworzy odbiór osobisty (Pickup) — brak wysyłki.
        $order = Order::factory()->for($shop)->create(['number' => 3]);
        OrderItem::factory()->for($order)->create();

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('Odbiór osobisty')    // metoda w sekcji „Dostawa i płatność"
            ->assertDontSee('gratis');        // ale bez wiersza kosztu dostawy w podsumowaniu
    }

    public function test_cannot_view_foreign_order(): void
    {
        [$seller] = $this->sellerWithShop();
        $foreign = Order::factory()->create();

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $foreign))
            ->assertForbidden();
    }
}
