<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Livewire\Seller\OrderStatusManager;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Zmiana statusu zamówienia w panelu (komponent OrderStatusManager): przejścia
 * są wybaczające, każda zmiana dopisuje zdarzenie do osi czasu, a sprzedawca
 * może ruszać wyłącznie zamówienia własnego sklepu.
 */
class OrderStatusChangeTest extends TestCase
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

    public function test_seller_changes_status_and_records_timeline_event(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::New]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->set('note', 'Zapłacone gotówką')
            ->call('changeTo', OrderStatus::Paid->value)
            ->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertCount(1, $order->statusEvents);

        $event = $order->statusEvents->first();
        $this->assertSame(OrderStatus::New, $event->from_status);
        $this->assertSame(OrderStatus::Paid, $event->to_status);
        $this->assertSame('Zapłacone gotówką', $event->note);
    }

    public function test_setting_same_status_is_a_no_op(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::New]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('changeTo', OrderStatus::New->value);

        $this->assertCount(0, $order->refresh()->statusEvents);
    }

    public function test_note_is_cleared_after_change(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::New]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->set('note', 'jakaś notatka')
            ->call('changeTo', OrderStatus::Processing->value)
            ->assertSet('note', '');
    }

    public function test_seller_cannot_change_foreign_order(): void
    {
        [$seller] = $this->sellerWithShop();
        $foreign = Order::factory()->create(['status' => OrderStatus::New]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $foreign])
            ->call('changeTo', OrderStatus::Paid->value)
            ->assertForbidden();

        $this->assertSame(OrderStatus::New, $foreign->refresh()->status);
    }

    public function test_pickup_order_recommends_ready_for_pickup_not_shipped(): void
    {
        // Odbiór osobisty (fabryka): „prawdopodobne" prowadzą do „Gotowe do odbioru",
        // a „Wysłane" ląduje wśród mniej prawdopodobnych. Cancelled nigdy w wyborach.
        $choices = OrderStatus::Processing->transitionChoices(\App\Enums\DeliveryMethod::Pickup);

        $this->assertContains(OrderStatus::ReadyForPickup, $choices['likely']);
        $this->assertNotContains(OrderStatus::Shipped, $choices['likely']);
        $this->assertContains(OrderStatus::Shipped, $choices['others']);
        $this->assertNotContains(OrderStatus::Cancelled, $choices['likely']);
        $this->assertNotContains(OrderStatus::Cancelled, $choices['others']);
    }

    public function test_recommended_next_step_is_first_in_likely(): void
    {
        // Zalecany = pierwszy w „likely" (kolejny krok kanonicznej ścieżki).
        $choices = OrderStatus::New->transitionChoices(\App\Enums\DeliveryMethod::Pickup);

        $this->assertSame(OrderStatus::AwaitingPayment, $choices['likely'][0]);
    }

    public function test_completed_has_no_forward_steps_but_others_available(): void
    {
        // Status końcowy: brak kroków naprzód, ale UI wybaczające — reszta w „others".
        $choices = OrderStatus::Completed->transitionChoices(\App\Enums\DeliveryMethod::Pickup);

        $this->assertSame([], $choices['likely']);
        $this->assertNotEmpty($choices['others']);
        $this->assertNotContains(OrderStatus::Cancelled, $choices['others']);
    }
}
