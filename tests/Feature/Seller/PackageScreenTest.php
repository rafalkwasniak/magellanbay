<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ekran „Mój pakiet" w panelu sprzedawcy: co mam wykupione, do kiedy, ile
 * zużyłem z limitów i co dostanę w wyższym pakiecie. Dotąd sprzedawca nie
 * widział nawet nazwy swojego pakietu ani terminu jego końca.
 */
class PackageScreenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWith(string $package, array $attributes = []): array
    {
        $seller = User::factory()->consented()->create();

        $shop = Shop::factory()->withInvoiceData()->create([
            'owner_id' => $seller->id,
            'package' => $package,
            'entitlements' => config("shop.packages.{$package}.entitlements"),
            'price_yearly' => config("shop.packages.{$package}.price_yearly"),
            ...$attributes,
        ]);

        return [$seller, $shop];
    }

    public function test_free_shop_sees_its_package_and_upgrade_paths(): void
    {
        [$seller] = $this->sellerWith('stall');

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Kram')
            ->assertSee('Za darmo, bez limitu czasu')
            // Wyżej są dwa pakiety — pokazujemy oba z tym, co dochodzi.
            ->assertSee('Co dostaniesz wyżej')
            ->assertSee('Stragan')
            ->assertSee('Pawilon');
    }

    public function test_paid_shop_sees_the_amount_and_the_due_date(): void
    {
        [$seller] = $this->sellerWith('booth', ['subscription_ends_at' => now()->addMonths(6)]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Stragan')
            ->assertSee('750,00')
            ->assertSee('Opłacony do')
            ->assertSee(now()->addMonths(6)->format('d.m.Y'))
            ->assertSee('Aktywny');
    }

    public function test_top_package_has_nothing_to_upsell(): void
    {
        [$seller] = $this->sellerWith('pavilion', ['subscription_ends_at' => now()->addYear()]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Pawilon')
            ->assertDontSee('Co dostaniesz wyżej');
    }

    public function test_expired_shop_is_told_what_changed_and_that_it_comes_back(): void
    {
        [$seller] = $this->sellerWith('pavilion', ['subscription_ends_at' => now()->subDay()]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Abonament wygasł')
            ->assertSee('działa teraz na zasadach pakietu Kram')
            // Ton bez straszenia: sklep działa, a po opłacie wraca stan sprzed.
            ->assertSee('Sklep i zamówienia działają dalej');
    }

    public function test_expiring_soon_shows_a_reminder(): void
    {
        [$seller] = $this->sellerWith('booth', ['subscription_ends_at' => now()->addDays(9)]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Abonament kończy się za 9 dni');
    }

    public function test_reminder_stays_quiet_when_the_date_is_far_away(): void
    {
        [$seller] = $this->sellerWith('booth', ['subscription_ends_at' => now()->addMonths(6)]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertDontSee('Abonament kończy się');
    }

    public function test_comped_shop_is_marked_as_free_forever(): void
    {
        [$seller] = $this->sellerWith('pavilion', ['comped' => true, 'subscription_ends_at' => now()->subYear()]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Dostęp bezpłatny')
            ->assertSee('bezterminowo')
            ->assertDontSee('Abonament wygasł');
    }

    public function test_usage_counters_show_products_and_ai(): void
    {
        [$seller, $shop] = $this->sellerWith('stall');
        Product::factory()->count(3)->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Wykorzystanie')
            ->assertSee('3 / 24')      // produkty
            ->assertSee('0 / 100');    // zadania AI w Kramie
    }

    public function test_manual_grant_shows_up_in_the_feature_list(): void
    {
        // Stragan z korespondencją seryjną nadaną poza pakietem — lista musi
        // czytać uprawnienia SKLEPU, nie preset pakietu z configu.
        [$seller] = $this->sellerWith('booth', [
            'entitlements' => array_merge(config('shop.packages.booth.entitlements'), ['bulk_mail' => true]),
            'subscription_ends_at' => now()->addYear(),
        ]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Wiadomości do klientów');
    }

    public function test_change_box_matches_the_purchase_configuration(): void
    {
        [$seller] = $this->sellerWith('booth', ['subscription_ends_at' => now()->addYear()]);

        // Z kluczami platformy (są w .env) box kieruje na przyciski „Kup"…
        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Zmiana pakietu')
            ->assertSee('Kupisz od razu');

        // …a bez nich wraca ścieżka kontaktowa — żadnych martwych przycisków.
        config(['services.paynow.platform.api_key' => null]);
        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('mailto:', false)
            ->assertDontSee('Kupisz od razu');
    }
}
