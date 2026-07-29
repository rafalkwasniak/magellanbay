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
        return Shop::factory()->create([
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

    public function test_screen_shows_the_amount_discount_and_new_term(): void
    {
        $seller = User::factory()->consented()->create();
        $this->shopOn('booth', ['owner_id' => $seller->id, 'subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Zmiana pakietu')
            ->assertSee('1 370,00')                     // do zapłaty
            ->assertSee('129,45')                       // zniżka za resztówkę
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
            ->assertSee('obniżka wejdzie przy odnowieniu');
    }
}
