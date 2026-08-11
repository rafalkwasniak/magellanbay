<?php

namespace Tests\Feature\Administrator;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsola admina — zamówienia całej platformy (TYLKO DO ODCZYTU).
 *
 * Ekran ma jedno zadanie ponad przekrojem: wsparcie. Sprzedawca dzwoni „nie
 * widzę zamówienia z wczoraj", a admin musi je znaleźć po tym, czym akurat
 * dysponuje — numerze, mailu klienta albo nazwie sklepu.
 */
class OrderOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_orders_from_every_shop(): void
    {
        // Sedno wielosklepowości: jedna lista zbiera sprzedaż z całej platformy,
        // czego z panelu pojedynczego sprzedawcy nie da się zobaczyć.
        $admin = User::factory()->admin()->create();
        Order::factory()->for(Shop::factory()->create(['name' => 'Kwiaciarnia Zosia']))->create();
        Order::factory()->for(Shop::factory()->create(['name' => 'Pracownia Igła']))->create();

        $this->actingAs($admin)
            ->get(route('administrator.orders.index'))
            ->assertOk()
            ->assertSee('Kwiaciarnia Zosia')
            ->assertSee('Pracownia Igła');
    }

    public function test_seller_cannot_view_platform_orders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('administrator.orders.index'))
            ->assertForbidden();
    }

    public function test_order_can_be_found_by_number(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create();
        $wanted = Order::factory()->for($shop)->create(['number' => 'ZAM-2026-0042']);
        Order::factory()->for($shop)->create(['number' => 'ZAM-2026-0099']);

        $this->actingAs($admin)
            ->get(route('administrator.orders.index', ['szukaj' => 'ZAM-2026-0042']))
            ->assertOk()
            ->assertSee($wanted->number)
            ->assertDontSee('ZAM-2026-0099');
    }

    public function test_order_can_be_found_by_customer_email(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create();
        Order::factory()->for($shop)->create(['buyer_email' => 'anna@example.pl', 'number' => 'ZAM-A']);
        Order::factory()->for($shop)->create(['buyer_email' => 'piotr@example.pl', 'number' => 'ZAM-B']);

        $this->actingAs($admin)
            ->get(route('administrator.orders.index', ['szukaj' => 'anna@example.pl']))
            ->assertOk()
            ->assertSee('ZAM-A')
            ->assertDontSee('ZAM-B');
    }

    public function test_orders_can_be_filtered_by_shop_and_status(): void
    {
        $admin = User::factory()->admin()->create();
        $zosia = Shop::factory()->create(['name' => 'Kwiaciarnia Zosia']);
        $igla = Shop::factory()->create(['name' => 'Pracownia Igła']);
        Order::factory()->for($zosia)->create(['number' => 'ZAM-ZOSIA', 'status' => OrderStatus::Paid]);
        Order::factory()->for($igla)->create(['number' => 'ZAM-IGLA', 'status' => OrderStatus::Paid]);
        Order::factory()->for($zosia)->create(['number' => 'ZAM-NOWE', 'status' => OrderStatus::New]);

        $this->actingAs($admin)
            ->get(route('administrator.orders.index', ['sklep' => $zosia->id, 'status' => 'paid']))
            ->assertOk()
            ->assertSee('ZAM-ZOSIA')
            ->assertDontSee('ZAM-IGLA')
            ->assertDontSee('ZAM-NOWE');
    }

    public function test_list_shows_last_30_days_by_default_and_the_whole_history_on_demand(): void
    {
        // Domyślne okno to decyzja produktowa, nie oszczędność: lista wsparcia ma
        // pokazywać świeże sprawy. Starsze zamówienie musi jednak dać się znaleźć,
        // inaczej pusty ekran wyglądałby jak utrata danych.
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create();
        Order::factory()->for($shop)->create(['number' => 'ZAM-STARE', 'created_at' => now()->subMonths(4)]);
        Order::factory()->for($shop)->create(['number' => 'ZAM-SWIEZE']);

        $this->actingAs($admin)
            ->get(route('administrator.orders.index'))
            ->assertOk()
            ->assertSee('ZAM-SWIEZE')
            ->assertDontSee('ZAM-STARE');

        $this->actingAs($admin)
            ->get(route('administrator.orders.index', ['okres' => '']))
            ->assertOk()
            ->assertSee('ZAM-STARE');
    }

    public function test_cancelled_orders_are_listed_but_never_counted_as_sales(): void
    {
        // Anulowane trzeba móc ZNALEŹĆ (klient dzwoni, że anulowano mu zamówienie),
        // ale wliczone do sprzedaży zawyżałyby obrót i zaniżały średni koszyk.
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create();
        Order::factory()->for($shop)->create(['number' => 'ZAM-OK', 'total_gross' => 100, 'status' => OrderStatus::Paid]);
        Order::factory()->for($shop)->create(['number' => 'ZAM-ANULOWANE', 'total_gross' => 900, 'status' => OrderStatus::Cancelled]);

        $this->actingAs($admin)
            ->get(route('administrator.orders.index'))
            ->assertOk()
            ->assertSee('ZAM-ANULOWANE')     // jest na liście...
            ->assertSee('100,00 zł')          // ...ale sprzedaż i koszyk widzą tylko 100 zł
            ->assertDontSee('1 000,00 zł');
    }

    public function test_screen_offers_no_way_to_change_an_order(): void
    {
        // Decyzja Rafała: konsola tylko patrzy. Formularz zmiany statusu tutaj
        // wysłałby klientowi maila, o którym sprzedawca by nie wiedział.
        $admin = User::factory()->admin()->create();
        Order::factory()->for(Shop::factory()->create())->create();

        $response = $this->actingAs($admin)->get(route('administrator.orders.index'));

        $response->assertOk()->assertSee('wyłącznie do odczytu');

        // Żaden formularz nie celuje w trasę zamówień — jedyny formularz ekranu to
        // GET-owe filtry. Liczymy POST-y CELUJĄCE W ZAMÓWIENIA, a nie wszystkie na
        // stronie: layout ma własne (wylogowanie, ciasteczka) i one nic tu nie znaczą.
        preg_match_all('/<form[^>]*method="POST"[^>]*>/i', $response->getContent(), $forms);
        $targetingOrders = array_filter(
            $forms[0],
            fn (string $form): bool => str_contains($form, '/administrator/zamowienia')
        );

        $this->assertSame([], $targetingOrders);
    }
}
