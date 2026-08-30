<?php

namespace Tests\Feature\Administrator;

use App\Enums\AnalyticsPeriod;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopAnalytics;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Analityka administratora: ten sam ekran co u sprzedawcy, liczony dla całej
 * platformy. Testy pilnują trzech rzeczy, które łatwo cicho zepsuć: że suma
 * naprawdę obejmuje wszystkie sklepy (także te w karencji na usunięcie), że filtr
 * `sklep` zawęża do jednego, i że klient liczy się osobno w każdym sklepie —
 * dzięki temu suma platformy zgadza się z sumą pojedynczych sklepów.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function sale(Shop $shop, float $gross, string $email): void
    {
        Order::factory()->for($shop)->create([
            'status' => OrderStatus::Paid,
            'total_gross' => $gross,
            'buyer_email' => $email,
            'created_at' => now()->subDays(2)->toDateTimeString(),
        ]);
    }

    public function test_platform_view_sums_every_shop(): void
    {
        $first = Shop::factory()->create();
        $second = Shop::factory()->create();

        $this->sale($first, 100.0, 'a@example.com');
        $this->sale($second, 250.0, 'b@example.com');

        // Anulowane odpada tak samo jak u sprzedawcy.
        Order::factory()->for($second)->create([
            'status' => OrderStatus::Cancelled,
            'total_gross' => 999.0,
            'created_at' => now()->subDay()->toDateTimeString(),
        ]);

        $kpis = app(ShopAnalytics::class)->for(null, AnalyticsPeriod::Last30Days)['kpis'];

        $this->assertSame(350.0, $kpis['revenue']['value']);
        $this->assertSame(2.0, $kpis['orders']['value']);
    }

    public function test_shop_awaiting_deletion_still_counts_towards_the_sum(): void
    {
        // Sprzedaż się wydarzyła — nie ma znikać z obrotu platformy tylko dlatego,
        // że sprzedawca zlecił usunięcie sklepu.
        $shop = Shop::factory()->create();
        $shop->forceFill(['deletion_scheduled_at' => now()->addDays(5)])->save();

        $this->sale($shop, 120.0, 'a@example.com');

        $kpis = app(ShopAnalytics::class)->for(null, AnalyticsPeriod::Last30Days)['kpis'];

        $this->assertSame(120.0, $kpis['revenue']['value']);
    }

    public function test_the_same_person_is_a_separate_customer_in_each_shop(): void
    {
        $first = Shop::factory()->create();
        $second = Shop::factory()->create();

        $this->sale($first, 100.0, 'ta.sama@example.com');
        $this->sale($second, 100.0, 'ta.sama@example.com');

        $kpis = app(ShopAnalytics::class)->for(null, AnalyticsPeriod::Last30Days)['kpis'];

        $this->assertSame(2.0, $kpis['customers']['value']);
    }

    public function test_screen_shows_the_platform_sum_by_default(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Shop::factory()->create();
        $second = Shop::factory()->create();

        $this->sale($first, 100.0, 'a@example.com');
        $this->sale($second, 250.0, 'b@example.com');

        $this->actingAs($admin)
            ->get(route('administrator.analytics.index'))
            ->assertOk()
            ->assertSee(Money::pln(350.0))
            ->assertSee('Wszystkie sklepy (sumarycznie)');
    }

    public function test_shop_filter_narrows_the_numbers_to_one_shop(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Shop::factory()->create(['name' => 'Kwiaciarnia Zosia']);
        $second = Shop::factory()->create();

        $this->sale($first, 100.0, 'a@example.com');
        $this->sale($second, 250.0, 'b@example.com');

        $this->actingAs($admin)
            ->get(route('administrator.analytics.index', ['sklep' => $first->id]))
            ->assertOk()
            ->assertSee('Kwiaciarnia Zosia')
            ->assertSee(Money::pln(100.0))
            ->assertDontSee(Money::pln(350.0));
    }

    public function test_unknown_shop_falls_back_to_the_platform_view(): void
    {
        // Tak samo jak nieznany okres wraca do wartości domyślnej — ekran ma się
        // nie wywracać na ręcznie podanym adresie.
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create();
        $this->sale($shop, 100.0, 'a@example.com');

        $this->actingAs($admin)
            ->get(route('administrator.analytics.index', ['sklep' => 999999]))
            ->assertOk()
            ->assertSee('Wszystkie sklepy (sumarycznie)');
    }

    public function test_screen_renders_without_any_shops(): void
    {
        // Puste dane muszą przejść przez wykresy i rankingi — stan pusty jest
        // normalnym stanem ekranu, nie wyjątkiem.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('administrator.analytics.index'))
            ->assertOk();
    }

    public function test_seller_cannot_open_administrator_analytics(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);

        $this->actingAs($seller)
            ->get(route('administrator.analytics.index'))
            ->assertForbidden();
    }
}
