<?php

namespace Tests\Feature\Seller;

use App\Enums\AnalyticsPeriod;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Analityka Poziomu 1 — liczby z danych, które już mamy (orders). Sprawdzamy, że
 * KPI liczą się poprawnie (obrót/zamówienia/AOV/klienci), anulowane odpadają,
 * Δ% bierze poprzednie okno tej samej długości, a dane są scope'owane do sklepu.
 */
class AnalyticsTest extends TestCase
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

    private function sale(Shop $shop, float $gross, string $email, string $createdAt): void
    {
        Order::factory()->for($shop)->create([
            'status' => OrderStatus::Paid,
            'total_gross' => $gross,
            'buyer_email' => $email,
            'created_at' => $createdAt,
        ]);
    }

    public function test_kpis_are_computed_from_orders_in_window(): void
    {
        [, $shop] = $this->sellerWithShop();

        // Bieżące okno (ostatnie 30 dni): 2 zakupy, 2 różnych klientów.
        $this->sale($shop, 100.0, 'a@example.com', now()->subDays(2)->toDateTimeString());
        $this->sale($shop, 50.0, 'b@example.com', now()->subDays(3)->toDateTimeString());

        // Anulowane — nie liczy się do niczego.
        Order::factory()->for($shop)->create([
            'status' => OrderStatus::Cancelled,
            'total_gross' => 999.0,
            'buyer_email' => 'c@example.com',
            'created_at' => now()->subDays(4)->toDateTimeString(),
        ]);

        // Poprzednie okno (30–60 dni temu): obrót 200 → baza do Δ%.
        $this->sale($shop, 200.0, 'a@example.com', now()->subDays(40)->toDateTimeString());

        $kpis = app(ShopAnalytics::class)->for($shop, AnalyticsPeriod::Last30Days)['kpis'];

        $this->assertSame(150.0, $kpis['revenue']['value']);
        $this->assertSame(2.0, $kpis['orders']['value']);
        $this->assertSame(75.0, $kpis['aov']['value']);
        $this->assertSame(2.0, $kpis['customers']['value']);

        // Δ obrotu: (150 - 200) / 200 = -25%.
        $this->assertSame(-25.0, $kpis['revenue']['delta']);
    }

    public function test_delta_is_null_without_previous_baseline(): void
    {
        [, $shop] = $this->sellerWithShop();
        $this->sale($shop, 100.0, 'a@example.com', now()->subDays(1)->toDateTimeString());

        $kpis = app(ShopAnalytics::class)->for($shop, AnalyticsPeriod::Last30Days)['kpis'];

        // Brak zamówień w poprzednim oknie → brak bazy → delta null (UI pokaże „—").
        $this->assertNull($kpis['revenue']['delta']);
    }

    public function test_data_is_scoped_to_shop(): void
    {
        [, $shop] = $this->sellerWithShop();
        $other = Shop::factory()->create();

        $this->sale($shop, 100.0, 'a@example.com', now()->subDays(1)->toDateTimeString());
        $this->sale($other, 500.0, 'x@example.com', now()->subDays(1)->toDateTimeString());

        $kpis = app(ShopAnalytics::class)->for($shop, AnalyticsPeriod::Last30Days)['kpis'];

        $this->assertSame(100.0, $kpis['revenue']['value']);
        $this->assertSame(1.0, $kpis['orders']['value']);
    }

    public function test_sparkline_has_one_point_per_bucket(): void
    {
        [, $shop] = $this->sellerWithShop();
        $this->sale($shop, 100.0, 'a@example.com', now()->subDays(1)->toDateTimeString());

        $kpis = app(ShopAnalytics::class)->for($shop, AnalyticsPeriod::Last12Months)['kpis'];

        // 12 miesięcy = 13 krawędzi kubełków miesięcznych (od–do włącznie).
        $this->assertGreaterThanOrEqual(12, count($kpis['revenue']['spark']));
        $this->assertLessThanOrEqual(13, count($kpis['revenue']['spark']));
    }

    public function test_seller_can_view_analytics_page(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $this->sale($shop, 120.0, 'a@example.com', now()->subDays(2)->toDateTimeString());

        $this->actingAs($seller)
            ->get(route('seller.analytics.index'))
            ->assertOk()
            ->assertSee('Analityka')
            ->assertSee('Obrót')
            ->assertSee('Zamówienia')
            ->assertSee('Średni koszyk')
            ->assertSee('Klienci');
    }

    public function test_period_can_be_switched_via_query(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->get(route('seller.analytics.index', ['okres' => '12m']))
            ->assertOk()
            ->assertSee('Ostatnie 12 miesięcy');
    }

    public function test_invalid_period_falls_back_to_default(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->get(route('seller.analytics.index', ['okres' => 'nonsense']))
            ->assertOk()
            ->assertSee('Ostatnie 30 dni');
    }
}
