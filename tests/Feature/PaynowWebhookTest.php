<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\Shop;
use App\Services\PaynowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Webhook Paynow = źródło prawdy o zapłacie. Poprawnie podpisane CONFIRMED
 * przenosi zamówienie z „Oczekuje na płatność" na „Opłacone" (z mailem do
 * kupującego, jak każda zmiana statusu). Broni go podpis: bez klucza sklepu nie
 * da się go sfałszować. Powtórka nie dubluje przejścia, a inne statusy nie ruszają
 * naszego.
 */
class PaynowWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNATURE_KEY = 'SIGN-KEY-1';

    private const URL = '/platnosci/paynow/webhook';

    /**
     * Zamówienie z płatnością online, czekające na wpłatę, wraz z podpiętym
     * kluczem podpisu sklepu i przypisanym `paymentId`.
     */
    private function awaitingOrder(string $paymentId = 'PAY-123'): Order
    {
        $shop = Shop::factory()->create();
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'API', 'signature_key' => self::SIGNATURE_KEY, 'environment' => 'sandbox'],
        ]);

        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::Online,
            'buyer_email' => 'kupujacy@example.com',
        ]);
        $order->forceFill(['payment_external_id' => $paymentId])->save();

        return $order;
    }

    /**
     * Wysyła podpisane powiadomienie z surowym ciałem — podpis musi zgadzać się
     * z bajtami, które faktycznie wysyłamy, więc kodujemy JSON raz i podpisujemy
     * ten sam string.
     */
    private function postSigned(array $payload, ?string $signatureKey = self::SIGNATURE_KEY)
    {
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = $signatureKey !== null
            ? app(PaynowService::class)->sign($signatureKey, $raw)
            : 'brak';

        return $this->call('POST', self::URL, [], [], [], [
            'HTTP_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $raw);
    }

    public function test_confirmed_webhook_marks_order_paid_and_mails_buyer(): void
    {
        $order = $this->awaitingOrder();

        $this->postSigned(['paymentId' => 'PAY-123', 'externalId' => (string) $order->number, 'status' => 'CONFIRMED'])
            ->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('CONFIRMED', $order->payment_status);
        $this->assertCount(1, $order->statusEvents);
        $this->assertSame(1, EmailMessage::count());
    }

    public function test_bad_signature_is_rejected_and_order_untouched(): void
    {
        $order = $this->awaitingOrder();

        $this->call('POST', self::URL, [], [], [], [
            'HTTP_SIGNATURE' => 'sfałszowany-podpis',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['paymentId' => 'PAY-123', 'status' => 'CONFIRMED']))
            ->assertStatus(400);

        $this->assertSame(OrderStatus::AwaitingPayment, $order->refresh()->status);
        $this->assertSame(0, EmailMessage::count());
    }

    public function test_replayed_confirmation_does_not_duplicate_the_transition(): void
    {
        $order = $this->awaitingOrder();
        $payload = ['paymentId' => 'PAY-123', 'status' => 'CONFIRMED'];

        $this->postSigned($payload)->assertOk();
        $this->postSigned($payload)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertCount(1, $order->statusEvents);   // bez dubla na osi
        $this->assertSame(1, EmailMessage::count());   // i bez drugiego maila
    }

    public function test_non_confirmed_status_is_recorded_but_leaves_order_awaiting(): void
    {
        $order = $this->awaitingOrder();

        $this->postSigned(['paymentId' => 'PAY-123', 'status' => 'REJECTED'])->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertSame('REJECTED', $order->payment_status);   // ślad zostaje
        $this->assertSame(0, EmailMessage::count());
    }

    public function test_unknown_payment_is_acknowledged_without_action(): void
    {
        $this->postSigned(['paymentId' => 'NIEZNANE', 'status' => 'CONFIRMED'])->assertOk();

        $this->assertSame(0, EmailMessage::count());
    }

    public function test_missing_fields_are_rejected(): void
    {
        $this->postSigned(['status' => 'CONFIRMED'])->assertStatus(400);
    }
}
