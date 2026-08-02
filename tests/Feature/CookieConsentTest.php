<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zgoda na ciasteczka analityczne.
 *
 * NAJWAŻNIEJSZY TEST W TYM PLIKU to ten, który sprawdza, że bez zgody skrypt
 * Google NIE POJAWIA SIĘ w wysłanym HTML-u. Baner, który się wyświetla, a skrypt
 * i tak leci, jest gorszy niż brak banera: dowodzi, że wiemy o obowiązku, i go
 * nie spełniamy. Zgoda musi być UPRZEDNIA, więc blokada jest serwerowa.
 *
 * Decyzja żyje wyłącznie w ciasteczku — świadomie nie zapisujemy jej w bazie,
 * bo ruch jest w większości anonimowy.
 */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIE = 'cookie_consent';

    private function withAnalytics(): void
    {
        config()->set('services.google.analytics_id', 'G-TESTOWE123');
    }

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    public function test_analytics_is_absent_from_the_page_until_consent_is_given(): void
    {
        $this->withAnalytics();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('googletagmanager', escape: false);
    }

    public function test_analytics_loads_once_consent_is_given(): void
    {
        $this->withAnalytics();

        $this->withUnencryptedCookie(self::COOKIE, 'granted')
            ->get('/')
            ->assertOk()
            ->assertSee('googletagmanager.com/gtag/js', escape: false);
    }

    public function test_refusal_keeps_analytics_out(): void
    {
        $this->withAnalytics();

        $this->withUnencryptedCookie(self::COOKIE, 'declined')
            ->get('/')
            ->assertOk()
            ->assertDontSee('googletagmanager', escape: false);
    }

    public function test_banner_shows_only_until_a_decision_is_made(): void
    {
        $this->get('/')->assertSee('Tylko niezbędne');

        $this->withUnencryptedCookie(self::COOKIE, 'granted')->get('/')->assertDontSee('Tylko niezbędne');
        $this->withUnencryptedCookie(self::COOKIE, 'declined')->get('/')->assertDontSee('Tylko niezbędne');
    }

    public function test_both_answers_are_one_click_away(): void
    {
        // Odmowa musi być równie łatwa jak zgoda — baner z odmową schowaną
        // głębiej jest wadliwy. Oba przyciski stoją w tym samym formularzu.
        $this->get('/')
            ->assertSee('name="decision" value="decline"', escape: false)
            ->assertSee('name="decision" value="accept"', escape: false);
    }

    public function test_accepting_stores_the_decision(): void
    {
        $this->from('/')
            ->post(route('cookies.store'), ['decision' => 'accept'])
            ->assertRedirect('/')
            ->assertCookie(self::COOKIE, 'granted', false);
    }

    public function test_declining_stores_the_decision(): void
    {
        $this->from('/')
            ->post(route('cookies.store'), ['decision' => 'decline'])
            ->assertRedirect('/')
            ->assertCookie(self::COOKIE, 'declined', false);
    }

    public function test_every_public_page_offers_a_way_to_change_the_decision(): void
    {
        // Wycofanie zgody musi być równie łatwe jak jej udzielenie. Link powstał
        // najpierw TYLKO na storefroncie — centrala wypadła z zakresu, dokładnie
        // tak jak przy grafice OG i statystykach. Ten test pilnuje, żeby nie
        // wróciło: sprawdza landing, dokument prawny ORAZ ekran logowania.
        foreach (['/', '/polityka-prywatnosci', route('login')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('value="reset"', escape: false);
        }
    }

    public function test_panel_lets_you_change_the_decision_without_leaving_it(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);

        // Bez decyzji panel PYTA — zgoda dotyczy całej domeny, nie pojedynczej
        // strony, więc ta sama decyzja rządzi landingiem i dokumentami. To był
        // sedno usterki: sprzedawca cofał zgodę i zostawał bez sposobu, żeby
        // podjąć ją na nowo, bo baner pojawiał się tylko poza panelem.
        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertSee('Tylko niezbędne')
            ->assertSee('value="reset"', escape: false);

        // Po decyzji baner ustępuje — zostaje sam wpis w menu.
        $this->withUnencryptedCookie(self::COOKIE, 'granted')
            ->actingAs($seller)->get(route('seller.dashboard'))
            ->assertDontSee('Tylko niezbędne')
            ->assertSee('value="reset"', escape: false);
    }

    public function test_cookies_entry_sits_in_both_panel_menus(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);

        $html = $this->withUnencryptedCookie(self::COOKIE, 'granted')
            ->actingAs($seller)->get(route('seller.dashboard'))->getContent();

        // Panel ma DWA menu — boczne i wysuwane mobilne — renderowane tym samym
        // komponentem. Wpis musi być w obu, inaczej na telefonie znika.
        $this->assertSame(2, substr_count($html, 'value="reset"'));
    }

    public function test_every_decision_is_confirmed_on_screen(): void
    {
        // Bez potwierdzenia klik w link ze stopki wygląda jak zepsuty przycisk:
        // strona wraca ta sama, a w panelu nie ma banera, który pokazałby efekt.
        foreach (['accept', 'decline', 'reset'] as $decision) {
            $this->from('/')
                ->post(route('cookies.store'), ['decision' => $decision])
                ->assertSessionHas('success');
        }
    }

    public function test_decision_can_be_withdrawn(): void
    {
        // Wycofanie zgody musi być możliwe — kasujemy ciasteczko, więc baner
        // pojawia się ponownie i użytkownik wybiera od nowa.
        $this->withUnencryptedCookie(self::COOKIE, 'granted')
            ->from('/')
            ->post(route('cookies.store'), ['decision' => 'reset'])
            ->assertRedirect('/')
            ->assertCookieExpired(self::COOKIE);
    }

    public function test_consent_cookie_is_not_encrypted(): void
    {
        // Wartość musi być czytelna poza cyklem żądania (skrypt w przeglądarce,
        // diagnostyka). Gdyby ktoś usunął wyjątek w bootstrap/app.php,
        // zaszyfrowane ciasteczko byłoby cicho traktowane jak brak decyzji —
        // baner wracałby przy każdej wizycie mimo wyrażonej zgody.
        $this->withAnalytics();

        $this->withUnencryptedCookie(self::COOKIE, 'granted')
            ->get('/')
            ->assertSee('googletagmanager.com/gtag/js', escape: false);
    }

    public function test_cookie_name_matches_the_encryption_exception(): void
    {
        // Nazwa w bootstrap/app.php jest wpisana wprost (config() jeszcze tam
        // nie działa), więc musi zgadzać się z konfiguracją.
        $this->assertSame(self::COOKIE, config('cookies.consent.name'));
    }

    // -------------------------------------------------------------- storefront

    public function test_shop_asks_even_without_analytics_configured(): void
    {
        $shop = Shop::factory()->active()->create();

        // Pytamy ZAWSZE, nie tylko gdy pomiar jest włączony. Zgoda zebrana z
        // zapasem nic nie kosztuje, a jej brak w dniu, w którym dojdzie piksel
        // czy mapa, oznaczałby przerabianie mechanizmu od nowa u wszystkich.
        $this->get($this->host($shop).'/')->assertSee('Tylko niezbędne');
    }

    public function test_shop_with_analytics_asks_in_its_own_colours(): void
    {
        // Pomiar wymaga trzech rzeczy naraz: uprawnienia z pakietu, włączonej
        // integracji i identyfikatora. Sklep musi być też aktywny, inaczej
        // storefront pokazuje ekran „już wkrótce" bez stopki i banera.
        $shop = Shop::factory()->active()->withGaAnalytics()->create(['name' => 'Domowe Lemoniady']);
        $shop->integrations()->create([
            'type' => IntegrationType::GoogleAnalytics,
            'enabled' => true,
            'config' => ['tracking_id' => 'G-SKLEP123'],
        ]);

        $response = $this->get($this->host($shop->fresh()).'/');

        $response->assertSee('Tylko niezbędne');
        // Zgodę zbieramy w imieniu SPRZEDAWCY — to on jest administratorem
        // danych na swojej subdomenie.
        $response->assertSee('Sklep Domowe Lemoniady', escape: false);
        // Kolory z palety motywu, nie zaszyte w komponencie: przycisk sięga po
        // zmienną, którą storefront wstrzykuje z motywu sklepu.
        $response->assertSee('var(--brand', escape: false);
    }

    public function test_consent_given_in_one_shop_does_not_apply_to_another(): void
    {
        // Na każdym storefroncie administratorem danych jest INNY sprzedawca,
        // więc zgoda nie może się przenosić. Broni tego brak SESSION_DOMAIN —
        // ciasteczka wiążą się z konkretnym hostem.
        $this->assertNull(config('session.domain'));
    }
}
