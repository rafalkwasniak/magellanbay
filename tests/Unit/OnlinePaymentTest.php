<?php

namespace Tests\Unit;

use App\Enums\DeliveryMethod;
use App\Enums\IntegrationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Support\OrderFlow;
use PHPUnit\Framework\TestCase;

/**
 * Fundament płatności online (Faza 1): metoda „online" to przedpłata, która
 * reużywa istniejącej ścieżki przelewu — bez wymyślania nowych statusów. Cała
 * różnica wobec przelewu to sposób dojścia do „Opłacone" (webhook), nie kształt
 * ścieżki. Ten test pilnuje, że dodanie case'a nie rozjechało tej reguły.
 */
class OnlinePaymentTest extends TestCase
{
    public function test_online_payment_is_prepaid(): void
    {
        $this->assertTrue(PaymentMethod::Online->isPrepaid());
    }

    public function test_online_payment_has_a_human_label(): void
    {
        $this->assertNotSame('', PaymentMethod::Online->label());
    }

    public function test_online_payment_reuses_the_prepaid_flow(): void
    {
        $flow = OrderFlow::for(PaymentMethod::Online, DeliveryMethod::Pickup);

        // Start w „Oczekuje na płatność", jest krok „Opłacone", nie ma „Nowe" —
        // czyli dokładnie ścieżka przedpłaty, ta sama co przelew tradycyjny.
        $this->assertSame(OrderStatus::AwaitingPayment, $flow->initial());
        $this->assertTrue($flow->includes(OrderStatus::Paid));
        $this->assertFalse($flow->includes(OrderStatus::New));
    }

    public function test_online_and_bank_transfer_share_the_same_path(): void
    {
        $online = OrderFlow::for(PaymentMethod::Online, DeliveryMethod::Courier)->statuses();
        $transfer = OrderFlow::for(PaymentMethod::BankTransfer, DeliveryMethod::Courier)->statuses();

        $this->assertSame($transfer, $online);
    }

    public function test_payments_integration_type_has_a_label(): void
    {
        $this->assertNotSame('', IntegrationType::Payments->label());
    }
}
