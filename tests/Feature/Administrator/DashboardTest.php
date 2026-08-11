<?php

namespace Tests\Feature\Administrator;

use App\Models\PackagePayment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pulpit admina: realne liczby i lista najnowszych sklepów (zamiast atrap „0").
 *
 * Kafelki pieniędzy czytają z tego samego źródła co dział „Pakiety"
 * (`PackageRevenue`) — do 2026-08-11 stały tu zera wpisane na sztywno, mimo że
 * rejestr opłat istniał. Testy pilnują, żeby liczba na pulpicie i liczba w
 * Pakietach nigdy się nie rozjechały.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_recent_shops(): void
    {
        $admin = User::factory()->admin()->create();
        Shop::factory()->create(['name' => 'Kwiaciarnia Zosia']);

        $this->actingAs($admin)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('Sprzedane abonamenty')
            ->assertSee('Przychód — sumarycznie')
            ->assertSee('Najnowsze sklepy')
            ->assertSee('Kwiaciarnia Zosia')
            ->assertDontSee('Nie ma jeszcze żadnych sklepów');
    }

    public function test_revenue_tiles_show_real_money(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create();
        PackagePayment::factory()->for($shop)->create(['amount' => 750]);
        PackagePayment::factory()->for($shop)->create(['amount' => 1500]);

        $this->actingAs($admin)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('2 250,00 zł')          // suma obu wpłat
            ->assertSee('Od początku platformy')
            // Stary podpis atrapy — jego powrót znaczyłby, że kafelek znowu kłamie.
            ->assertDontSee('Po uruchomieniu płatności za SaaS');
    }

    public function test_pending_payment_does_not_inflate_the_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create();
        PackagePayment::factory()->pending()->for($shop)->create(['amount' => 1500]);

        $this->actingAs($admin)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('0,00 zł');
    }

    public function test_attention_banner_appears_only_when_there_is_something_to_do(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertDontSee('wymaga uwagi');

        Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->addDays(10)]);

        $this->actingAs($admin)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('wymaga uwagi')
            ->assertSee('Kończące się abonamenty');
    }

    public function test_recent_shops_link_to_their_storefronts_in_a_new_tab(): void
    {
        // Ten sam podgląd co na pełnej liście sklepów — adres z `host()`, więc
        // sklep z własną domeną prowadzi tam, a nie na subdomenę centrali.
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create(['slug' => 'lemoniady']);

        $this->actingAs($admin)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('Zobacz')
            ->assertSee('href="https://'.$shop->host().'"', false)
            ->assertSee('target="_blank"', false);
    }

    public function test_dashboard_shows_empty_state_without_shops(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('Nie ma jeszcze żadnych sklepów');
    }
}
