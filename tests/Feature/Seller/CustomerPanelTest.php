<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dział „Klienci" w panelu sprzedawcy: lista wszystkich kupujących (także
 * gości), wyszukiwanie i karta pojedynczego klienta z historią zamówień.
 * Dostępny we wszystkich pakietach — to narzędzie obsługi sprzedaży, nie
 * funkcja premium.
 */
class CustomerPanelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();

        return [$seller, Shop::factory()->create(['owner_id' => $seller->id])];
    }

    private function orderFor(Shop $shop, string $email, float $total, array $attributes = []): Order
    {
        return Order::factory()->create([
            'shop_id' => $shop->id,
            'buyer_email' => $email,
            'total_gross' => $total,
            ...$attributes,
        ]);
    }

    public function test_list_shows_guests_and_account_holders_with_their_numbers(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->orderFor($shop, 'jan@example.test', 380, ['buyer_name' => 'Jan', 'buyer_surname' => 'Nowak']);
        $account = Customer::factory()->create(['shop_id' => $shop->id, 'email' => 'anna@example.test', 'name' => 'Anna', 'surname' => 'Kowalska']);
        $this->orderFor($shop, 'anna@example.test', 1000, ['customer_id' => $account->id]);
        $this->orderFor($shop, 'anna@example.test', 240, ['customer_id' => $account->id]);

        $this->actingAs($seller)->get(route('seller.customers.index'))
            ->assertOk()
            ->assertSee('Anna Kowalska')
            ->assertSee('Jan Nowak')          // gość musi być widoczny
            ->assertSee('1 240,00')           // suma wydatków Anny (spacja tysięczna)
            ->assertSee('380,00');
    }

    public function test_empty_directory_explains_itself(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)->get(route('seller.customers.index'))
            ->assertOk()
            ->assertSee('Nie masz jeszcze klientów');
    }

    public function test_search_narrows_the_list(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $this->orderFor($shop, 'anna@example.test', 100, ['buyer_name' => 'Anna', 'buyer_surname' => 'Kowalska']);
        $this->orderFor($shop, 'jan@example.test', 100, ['buyer_name' => 'Jan', 'buyer_surname' => 'Nowak']);

        $this->actingAs($seller)->get(route('seller.customers.index', ['szukaj' => 'kowalska']))
            ->assertOk()
            ->assertSee('Anna Kowalska')
            ->assertDontSee('Jan Nowak');
    }

    public function test_filters_narrow_by_account_and_consent(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        // Gość — bez konta, więc i bez zgody.
        $this->orderFor($shop, 'gosc@example.test', 100, ['buyer_name' => 'Jan', 'buyer_surname' => 'Nowak']);

        // Konto ze zgodą.
        $zgoda = Customer::factory()->create(['shop_id' => $shop->id, 'email' => 'zgoda@example.test', 'name' => 'Anna', 'surname' => 'Zgodna']);
        $zgoda->setConsent(\App\Enums\ConsentChannel::Email, true, '127.0.0.1');
        $this->orderFor($shop, 'zgoda@example.test', 100, ['customer_id' => $zgoda->id]);

        // Konto bez zgody.
        $bez = Customer::factory()->create(['shop_id' => $shop->id, 'email' => 'bezzgody@example.test', 'name' => 'Ewa', 'surname' => 'Cicha']);
        $this->orderFor($shop, 'bezzgody@example.test', 100, ['customer_id' => $bez->id]);

        $this->actingAs($seller);

        // Tylko z kontem.
        $this->get(route('seller.customers.index', ['konto' => 1]))
            ->assertOk()->assertSee('Anna Zgodna')->assertSee('Ewa Cicha')->assertDontSee('Jan Nowak');

        // Tylko goście — „0" musi znaczyć coś innego niż brak parametru.
        $this->get(route('seller.customers.index', ['konto' => 0]))
            ->assertOk()->assertSee('Jan Nowak')->assertDontSee('Anna Zgodna');

        // Tylko ze zgodą (to lista odbiorców mailingu).
        $this->get(route('seller.customers.index', ['zgoda' => 1]))
            ->assertOk()->assertSee('Anna Zgodna')->assertDontSee('Ewa Cicha');

        // Bez zgody — także goście, bo oni zgody nie mają.
        $this->get(route('seller.customers.index', ['zgoda' => 0]))
            ->assertOk()->assertSee('Ewa Cicha')->assertSee('Jan Nowak')->assertDontSee('Anna Zgodna');

        // Bez filtrów — wszyscy.
        $this->get(route('seller.customers.index'))
            ->assertOk()->assertSee('Jan Nowak')->assertSee('Anna Zgodna')->assertSee('Ewa Cicha');
    }

    public function test_filter_panel_matches_the_orders_screen(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $this->orderFor($shop, 'anna@example.test', 250, ['buyer_name' => 'Anna', 'buyer_surname' => 'Kowalska']);
        $this->orderFor($shop, 'jan@example.test', 150, ['buyer_name' => 'Jan', 'buyer_surname' => 'Nowak']);

        $this->actingAs($seller)->get(route('seller.customers.index'))
            ->assertOk()
            // Ten sam układ co Zamówienia: nagłówek „Filtry", przycisk „Filtruj",
            // sortowanie osobno przy liście.
            ->assertSee('Filtry')
            ->assertSee('Filtruj')
            ->assertSee('Sortuj')
            // …i podsumowanie wyświetlonego zbioru, jak kafelki sprzedaży.
            ->assertSee('Wydali łącznie')
            ->assertSee('400,00');
    }

    public function test_summary_follows_the_active_filters(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $account = Customer::factory()->create(['shop_id' => $shop->id, 'email' => 'konto@example.test']);
        $this->orderFor($shop, 'konto@example.test', 250, ['customer_id' => $account->id]);
        $this->orderFor($shop, 'gosc@example.test', 150);

        // Bez filtrów: obaj klienci.
        $this->actingAs($seller)->get(route('seller.customers.index'))
            ->assertOk()->assertSee('400,00');

        // Po zawężeniu do kont podsumowanie musi zejść do 250 zł.
        $this->actingAs($seller)->get(route('seller.customers.index', ['konto' => 1]))
            ->assertOk()->assertSee('250,00')->assertDontSee('400,00');
    }

    public function test_orders_on_the_card_are_paginated(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $buyer = ['buyer_name' => 'Anna', 'buyer_surname' => 'Kowalska'];

        foreach (range(1, 18) as $i) {
            $this->orderFor($shop, 'anna@example.test', 50, $buyer);
        }

        $response = $this->actingAs($seller)
            ->get(route('seller.customers.show', ['email' => 'anna@example.test']))
            ->assertOk()
            ->assertSee('18 zamówień');

        // 15 na stronę, więc stronicowanie musi się pokazać.
        $this->assertTrue($response->viewData('orders')->hasPages());
        $this->assertCount(15, $response->viewData('orders')->items());
    }

    public function test_card_links_back_to_the_customer_list(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $this->orderFor($shop, 'anna@example.test', 100);

        // „Wróć do listy" — tak samo jak na szczególe zamówienia.
        $this->actingAs($seller)->get(route('seller.customers.show', ['email' => 'anna@example.test']))
            ->assertOk()
            ->assertSee('Wróć do listy')
            ->assertDontSee('Wróć do kartoteki');
    }

    public function test_customer_card_shows_history_and_statistics(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        // Dane osobowe kartoteka bierze z NAJNOWSZEGO zamówienia (mają być
        // aktualne), więc ustawiamy je na każdym — inaczej ostatnie zamówienie
        // z fabryki podstawiłoby losowe imię.
        $buyer = ['buyer_name' => 'Anna', 'buyer_surname' => 'Kowalska'];

        $first = $this->orderFor($shop, 'anna@example.test', 100, $buyer);
        $this->orderFor($shop, 'anna@example.test', 300, $buyer);
        $this->orderFor($shop, 'anna@example.test', 900, $buyer + ['status' => OrderStatus::Cancelled]);

        $this->actingAs($seller)->get(route('seller.customers.show', ['email' => 'anna@example.test']))
            ->assertOk()
            ->assertSee('Anna Kowalska')
            ->assertSee('400,00')             // wydatki bez anulowanego
            ->assertSee('200,00')             // średnie zamówienie z zapłaconych
            ->assertSee('#'.$first->number)
            ->assertSee('Kupował jako gość');
    }

    public function test_card_of_an_address_that_never_bought_here_is_404(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)->get(route('seller.customers.show', ['email' => 'nikt@example.test']))
            ->assertNotFound();
    }

    public function test_seller_cannot_open_a_customer_of_another_shop(): void
    {
        [$seller] = $this->sellerWithShop();
        $other = Shop::factory()->create();
        $this->orderFor($other, 'obcy@example.test', 500);

        // Kartoteka jest scope'owana do sklepu — cudzy klient nie istnieje.
        $this->actingAs($seller)->get(route('seller.customers.show', ['email' => 'obcy@example.test']))
            ->assertNotFound();
    }

    public function test_directory_is_available_in_every_package(): void
    {
        // Sklep na domyślnym pakiecie (Kram, darmowy) — bez żadnych dodatków.
        [$seller, $shop] = $this->sellerWithShop();
        $this->orderFor($shop, 'anna@example.test', 100);

        $this->actingAs($seller)->get(route('seller.customers.index'))
            ->assertOk()
            ->assertDontSee('pakiecie Pawilon');

        $this->actingAs($seller)->get(route('seller.customers.show', ['email' => 'anna@example.test']))
            ->assertOk();
    }
}
