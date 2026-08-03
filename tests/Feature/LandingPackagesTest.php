<?php

namespace Tests\Feature;

use App\Support\PackageFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cennik na stronie głównej: karta pakietu wyróżnia to, co DOCHODZI względem
 * pakietu niżej, żeby kupujący nie musiał porównywać trzech kolumn linijka po
 * linijce. Lista bierze się z `config('shop.packages')`, więc nie może
 * rozjechać się z tym, co sklep faktycznie dostaje.
 */
class LandingPackagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array<string, bool>>  pakiet => [etykieta => czy nowa]
     */
    private function featureMap(): array
    {
        $map = [];

        foreach (PackageFeatures::landing() as $package) {
            foreach ($package['features'] as $feature) {
                $map[$package['name']][$feature['label']] = $feature['is_new'];
            }
        }

        return $map;
    }

    public function test_cheapest_package_highlights_nothing(): void
    {
        // Nie ma się do czego porównywać — inaczej cała karta byłaby pogrubiona.
        $this->assertNotContains(true, $this->featureMap()['Kram']);
    }

    public function test_middle_package_highlights_what_the_free_one_lacks(): void
    {
        $stragan = $this->featureMap()['Stragan'];

        $this->assertTrue($stragan['Integracja płatności online (własne konto Paynow)']);
        $this->assertTrue($stragan['Wysyłka kurierem i przez InPost']);
        $this->assertTrue($stragan['Integracja z Fakturownią']);
        // Wyższy limit produktów to też różnica, choć cecha ta sama.
        $this->assertTrue($stragan['Do 48 produktów']);

        // Wspólne z Kramem — bez wyróżnienia.
        $this->assertFalse($stragan['Własny adres i strona sklepu']);
        $this->assertFalse($stragan['Zwroty 14 dni zgodne z prawem']);
    }

    public function test_top_package_highlights_only_what_the_middle_one_lacks(): void
    {
        $pawilon = $this->featureMap()['Pawilon'];

        $this->assertTrue($pawilon['Edycja zamówień']);
        $this->assertTrue($pawilon['Kody rabatowe w koszyku']);
        $this->assertTrue($pawilon['Wiadomości do klientów']);
        $this->assertTrue($pawilon['Do 96 produktów']);

        // Ma je już Stragan, więc w Pawilonie to nie jest nowość.
        $this->assertFalse($pawilon['Integracja płatności online (własne konto Paynow)']);
        $this->assertFalse($pawilon['Integracja z Fakturownią']);
    }

    public function test_landing_shows_shipped_features_without_a_coming_soon_badge(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Kody rabatowe w koszyku')
            ->assertSee('Wiadomości do klientów')
            ->assertSee('Zwroty 14 dni zgodne z prawem')
            // Oba moduły są na produkcji — plakietka wprowadzałaby w błąd.
            ->assertDontSee('wkrótce');
    }

    public function test_landing_explains_how_to_buy_a_package(): void
    {
        // Ceny same nie mówią, CO ZROBIĆ — ktoś, kto chce od razu Pawilon, musi
        // wiedzieć, że droga wiedzie przez darmowe konto.
        $this->get('/')
            ->assertOk()
            ->assertSee('Jak kupić pakiet?')
            ->assertSee('Załóż darmowe konto')
            // Bez cudzysłowu w asercji — Blade escapuje go na `&quot;`.
            ->assertSee('Wejdź w')
            ->assertSee('Załóż konto i wybierz pakiet')
            // Reguła zmiany pakietu wyłożona wprost, nie jako haczyk.
            ->assertSee('niewykorzystany okres wraca jako zniżka');
    }

    public function test_each_package_card_has_its_own_call_to_action(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Zacznij za darmo')       // Kram
            ->assertSee('Wybierz Stragan')
            ->assertSee('Wybierz Pawilon');
    }

    public function test_price_shows_twelve_months_struck_through_next_to_the_yearly_one(): void
    {
        // Zysk ma być widoczny bez liczenia: obok ceny rocznej stoi przekreślona
        // cena 12 miesięcy (stawka miesięczna × 12). Obie kwoty liczą się z
        // `shop.billing`, więc zmiana reguły przelicza cennik, a nie rozjeżdża go.
        $paid = (int) config('shop.billing.months_paid');
        $total = (int) config('shop.billing.months_total');

        $html = $this->get('/')->assertOk()->getContent();

        foreach (config('shop.packages') as $package) {
            $yearly = (int) $package['price_yearly'];

            if ($yearly === 0) {
                continue;   // Kram jest darmowy — nie ma czego przekreślać
            }

            $monthly = intdiv($yearly, $paid);

            $this->assertStringContainsString('<s class="text-xl text-stone-400">'.($monthly * $total).' zł</s>', $html);
            $this->assertStringContainsString($yearly.' zł', $html);
        }

        $this->assertStringContainsString(($total - $paid).' miesiące za darmo', $html);
        // Stary opis („płacisz za 10 miesięcy, 2 gratis") zastąpiony przekreśleniem.
        $this->assertStringNotContainsString('2 gratis', $html);
    }

    public function test_features_do_not_promise_what_we_do_not_have(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Sitemapy ani danych strukturalnych produktu NIE mamy — kafelek SEO
        // obiecywał je wcześniej, mimo audytu, który je świadomie odłożył.
        $this->assertStringNotContainsString('mapy strony', $html);
        $this->assertStringNotContainsString('dane produktów dla wyszukiwarek', $html);

        // …a to, co mamy, jest wymienione po imieniu, nie jako „kolejne metody".
        $this->assertStringNotContainsString('i kolejne metody', $html);
        $this->assertStringContainsString('BLIK', $html);
        $this->assertStringContainsString('Paczkomat', $html);
        $this->assertStringContainsString('Zwroty zgodne z prawem', $html);
    }

    public function test_returns_are_listed_in_every_package(): void
    {
        foreach ($this->featureMap() as $package => $features) {
            $this->assertArrayHasKey(
                'Zwroty 14 dni zgodne z prawem',
                $features,
                "Pakiet {$package} musi mieć obsługę zwrotów — prawo odstąpienia nie zależy od opłaty.",
            );
        }
    }
}
