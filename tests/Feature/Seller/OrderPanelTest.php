<?php

namespace Tests\Feature\Seller;

use App\Enums\SaleUnit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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

    public function test_list_filters_by_status(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Order::factory()->for($shop)->create(['number' => 1, 'buyer_name' => 'Nowezam', 'status' => \App\Enums\OrderStatus::New]);
        Order::factory()->for($shop)->create(['number' => 2, 'buyer_name' => 'Wyslanezam', 'status' => \App\Enums\OrderStatus::Shipped]);

        $this->actingAs($seller)
            ->get(route('seller.orders.index', ['status' => 'shipped']))
            ->assertOk()
            ->assertSee('Wyslanezam')
            ->assertDontSee('Nowezam');
    }

    public function test_list_filters_by_amount_range(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Order::factory()->for($shop)->create(['number' => 1, 'buyer_name' => 'Alfazam', 'total_gross' => 10]);
        Order::factory()->for($shop)->create(['number' => 2, 'buyer_name' => 'Betazam', 'total_gross' => 50]);
        Order::factory()->for($shop)->create(['number' => 3, 'buyer_name' => 'Gammazam', 'total_gross' => 200]);

        $this->actingAs($seller)
            ->get(route('seller.orders.index', ['kwota_od' => '20', 'kwota_do' => '100']))
            ->assertOk()
            ->assertSee('Betazam')
            ->assertDontSee('Alfazam')
            ->assertDontSee('Gammazam');
    }

    public function test_list_filters_by_date_range(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Order::factory()->for($shop)->create(['number' => 1, 'buyer_name' => 'Starezam'])
            ->forceFill(['created_at' => '2026-01-10 12:00:00'])->save();
        Order::factory()->for($shop)->create(['number' => 2, 'buyer_name' => 'Nowezam'])
            ->forceFill(['created_at' => '2026-06-10 12:00:00'])->save();

        $this->actingAs($seller)
            ->get(route('seller.orders.index', ['data_od' => '2026-05-01', 'data_do' => '2026-07-01']))
            ->assertOk()
            ->assertSee('Nowezam')
            ->assertDontSee('Starezam');
    }

    public function test_list_filters_by_product(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Widżet']);

        $withProduct = Order::factory()->for($shop)->create(['number' => 1, 'buyer_name' => 'Zproduktemzam']);
        OrderItem::factory()->for($withProduct)->create(['product_id' => $product->id, 'name' => 'Widżet']);

        $withoutProduct = Order::factory()->for($shop)->create(['number' => 2, 'buyer_name' => 'Bezproduktuzam']);
        OrderItem::factory()->for($withoutProduct)->create(['product_id' => null, 'name' => 'Inny']);

        $this->actingAs($seller)
            ->get(route('seller.orders.index', ['produkt' => $product->id]))
            ->assertOk()
            ->assertSee('Zproduktemzam')
            ->assertDontSee('Bezproduktuzam');
    }

    public function test_list_sorts_by_amount_ascending(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Order::factory()->for($shop)->create(['number' => 1, 'buyer_name' => 'Malyzam', 'total_gross' => 10]);
        Order::factory()->for($shop)->create(['number' => 2, 'buyer_name' => 'Duzyzam', 'total_gross' => 999]);

        $content = $this->actingAs($seller)
            ->get(route('seller.orders.index', ['sortowanie' => 'kwota']))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($content, 'Duzyzam'),
            strpos($content, 'Malyzam'),
            'Sortowanie po kwocie (rosnąco) powinno ustawić tańsze zamówienie wyżej — jak „Cena" w Produktach.'
        );
    }

    public function test_sales_stats_reflect_filtered_set(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $included = Order::factory()->for($shop)->create(['number' => 1, 'total_gross' => 100]);
        OrderItem::factory()->for($included)->create(['quantity' => 2]);
        $excluded = Order::factory()->for($shop)->create(['number' => 2, 'total_gross' => 50]);
        OrderItem::factory()->for($excluded)->create(['quantity' => 3]);

        $this->actingAs($seller)
            ->get(route('seller.orders.index', ['kwota_od' => '80']))
            ->assertOk()
            ->assertSee('Twoja sprzedaż')
            ->assertSee('Z wyświetlonych zamówień')
            ->assertSee('100,00 zł')      // przychód z przefiltrowanego zbioru
            ->assertDontSee('50,00 zł');  // wykluczone zamówienie nie wchodzi do statystyk
    }

    public function test_sales_stats_without_filters_cover_all_orders(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Order::factory()->for($shop)->create(['number' => 1, 'total_gross' => 100]);

        $this->actingAs($seller)
            ->get(route('seller.orders.index'))
            ->assertOk()
            ->assertSee('Twoja sprzedaż')
            ->assertSee('Ze wszystkich zamówień');
    }

    public function test_filtered_empty_result_shows_hint_not_true_empty_state(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Order::factory()->for($shop)->create(['number' => 1, 'total_gross' => 10]);

        $this->actingAs($seller)
            ->get(route('seller.orders.index', ['kwota_od' => '9999']))
            ->assertOk()
            ->assertSee('Brak zamówień pasujących do filtrów')
            ->assertDontSee('Nie masz jeszcze zamówień');
    }

    public function test_order_link_and_back_link_carry_filter_context(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create(['number' => 5, 'total_gross' => 100]);

        // Lista z filtrem → link do zamówienia niesie kontekst dalej.
        $this->actingAs($seller)
            ->get(route('seller.orders.index', ['kwota_od' => '1', 'sortowanie' => 'kwota']))
            ->assertOk()
            ->assertSee('kwota_od=1', false)
            ->assertSee('sortowanie=kwota', false);

        // Szczegół otwarty z kontekstu → „Wróć do listy" niesie go z powrotem (ze stroną).
        $this->actingAs($seller)
            ->get(route('seller.orders.show', ['order' => $order, 'kwota_od' => '1', 'page' => 2]))
            ->assertOk()
            ->assertSee('Wróć do listy')
            ->assertSee('kwota_od=1', false)
            ->assertSee('page=2', false);
    }
}
