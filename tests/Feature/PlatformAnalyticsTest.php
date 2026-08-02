<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Statystyki Google dla CENTRALI (kramio.pl).
 *
 * Sklepy sprzedawców miały własny pomiar od dawna, sama platforma nie — ten sam
 * rodzaj luki co z grafiką do social mediów: praca nad storefrontem nie objęła
 * stron centrali, bo nikt ich osobno nie sprawdził.
 *
 * Testy pilnują trzech rzeczy: że pomiar wchodzi na strony publiczne, że NIE
 * wchodzi do panelu (decyzja świadoma, nie zapomniana) i że brak identyfikatora
 * zwyczajnie go wyłącza, zamiast zostawiać w źródle pusty skrypt.
 */
class PlatformAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'G-TESTOWE123';

    private function withAnalytics(): void
    {
        config()->set('services.google.analytics_id', self::ID);
    }

    /**
     * Żądanie z wyrażoną zgodą na ciasteczka.
     *
     * Od 02.08.2026 sam skonfigurowany identyfikator NIE WYSTARCZA — skrypt
     * pomiaru pojawia się w stronie dopiero po zgodzie użytkownika, a blokada
     * jest serwerowa. Testy sprawdzające obecność pomiaru muszą więc tę zgodę
     * wyrazić; brak zgody ma własne testy w CookieConsentTest.
     */
    private function consenting(): static
    {
        return $this->withUnencryptedCookie((string) config('cookies.consent.name'), 'granted');
    }

    public function test_landing_page_loads_analytics(): void
    {
        $this->withAnalytics();

        $this->consenting()->get('/')
            ->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id='.self::ID, escape: false);
    }

    public function test_login_page_loads_analytics(): void
    {
        $this->withAnalytics();

        $this->consenting()->get(route('login'))
            ->assertOk()
            ->assertSee(self::ID, escape: false);
    }

    public function test_legal_page_loads_analytics(): void
    {
        $this->withAnalytics();

        $this->consenting()->get('/polityka-prywatnosci')
            ->assertOk()
            ->assertSee(self::ID, escape: false);
    }

    public function test_panel_does_not_load_analytics(): void
    {
        $this->withAnalytics();

        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);

        // Panel to narzędzie pracy za logowaniem: adresy podstron niosą numery
        // zamówień i dane klientów sprzedawcy. Gdyby ktoś kiedyś wpiął pomiar
        // do layoutu panelu, ten test ma o tym powiedzieć.
        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertDontSee('googletagmanager.com', escape: false);
    }

    public function test_missing_id_disables_the_measurement_entirely(): void
    {
        config()->set('services.google.analytics_id', null);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('googletagmanager.com', escape: false);
    }

    public function test_tag_manager_identifier_loads_the_other_script(): void
    {
        // Sprzedawca wkleja to, co dostał, i nie musi wiedzieć, czy ma GA4 czy
        // Tag Managera — komponent rozpoznaje to sam. Ta sama logika obsługuje
        // centralę, więc pilnujemy jej tutaj.
        config()->set('services.google.analytics_id', 'GTM-TESTOWE');

        $this->consenting()->get('/')
            ->assertOk()
            ->assertSee('googletagmanager.com/gtm.js', escape: false);
    }
}
