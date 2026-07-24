<?php

namespace Tests\Feature\Seller;

use App\Enums\IntegrationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Jobs\GenerateInvoice;
use App\Livewire\Seller\OrderStatusManager;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Zmiana statusu zamówienia w panelu (komponent OrderStatusManager): wolno
 * poruszać się wyłącznie po ścieżce zamówienia (`OrderFlow`) — i to twardo, nie
 * tylko w UI. Każda zmiana dopisuje zdarzenie do osi czasu, anulowane jest
 * zamrożone, a sprzedawca może ruszać tylko zamówienia własnego sklepu.
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
            ->set('note', 'Pakuję dzisiaj')
            ->call('changeTo', OrderStatus::Processing->value)
            ->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertCount(1, $order->statusEvents);

        $event = $order->statusEvents->first();
        $this->assertSame(OrderStatus::New, $event->from_status);
        $this->assertSame(OrderStatus::Processing, $event->to_status);
        $this->assertSame('Pakuję dzisiaj', $event->note);
    }

    public function test_bank_transfer_marked_paid_does_not_auto_invoice(): void
    {
        // Auto-FV jest przypięta do Paynow (wyzwala ją webhook operatora), NIE do
        // samego statusu „Opłacone". Ręczne oznaczenie przelewu jako opłaconego —
        // nawet gdy sklep ma auto-FV i włączoną Fakturownię — nie zleca faktury.
        // Ta gwarancja jest sednem umieszczenia checkboxa pod Paynow.
        Bus::fake();
        [$seller, $shop] = $this->sellerWithShop();
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'API', 'signature_key' => 'SIGN', 'environment' => 'sandbox', 'auto_invoice' => true],
        ]);
        $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://sklep.fakturownia.pl', 'api_token' => 'SECRET'],
        ]);

        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::BankTransfer,
        ]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('changeTo', OrderStatus::Paid->value)
            ->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        Bus::assertNotDispatched(GenerateInvoice::class);
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

    public function test_status_outside_the_flow_is_rejected_by_the_backend(): void
    {
        // Płatność przy odbiorze (fabryka) nie zna „Opłacone" — nie ma czego
        // potwierdzać. Blokada jest twarda, nie sprowadza się do ukrycia guzika.
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::New]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('changeTo', OrderStatus::Paid->value);

        $order->refresh();
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertCount(0, $order->statusEvents);
    }

    public function test_same_status_is_allowed_on_the_prepaid_flow(): void
    {
        // Kontrola do testu wyżej: „Opłacone" blokuje ścieżka, nie sam status.
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::BankTransfer,
        ]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('changeTo', OrderStatus::Paid->value);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_cancelled_order_is_frozen(): void
    {
        // Anulowanie jest nieodwracalne — z „Anulowane" nie ma wyjścia.
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::Cancelled]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('changeTo', OrderStatus::Processing->value);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertCount(0, $order->statusEvents);
    }

    public function test_every_status_change_mails_the_buyer(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::New,
            'buyer_email' => 'kupujacy@example.com',
        ]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->set('note', 'Odbiór do piątku')
            ->call('changeTo', OrderStatus::Processing->value);

        $this->assertSame(1, EmailMessage::count());

        $mail = EmailMessage::first();
        $this->assertSame('kupujacy@example.com', $mail->to_email);
        $this->assertSame('Zamówienie #'.$order->number.': W realizacji — '.$shop->name, $mail->subject);
        $this->assertSame($shop->name, $mail->from_name);       // tożsamość „od sklepu"
        $this->assertSame($shop->id, $mail->shop_id);           // branding per sklep

        // Notatka sprzedawcy trafia do kupującego wraz ze statusem.
        $this->assertStringContainsString('Odbiór do piątku', json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE));
    }

    public function test_buyer_is_mailed_even_when_status_goes_backwards(): void
    {
        // Cofnięcie też leci mailem — inaczej klient przyjedzie po coś, czego nie ma.
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::ReadyForPickup]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('changeTo', OrderStatus::Processing->value);

        $this->assertSame(OrderStatus::Processing, $order->refresh()->status);
        $this->assertSame(1, EmailMessage::count());
    }

    public function test_no_op_and_rejected_changes_send_no_mail(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::New]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('changeTo', OrderStatus::New->value)          // ten sam status
            ->call('changeTo', OrderStatus::Paid->value);        // spoza ścieżki

        // Ani pustego przejścia w historii, ani śmiecia w skrzynce kupującego.
        $this->assertCount(0, $order->refresh()->statusEvents);
        $this->assertSame(0, EmailMessage::count());
    }

    public function test_timeline_shows_the_initial_status_of_a_fresh_order(): void
    {
        // Status początkowy nie jest zdarzeniem (nikt go nie zmieniał), więc oś
        // czasu musi go dołożyć — inaczej świeże zamówienie ma tylko „Złożone".
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::BankTransfer,
        ]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->assertSee('Złożone')
            ->assertSee('Oczekuje na płatność');
    }

    public function test_timeline_keeps_the_initial_status_after_it_changes(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::BankTransfer,
        ]);

        $html = Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('changeTo', OrderStatus::Paid->value)
            ->html();

        // Sedno: początkowy „Oczekuje na płatność" NIE znika z osi po zmianie —
        // wcześniej oś zaczynała się dopiero od pierwszej ZMIANY, więc go zjadała.
        $timeline = Str::between($html, '<ol', '</ol>');
        $this->assertStringContainsString('Złożone', $timeline);
        $this->assertStringContainsString('Oczekuje na płatność', $timeline);
        $this->assertStringContainsString('Opłacone', $timeline);
        $this->assertLessThan(
            strpos($timeline, 'Opłacone'),
            strpos($timeline, 'Oczekuje na płatność'),
            'Status początkowy musi stać na osi PRZED tym, na który go zmieniono.',
        );
    }

    public function test_clicking_a_status_opens_an_inline_confirmation_instead_of_changing(): void
    {
        // Klik w status nie zmienia go od razu — otwiera panel potwierdzenia
        // w miejscu (bliźniaczy do anulowania), zamiast systemowego popupu.
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::New]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('askChange', OrderStatus::Processing->value)
            ->assertSet('pendingStatus', OrderStatus::Processing->value)
            ->assertSee('Zmienić status na');

        // Nic się jeszcze nie stało: ani zmiany, ani maila.
        $this->assertSame(OrderStatus::New, $order->refresh()->status);
        $this->assertCount(0, $order->statusEvents);
    }

    public function test_asking_to_change_to_a_status_outside_the_flow_opens_nothing(): void
    {
        // Płatność przy odbiorze nie zna „Opłacone" — nie ma czego potwierdzać.
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::New]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('askChange', OrderStatus::Paid->value)
            ->assertSet('pendingStatus', null);
    }

    public function test_dismissing_the_confirmation_clears_the_pending_status_and_note(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::New]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->call('askChange', OrderStatus::Processing->value)
            ->set('note', 'coś tam')
            ->call('dismissChange')
            ->assertSet('pendingStatus', null)
            ->assertSet('note', '');
    }

    public function test_panel_lists_only_statuses_from_the_orders_flow(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::BankTransfer,
        ]);

        Livewire::actingAs($seller)
            ->test(OrderStatusManager::class, ['order' => $order])
            ->assertSee('Opłacone')
            ->assertSee('Gotowe do odbioru')
            // „Nowe" nie należy do ścieżki przelewu — nie jest chowane, nie istnieje.
            ->assertDontSee('Nowe');
    }
}
