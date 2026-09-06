<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Arkusz produkcyjny zamówienia (Etap 2, krok H — wersja startowa).
 *
 * Kartka do położenia obok magnesu. Świadomie najmniejsza rzecz, która ma sens:
 * klient nie opisał tego modułu ani jednym zdaniem, a kształt zbiorczego
 * zestawienia zależy od tego, jak zorganizowana jest jego pracownia.
 *
 * Testy pilnują dwóch rzeczy: że wykonawca DOSTAJE wszystko, czego potrzebuje
 * (teksty do nadruku i graweru), i że NIE dostaje niczego, czego nie powinien
 * zobaczyć (ceny, dane płatnicze).
 */
class OrderWorksheetTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->consented()->create();
        $this->shop = Shop::factory()->create(['owner_id' => $this->owner->id]);
    }

    private function zamowienie(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'shop_id' => $this->shop->id,
            'status' => OrderStatus::Paid,
        ], $attributes));
    }

    public function test_worksheet_shows_what_has_to_be_engraved(): void
    {
        $order = $this->zamowienie();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'name' => 'Magnes kamienny',
            'quantity' => 2,
            'personalisation' => [
                ['label' => 'Imię', 'value' => 'Zosia'],
                ['label' => 'Grawer', 'value' => 'Do mety albo śmierć'],
            ],
        ]);

        $this->actingAs($this->owner)
            ->get(route('seller.orders.worksheet', $order))
            ->assertOk()
            ->assertSee('Arkusz produkcyjny')
            ->assertSee($order->number)
            ->assertSee('Magnes kamienny')
            ->assertSee('Imię')
            ->assertSee('Zosia')
            ->assertSee('Do mety albo śmierć');
    }

    /**
     * ARKUSZ IDZIE DO PRACOWNI ALBO DO PODWYKONAWCY. Do wykonania magnesu
     * potrzebny jest tekst i grafika, nie kwota, którą zapłacił klient.
     */
    public function test_worksheet_carries_no_prices(): void
    {
        $order = $this->zamowienie(['total_gross' => 123.45]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'name' => 'Magnes kamienny',
            'quantity' => 1,
            'unit_price_gross' => 99.99,
            'line_total_gross' => 99.99,
            'personalisation' => [['label' => 'Imię', 'value' => 'Zosia']],
        ]);

        $this->actingAs($this->owner)
            ->get(route('seller.orders.worksheet', $order))
            ->assertOk()
            ->assertDontSee('99,99')
            ->assertDontSee('99.99')
            ->assertDontSee('123,45')
            ->assertDontSee('123.45');
    }

    public function test_item_without_personalisation_says_so(): void
    {
        $order = $this->zamowienie();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'name' => 'Magnes zwykły',
            'quantity' => 1,
            'personalisation' => null,
        ]);

        $this->actingAs($this->owner)
            ->get(route('seller.orders.worksheet', $order))
            ->assertOk()
            ->assertSee('Bez personalizacji');
    }

    /**
     * Arkusz mógł zostać wydrukowany wcześniej, ale ten, który powstaje TERAZ,
     * ma powiedzieć na górze strony, że wykonywać nie należy.
     */
    public function test_cancelled_order_warns_at_the_top(): void
    {
        $order = $this->zamowienie(['status' => OrderStatus::Cancelled]);
        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1]);

        $this->actingAs($this->owner)
            ->get(route('seller.orders.worksheet', $order))
            ->assertOk()
            ->assertSee('NIE WYKONYWAĆ');
    }

    /**
     * Ilości NIE zmniejszamy o zwrot — arkusz jest zleceniem wykonania, a zwrot
     * przychodzi zwykle po nim. Ale człowiek ma o zwrocie wiedzieć.
     */
    public function test_a_return_is_noted_without_changing_the_quantity(): void
    {
        $order = $this->zamowienie();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'name' => 'Magnes kamienny',
            'quantity' => 3,
            'returned_quantity' => 1,
        ]);

        $this->actingAs($this->owner)
            ->get(route('seller.orders.worksheet', $order))
            ->assertOk()
            ->assertSee('zwrócono');
    }

    /**
     * Cudze zamówienie odbija się 403 — tak samo jak reszta ekranów tego
     * kontrolera. Spójność wewnątrz modułu jest tu ważniejsza niż moja
     * preferencja dla 404: sprzedawca i tak nie zgaduje cudzych numerów,
     * bo widzi wyłącznie własną listę.
     */
    public function test_another_shops_order_is_refused(): void
    {
        $cudze = Order::factory()->create(['shop_id' => Shop::factory()->create()->id]);

        $this->actingAs($this->owner)
            ->get(route('seller.orders.worksheet', $cudze))
            ->assertForbidden();
    }

    public function test_guest_cannot_read_the_worksheet(): void
    {
        $order = $this->zamowienie();

        $this->get(route('seller.orders.worksheet', $order))
            ->assertRedirect(route('login'));
    }

    public function test_order_screen_links_to_the_worksheet(): void
    {
        $order = $this->zamowienie();
        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1]);

        $this->actingAs($this->owner)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('Arkusz produkcyjny');
    }
}
