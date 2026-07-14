<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Exceptions\OrderEditException;
use App\Livewire\Seller\OrderEditor;
use App\Livewire\Seller\OrderStatusManager;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\OrderEditor as OrderEditorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Anulowanie zamówienia (ustalenia 2026-07-14): wymaga potwierdzenia, oddaje
 * towar na stan, wysyła kupującemu maila z wykazem i kwotą, jest nieodwracalne,
 * a anulowane zamówienie jest zamrożone — zostaje wyłącznie informacyjnie.
 */
class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);

        return [$seller, $shop];
    }

    /**
     * Zamówienie z jedną pozycją na 3 szt. produktu, którego stan wynosi 5 —
     * czyli tak, jakby przy składaniu zdjęto 3 z ośmiu.
     *
     * @return array{0: Order, 1: Product}
     */
    private function orderWithStock(Shop $shop, bool $trackStock = true): array
    {
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'track_stock' => $trackStock,
            'stock' => $trackStock ? 5 : null,
        ]);

        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::Processing]);
        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        return [$order, $product];
    }

    public function test_cancelling_returns_goods_to_stock(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        [$order, $product] = $this->orderWithStock($shop);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('askCancel')
            ->call('cancel');

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        // 5 na stanie + 3 zdjęte przez to zamówienie = 8.
        $this->assertSame('8.00', $product->fresh()->stock);
    }

    public function test_cancelling_leaves_untracked_products_alone(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        [$order, $product] = $this->orderWithStock($shop, trackStock: false);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('askCancel')
            ->call('cancel');

        // Produkt bez kontroli stanu — nie ma czego zwracać.
        $this->assertNull($product->fresh()->stock);
    }

    public function test_cancelling_mails_the_buyer_with_reason_and_amount(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        [$order] = $this->orderWithStock($shop);
        $order->update(['buyer_email' => 'kupujacy@example.com', 'total_gross' => 149.99]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('askCancel')
            ->set('cancelReason', 'Brak towaru w magazynie')
            ->call('cancel');

        $this->assertSame(1, EmailMessage::count());

        $mail = EmailMessage::first();
        $this->assertSame('kupujacy@example.com', $mail->to_email);
        $this->assertSame('Zamówienie #'.$order->number.' zostało anulowane — '.$shop->name, $mail->subject);

        $body = json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Brak towaru w magazynie', $body);   // powód
        $this->assertStringContainsString('149,99', $body);                    // kwota
    }

    public function test_reason_is_optional_and_recorded_on_the_timeline(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        [$order] = $this->orderWithStock($shop);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('askCancel')
            ->call('cancel');

        $event = $order->refresh()->statusEvents->last();
        $this->assertSame(OrderStatus::Cancelled, $event->to_status);
        $this->assertNull($event->note);
        $this->assertSame(1, EmailMessage::count());   // mail leci także bez powodu
    }

    public function test_cancelling_asks_for_confirmation_first(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        [$order, $product] = $this->orderWithStock($shop);

        // Samo wejście w potwierdzenie niczego nie robi — dopiero „cancel".
        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('askCancel')
            ->assertSet('confirmingCancel', true)
            ->assertSee('Tej operacji')
            ->call('dismissCancel')
            ->assertSet('confirmingCancel', false);

        $this->assertSame(OrderStatus::Processing, $order->refresh()->status);
        $this->assertSame('5.00', $product->fresh()->stock);
        $this->assertSame(0, EmailMessage::count());
    }

    public function test_cancel_cannot_be_smuggled_through_the_status_buttons(): void
    {
        // „Anulowane" nie jest zwykłym statusem — nie da się go ustawić z pominięciem
        // potwierdzenia, więc i z pominięciem zwrotu na stan.
        [$seller, $shop] = $this->sellerWithShop();
        [$order, $product] = $this->orderWithStock($shop);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('changeTo', OrderStatus::Cancelled->value);

        $this->assertSame(OrderStatus::Processing, $order->refresh()->status);
        $this->assertSame('5.00', $product->fresh()->stock);
    }

    public function test_cancelled_order_cannot_be_edited_so_stock_is_never_returned_twice(): void
    {
        [, $shop] = $this->sellerWithShop();
        [$order, $product] = $this->orderWithStock($shop);
        $item = $order->items()->first();

        app(\App\Services\OrderStatusChanger::class)->change($order, OrderStatus::Cancelled);
        $this->assertSame('8.00', $product->fresh()->stock);

        // Usunięcie pozycji po anulowaniu oddałoby te 3 szt. DRUGI raz.
        $this->expectException(OrderEditException::class);
        app(OrderEditorService::class)->removeItem($item);
    }

    public function test_editing_controls_are_hidden_on_a_cancelled_order(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        [$order] = $this->orderWithStock($shop);
        $order->update(['status' => OrderStatus::Cancelled]);

        Livewire::actingAs($seller)
            ->test(OrderEditor::class, ['order' => $order])
            ->assertSee('Anulowane — tylko podgląd')
            ->assertDontSee('Edytuj zamówienie')
            ->call('toggleEditing')
            ->assertSet('editing', false);        // tryb edycji się nie odsłania
    }

    public function test_cancelling_tells_the_items_card_to_close_editing(): void
    {
        // Karty „Status" i „Pozycje" to osobne komponenty. Bez tego sygnału karta
        // pozycji zostałaby w trybie edycji nad zamówieniem, którego już nie wolno
        // ruszać — do najbliższego odświeżenia strony.
        [$seller, $shop] = $this->sellerWithShop();
        [$order] = $this->orderWithStock($shop);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('askCancel')
            ->call('cancel')
            ->assertDispatched('order-status-changed');

        Livewire::actingAs($seller)
            ->test(OrderEditor::class, ['order' => $order])
            ->set('editing', true)
            ->call('syncWithStatus')
            ->assertSet('editing', false);
    }
}
