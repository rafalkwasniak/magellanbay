<?php

namespace Tests\Feature\Seller;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Support\PackageFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ekrany zamknięte pakietem muszą wyglądać i mówić tak samo.
 *
 * Rafał wyłapał (30.07), że Kody rabatowe zostawiają prawą kolumnę z opisami, a
 * Wiadomości nie zostawiają nic — ten sam stan, dwa różne wrażenia: jeden ekran
 * czyta się jako zamknięty, drugi jako zepsuty. Przy okazji audytu wyszło, że
 * żadna zachęta nie prowadziła do zakupu (pisane, gdy płatności nie było) i że
 * nazwy pakietów były wklejone w treść widoków.
 */
class LockedFeatureConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sklep na najtańszym pakiecie — nic płatnego nie jest odblokowane.
     *
     * @return array{0: User, 1: Shop}
     */
    private function freeSeller(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create([
            'owner_id' => $seller->id,
            'package' => 'stall',
            'entitlements' => config('shop.packages.stall.entitlements'),
            'price_yearly' => 0,
        ]);

        return [$seller, $shop];
    }

    /**
     * @return list<array{0: string, 1: string}>  [trasa, nagłówek zachęty]
     */
    public static function lockedScreens(): array
    {
        return [
            'kody rabatowe' => ['seller.discounts.index', 'Kody rabatowe w pakiecie Pawilon'],
            'wiadomości' => ['seller.mailings.index', 'Wiadomości do klientów w pakiecie Pawilon'],
            'integracje' => ['seller.integrations.edit', 'Integracje w pakiecie Stragan'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('lockedScreens')]
    public function test_every_locked_screen_names_the_package_and_leads_to_the_purchase(string $route, string $headline): void
    {
        [$seller] = $this->freeSeller();

        $this->actingAs($seller)->get(route($route))
            ->assertOk()
            ->assertSee($headline)
            ->assertSee('Twój obecny pakiet:')
            ->assertSee('Kram')
            // Sedno poprawki: zachęta ma DOKĄD wysłać.
            ->assertSee(route('seller.package.show'), false)
            ->assertSee('Zobacz pakiet');
    }

    public function test_locked_screens_keep_their_side_column(): void
    {
        [$seller] = $this->freeSeller();

        // Prawa kolumna z opisami zostaje na obu ekranach — inaczej jeden z nich
        // wygląda na w pół zepsuty.
        $this->actingAs($seller)->get(route('seller.discounts.index'))
            ->assertOk()
            ->assertSee('Jak to działa');

        $this->actingAs($seller)->get(route('seller.mailings.index'))
            ->assertOk()
            ->assertSee('Jak to działa')
            // Liczba zgód działa jako zachęta mocniej niż opis funkcji, więc
            // pokazujemy ją także przy blokadzie.
            ->assertSee('Odbiorcy');
    }

    public function test_package_names_in_upsells_come_from_config(): void
    {
        // Gdyby ktoś przeniósł funkcję do innego pakietu, treść ma pójść za nim.
        $this->assertSame('Pawilon', PackageFeatures::cheapestWith('bulk_mail')['name']);
        $this->assertSame('Pawilon', PackageFeatures::cheapestWith('discount_codes')['name']);
        // Fakturownia jest już w Straganie — najtańszy, nie pierwszy z listy.
        $this->assertSame('Stragan', PackageFeatures::cheapestWith('invoices')['name']);
        $this->assertNull(PackageFeatures::cheapestWith('nie_ma_takiej_funkcji'));
    }

    public function test_missing_entitlement_is_not_reported_as_a_cancelled_order(): void
    {
        [$seller, $shop] = $this->freeSeller();
        $order = Order::factory()->create(['shop_id' => $shop->id]);

        // Było: „Anulowane — tylko podgląd" przy zamówieniu, które anulowane NIE
        // JEST. Fałszywa informacja o cudzym zamówieniu.
        $this->actingAs($seller)->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Anulowane — tylko podgląd')
            ->assertSee('Edycja zamówień w pakiecie Pawilon');
    }

    public function test_cancelled_order_still_says_it_is_cancelled(): void
    {
        // Drugi powód braku przycisku musi zostać nietknięty.
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create([
            'owner_id' => $seller->id,
            'package' => 'pavilion',
            'entitlements' => config('shop.packages.pavilion.entitlements'),
            'price_yearly' => 1500,
            'subscription_ends_at' => now()->addYear(),
        ]);
        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'status' => \App\Enums\OrderStatus::Cancelled,
        ]);

        $this->actingAs($seller)->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('Anulowane — tylko podgląd');
    }
}
