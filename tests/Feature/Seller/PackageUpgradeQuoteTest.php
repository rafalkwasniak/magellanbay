<?php

namespace Tests\Feature\Seller;

use App\Models\Shop;
use App\Models\User;
use App\Support\PackageUpgrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Wycena zmiany pakietu: klient kupuje PEŁNY ROK nowego pakietu, a resztówka
 * obecnego wraca mu jako ZNIŻKA. Termin liczy się od nowa — dzięki temu
 * przejście wyżej po pół roku daje kolejny pełny rok współpracy, a nie
 * dokończenie starego okresu.
 *
 *     zniżka     = cena obecnego × dni do końca ÷ 365
 *     do zapłaty = cena nowego (rok) − zniżka, w dół do pełnych złotych
 */
class PackageUpgradeQuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Stała data — wycena zależy od liczby dni, więc test nie może dryfować.
        Carbon::setTestNow(Carbon::parse('2026-07-29 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function shopOn(string $package, array $attributes = []): Shop
    {
        return Shop::factory()->withInvoiceData()->create([
            'package' => $package,
            'entitlements' => config("shop.packages.{$package}.entitlements"),
            'price_yearly' => config("shop.packages.{$package}.price_yearly"),
            ...$attributes,
        ]);
    }

    public function test_upgrade_buys_a_full_year_minus_the_unused_part(): void
    {
        // Stragan (750) opłacony jeszcze na 63 dni → Pawilon (1500).
        $shop = $this->shopOn('booth', ['subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $quote = PackageUpgrade::quote($shop, 'pavilion');

        $this->assertSame('credit', $quote['kind']);
        $this->assertSame(63, $quote['days_left']);
        // zniżka: 750 × 63/365 = 129,45 → do zapłaty 1500 − 129,45 = 1370,55 → 1370
        $this->assertSame(129.45, $quote['credit']);
        $this->assertSame(1370.0, $quote['amount']);
        // Termin rusza od nowa — to sedno tej reguły.
        $this->assertSame('2027-07-29', $quote['new_ends_at']->format('Y-m-d'));
    }

    public function test_half_year_left_gives_half_the_price_as_discount(): void
    {
        // Pół roku Stragana zostało → zniżka ~połowa jego ceny rocznej.
        $shop = $this->shopOn('booth', ['subscription_ends_at' => Carbon::parse('2027-01-29')]);

        $quote = PackageUpgrade::quote($shop, 'pavilion');

        $this->assertSame(184, $quote['days_left']);
        $this->assertSame(378.08, $quote['credit']);          // 750 × 184/365
        $this->assertSame(1121.0, $quote['amount']);          // 1500 − 378,08 → 1121
        $this->assertSame('2027-07-29', $quote['new_ends_at']->format('Y-m-d'));
    }

    public function test_the_earlier_you_switch_the_bigger_the_discount(): void
    {
        $early = $this->shopOn('booth', ['subscription_ends_at' => Carbon::parse('2027-07-29')]);
        $late = $this->shopOn('booth', ['subscription_ends_at' => Carbon::parse('2026-08-05')]);

        // Prawie cały rok niewykorzystany → prawie cała cena Stragana w zniżce.
        $this->assertSame(750.0, PackageUpgrade::quote($early, 'pavilion')['amount']);
        // Tydzień do końca → zniżka groszowa, płaci niemal pełny rok.
        $this->assertSame(1485.0, PackageUpgrade::quote($late, 'pavilion')['amount']);
    }

    public function test_amount_is_rounded_down_in_the_clients_favour(): void
    {
        $shop = $this->shopOn('booth', ['subscription_ends_at' => Carbon::parse('2026-09-30')]);

        // 1370,55 zł nie może zamienić się w 1371 zł.
        $this->assertSame(1370.0, PackageUpgrade::quote($shop, 'pavilion')['amount']);
    }

    public function test_from_the_free_package_you_buy_a_full_year(): void
    {
        // Kram nie ma opłaconego okresu, więc nie ma czego kredytować.
        $quote = PackageUpgrade::quote($this->shopOn('stall'), 'booth');

        $this->assertSame('full', $quote['kind']);
        $this->assertSame(750.0, $quote['amount']);
        $this->assertSame(0.0, $quote['credit']);
        $this->assertSame('2027-07-29', $quote['new_ends_at']->format('Y-m-d'));
    }

    public function test_expired_subscription_gets_no_discount(): void
    {
        $shop = $this->shopOn('booth', ['subscription_ends_at' => Carbon::parse('2026-07-01')]);

        $quote = PackageUpgrade::quote($shop, 'pavilion');

        $this->assertSame('full', $quote['kind']);
        $this->assertSame(1500.0, $quote['amount']);
    }

    public function test_comped_shop_is_not_priced_with_a_discount(): void
    {
        $shop = $this->shopOn('booth', ['comped' => true, 'subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $this->assertSame('full', PackageUpgrade::quote($shop, 'pavilion')['kind']);
    }

    public function test_downgrade_costs_nothing_now(): void
    {
        $shop = $this->shopOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $quote = PackageUpgrade::quote($shop, 'booth');

        // Obniżka wchodzi przy odnowieniu; do tego czasu działa opłacony Pawilon.
        $this->assertSame('downgrade', $quote['kind']);
        $this->assertSame(0.0, $quote['amount']);
    }

    public function test_top_package_has_no_upgrade_quotes(): void
    {
        $shop = $this->shopOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $this->assertSame([], PackageUpgrade::upgradeQuotes($shop));
    }

    public function test_free_shop_gets_quotes_for_both_paid_packages(): void
    {
        $quotes = PackageUpgrade::upgradeQuotes($this->shopOn('stall'));

        $this->assertSame(['booth', 'pavilion'], array_keys($quotes));
        $this->assertSame(750.0, $quotes['booth']['amount']);
        $this->assertSame(1500.0, $quotes['pavilion']['amount']);
    }

    public function test_the_displayed_discount_closes_the_arithmetic(): void
    {
        // Rafał wyłapał: 16,44 zł zniżki obok 733 zł do zapłaty nie daje się
        // zsumować w głowie (750 − 16,44 = 733,56). Pokazujemy RÓŻNICĘ kwot, więc
        // rachunek zawsze się domyka — także przy przejściu wyżej.
        $down = PackageUpgrade::quote(
            $this->shopOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-08-02')]),
            'booth',
        );

        $this->assertSame(733.0, $down['amount']);
        $this->assertSame(16.44, $down['credit'], 'surowa proporcja zostaje w danych');
        $this->assertSame(17.0, PackageUpgrade::discountShown($down));

        $up = PackageUpgrade::quote(
            $this->shopOn('booth', ['subscription_ends_at' => Carbon::parse('2026-09-30')]),
            'pavilion',
        );

        $this->assertSame(1370.0, $up['amount']);
        $this->assertSame(130.0, PackageUpgrade::discountShown($up));
    }

    public function test_downsize_is_refused_outside_the_renewal_window(): void
    {
        // Sedno zabezpieczenia: kto kupił Pawilon, nie zejdzie nazajutrz na
        // Stragan po zniżce równej niemal całej wpłacie.
        $shop = $this->shopOn('pavilion', ['subscription_ends_at' => Carbon::parse('2027-07-28')]);

        $quote = PackageUpgrade::quote($shop, 'booth');

        $this->assertSame('downgrade', $quote['kind']);
        $this->assertSame(0.0, $quote['amount']);
    }

    public function test_downsize_in_the_window_costs_the_lower_price_minus_the_leftover(): void
    {
        // 30 dni do końca Pawilonu → Stragan. Zniżka: 1500 × 30/365 = 123,29,
        // do zapłaty: 750 − 123,29 = 626,71 → 626 (zaokrąglone na korzyść klienta).
        $shop = $this->shopOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-08-28')]);

        $quote = PackageUpgrade::quote($shop, 'booth');

        $this->assertSame('downsize', $quote['kind']);
        $this->assertSame(30, $quote['days_left']);
        $this->assertSame(123.29, $quote['credit']);
        $this->assertSame(626.0, $quote['amount']);
        // Resztówka się zużyła, więc rok liczy się od dziś — inaczej niż przy
        // przedłużeniu tego samego pakietu.
        $this->assertSame('2027-07-29', $quote['new_ends_at']->format('Y-m-d'));
    }

    public function test_the_window_boundary_is_exactly_thirty_days(): void
    {
        $inside = $this->shopOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-08-28')]);
        $outside = $this->shopOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-08-29')]);

        $this->assertSame('downsize', PackageUpgrade::quote($inside, 'booth')['kind']);
        $this->assertSame('downgrade', PackageUpgrade::quote($outside, 'booth')['kind']);
    }

    public function test_expired_shop_pays_the_full_lower_price_with_no_discount(): void
    {
        $shop = $this->shopOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-01-29')]);

        $quote = PackageUpgrade::quote($shop, 'booth');

        $this->assertSame('downsize', $quote['kind']);
        $this->assertSame(0.0, $quote['credit']);
        $this->assertSame(750.0, $quote['amount']);
    }

    public function test_a_discount_can_never_swallow_the_whole_amount(): void
    {
        // Twarda blokada obok okna: cennik takiego przypadku nie zna, ale cena
        // indywidualna z konsoli admina nie zna cennika. Zwrot gotówki nie ma się
        // wydarzyć NIGDY, nie tylko „przy typowych cenach".
        $shop = $this->shopOn('pavilion', [
            'price_yearly' => 20000,
            'subscription_ends_at' => Carbon::parse('2026-08-28'),
        ]);

        $this->assertSame('unavailable', PackageUpgrade::quote($shop, 'booth')['kind']);
    }

    public function test_the_free_package_cannot_be_bought_as_a_downsize(): void
    {
        // Zejście na Kram dzieje się samo przez wygaśnięcie — nie ma czego kupować.
        $shop = $this->shopOn('booth', ['subscription_ends_at' => Carbon::parse('2026-08-01')]);

        $this->assertSame('unavailable', PackageUpgrade::quote($shop, 'stall')['kind']);
        $this->assertSame([], PackageUpgrade::downsizeQuotes($shop));
    }

    public function test_renewal_glues_a_year_to_the_existing_term(): void
    {
        // Płacę 30 dni przed końcem → mam opłacone przez 13 miesięcy, nie tracę
        // tych 30 dni (decyzja Rafała). Pełna cena, bez zniżek.
        $shop = $this->shopOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-08-28')]);

        $quote = PackageUpgrade::renewal($shop);

        $this->assertSame('renewal', $quote['kind']);
        $this->assertSame(1500.0, $quote['amount']);
        $this->assertSame(0.0, $quote['credit']);
        $this->assertSame('2027-08-28', $quote['new_ends_at']->format('Y-m-d'));
    }

    public function test_paying_late_in_grace_shortens_the_year_by_those_days(): void
    {
        // 3 dni po terminie, wciąż w karencji: rok liczy się od TERMINU, więc
        // wychodzi o te 3 dni krócej. Spóźnienie nie jest premiowane.
        $shop = $this->shopOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-07-26')]);

        $quote = PackageUpgrade::renewal($shop);

        $this->assertSame('renewal', $quote['kind']);
        $this->assertSame(1500.0, $quote['amount']);
        $this->assertSame('2027-07-26', $quote['new_ends_at']->format('Y-m-d'));
    }

    public function test_renewal_after_the_subscription_lapsed_starts_a_fresh_year(): void
    {
        // Pół roku po wygaśnięciu doklejanie do starej daty sprzedałoby pół roku
        // dostępu za cenę roku — dlatego liczymy od dziś.
        $shop = $this->shopOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-01-29')]);

        $quote = PackageUpgrade::renewal($shop);

        $this->assertSame('2027-07-29', $quote['new_ends_at']->format('Y-m-d'));
    }

    public function test_renewal_charges_the_shops_own_price_not_the_list_one(): void
    {
        // Cena indywidualna: przedłużenie idzie na warunkach TEGO sklepu.
        $shop = $this->shopOn('pavilion', [
            'price_yearly' => 900,
            'subscription_ends_at' => Carbon::parse('2026-08-28'),
        ]);

        $this->assertSame(900.0, PackageUpgrade::renewal($shop)['amount']);
    }

    public function test_free_and_comped_shops_have_nothing_to_renew(): void
    {
        $this->assertSame('unavailable', PackageUpgrade::renewal($this->shopOn('stall'))['kind']);

        $comped = $this->shopOn('pavilion', ['comped' => true, 'subscription_ends_at' => Carbon::parse('2026-08-28')]);
        $this->assertSame('unavailable', PackageUpgrade::renewal($comped)['kind']);
    }

    public function test_buying_the_same_package_is_treated_as_a_renewal(): void
    {
        // Ścieżka zakupu woła quote() z nazwą pakietu — ten sam pakiet nie może
        // wpaść w „tańszy pakiet" i wrócić z zerową kwotą.
        $shop = $this->shopOn('booth', ['subscription_ends_at' => Carbon::parse('2026-08-28')]);

        $quote = PackageUpgrade::quote($shop, 'booth');

        $this->assertSame('renewal', $quote['kind']);
        $this->assertSame(750.0, $quote['amount']);
        $this->assertSame('2027-08-28', $quote['new_ends_at']->format('Y-m-d'));
    }

    public function test_screen_shows_the_amount_discount_and_new_term(): void
    {
        $seller = User::factory()->consented()->create();
        $this->shopOn('booth', ['owner_id' => $seller->id, 'subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Zmiana pakietu')
            ->assertSee('1 370,00')                     // do zapłaty
            // Zniżka POKAZANA jako różnica (1500 − 1370), żeby rachunek się
            // domykał; surowe 129,45 zostaje w danych, nie na ekranie.
            ->assertSee('130,00')
            ->assertDontSee('129,45')
            ->assertSee('29.07.2027')                   // nowy termin (rok od dziś)
            ->assertSee('odejmujemy jako zniżkę');
    }

    public function test_top_package_screen_explains_downgrade_instead(): void
    {
        $seller = User::factory()->consented()->create();
        $this->shopOn('pavilion', ['owner_id' => $seller->id, 'subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Masz najwyższy pakiet')
            // Poza oknem odnowienia ekran mówi OD KIEDY zejście będzie możliwe,
            // zamiast odsyłać do kontaktu.
            ->assertSee('w ostatnich 30 dniach abonamentu')
            ->assertSee('od 31.08.2026')
            // Przedłużenie w TYM SAMYM pakiecie jest dostępne zawsze — ograniczone
            // do okna jest tylko zejście niżej.
            ->assertSee('Przedłuż o rok — 1 500,00 zł');
    }
}
