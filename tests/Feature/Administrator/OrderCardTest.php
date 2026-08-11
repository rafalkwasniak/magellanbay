<?php

namespace Tests\Feature\Administrator;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsola admina — karta zamówienia (PODGLĄD).
 *
 * Istnieje po to, żeby wsparcie odpowiedziało na „co jest w tym zamówieniu i na
 * czym stoi", nie prosząc sprzedawcy o zrzut ekranu. Nie wolno jej dać ani
 * jednej akcji: zmiana z konsoli wysłałaby klientowi maila, o którym sprzedawca
 * by nie wiedział.
 */
class OrderCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_items_customer_and_totals(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create(['name' => 'Kwiaciarnia Zosia']);
        $order = Order::factory()->for($shop)->create([
            'number' => 'ZAM-2026-0042',
            'buyer_name' => 'Anna',
            'buyer_surname' => 'Kowalska',
            'buyer_email' => 'anna@example.pl',
            'total_gross' => 249.90,
        ]);
        OrderItem::factory()->for($order)->create(['name' => 'Bukiet komunijny', 'quantity' => 2]);

        $this->actingAs($admin)
            ->get(route('administrator.orders.show', $order))
            ->assertOk()
            ->assertSee('ZAM-2026-0042')
            ->assertSee('Kwiaciarnia Zosia')
            ->assertSee('Bukiet komunijny')
            ->assertSee('Anna Kowalska')
            ->assertSee('anna@example.pl')
            ->assertSee('249,90 zł');
    }

    public function test_seller_cannot_open_a_foreign_order(): void
    {
        // Sprzedawca ma własną kartę zamówienia w swoim panelu; ta trasa jest
        // administracyjna i musi być zamknięta niezależnie od tego, czyj to sklep.
        $order = Order::factory()->for(Shop::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->get(route('administrator.orders.show', $order))
            ->assertForbidden();
    }

    public function test_card_offers_no_action_that_changes_the_order(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->for(Shop::factory()->create())->create();

        $response = $this->actingAs($admin)->get(route('administrator.orders.show', $order));

        $response->assertOk()->assertSee('wyłącznie do odczytu');

        // Liczymy POST-y CELUJĄCE W ZAMÓWIENIA — layout ma własne (wylogowanie,
        // ciasteczka) i one nic tu nie znaczą.
        preg_match_all('/<form[^>]*method="POST"[^>]*>/i', $response->getContent(), $forms);

        $this->assertSame([], array_filter(
            $forms[0],
            fn (string $form): bool => str_contains($form, '/administrator/zamowienia')
        ));
    }

    public function test_returned_item_shows_both_quantities(): void
    {
        // Zwrot pomniejsza ilość EFEKTYWNĄ, ale `quantity` zostaje migawką z
        // chwili zakupu — i to na nią opiewa faktura sprzed zwrotu. Pokazanie
        // tylko jednej z tych liczb kazałoby adminowi zgadywać, dlaczego karta
        // nie zgadza się z dokumentem.
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->for(Shop::factory()->create())->create();
        OrderItem::factory()->for($order)->create([
            'name' => 'Świeca sojowa',
            'quantity' => 3,
            'returned_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('administrator.orders.show', $order))
            ->assertOk()
            // Jednostka pochodzi z `SaleUnit`, więc karta mówi „1 szt.", a nie samo „1".
            ->assertSee('zwrot 1 szt. z 3 szt.');
    }

    public function test_status_history_is_shown(): void
    {
        // Bez historii nie widać, KIEDY zamówienie utknęło — data założenia i
        // bieżący status same tego nie mówią.
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->for(Shop::factory()->create())->create(['status' => OrderStatus::New]);
        $order->changeStatus(OrderStatus::Paid);

        $this->actingAs($admin)
            ->get(route('administrator.orders.show', $order))
            ->assertOk()
            ->assertSee('Historia')
            ->assertSee('Opłacone');
    }

    public function test_failed_shipment_reason_is_visible_on_the_card(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->for(Shop::factory()->create())->create([
            'shipment_error' => 'Nie udało się nadać przesyłki w InPoście.',
        ]);

        $this->actingAs($admin)
            ->get(route('administrator.orders.show', $order))
            ->assertOk()
            ->assertSee('Nie udało się nadać przesyłki w InPoście.');
    }

    public function test_order_number_on_the_list_opens_the_card(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->for(Shop::factory()->create())->create();

        $this->actingAs($admin)
            ->get(route('administrator.orders.index'))
            ->assertOk()
            ->assertSee('href="'.route('administrator.orders.show', $order).'"', false);
    }
}
