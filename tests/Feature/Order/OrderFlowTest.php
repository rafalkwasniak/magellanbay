<?php

namespace Tests\Feature\Order;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Support\OrderFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ścieżka statusów = funkcja (płatność × dostawa). Dwa scenariusze możliwe dziś:
 * gotówka+odbiór i przelew+odbiór. Trzeci (przelew+wysyłka) dojdzie z modułem
 * dostaw — patrz `OrderFlow::for()`.
 */
class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pay_on_pickup_flow_has_no_payment_steps(): void
    {
        $flow = OrderFlow::for(PaymentMethod::PayOnPickup, DeliveryMethod::Pickup);

        $this->assertSame([
            OrderStatus::New,
            OrderStatus::Processing,
            OrderStatus::ReadyForPickup,
            OrderStatus::Completed,
        ], $flow->statuses());

        // Płaci przy odbiorze — nie ma czego oczekiwać ani potwierdzać.
        $this->assertFalse($flow->includes(OrderStatus::AwaitingPayment));
        $this->assertFalse($flow->includes(OrderStatus::Paid));
    }

    public function test_prepaid_flow_starts_at_awaiting_payment_and_has_no_new(): void
    {
        $flow = OrderFlow::for(PaymentMethod::BankTransfer, DeliveryMethod::Pickup);

        $this->assertSame([
            OrderStatus::AwaitingPayment,
            OrderStatus::Paid,
            OrderStatus::Processing,
            OrderStatus::ReadyForPickup,
            OrderStatus::Completed,
        ], $flow->statuses());

        // „Nowe" żyłoby sekundę — po złożeniu od razu czekamy na wpłatę.
        $this->assertFalse($flow->includes(OrderStatus::New));
        $this->assertSame(OrderStatus::AwaitingPayment, $flow->initial());
    }

    public function test_cancelled_never_belongs_to_a_flow(): void
    {
        foreach (PaymentMethod::cases() as $payment) {
            $flow = OrderFlow::for($payment, DeliveryMethod::Pickup);

            // Anulowanie to przerwanie realizacji, nie jej krok.
            $this->assertFalse($flow->includes(OrderStatus::Cancelled));
            $this->assertNull($flow->next(OrderStatus::Cancelled));
        }
    }

    public function test_next_walks_the_flow_and_stops_at_the_end(): void
    {
        $flow = OrderFlow::for(PaymentMethod::BankTransfer, DeliveryMethod::Pickup);

        $this->assertSame(OrderStatus::Paid, $flow->next(OrderStatus::AwaitingPayment));
        $this->assertSame(OrderStatus::Processing, $flow->next(OrderStatus::Paid));
        $this->assertSame(OrderStatus::ReadyForPickup, $flow->next(OrderStatus::Processing));
        $this->assertSame(OrderStatus::Completed, $flow->next(OrderStatus::ReadyForPickup));

        // Ostatni krok nie ma następnika.
        $this->assertNull($flow->next(OrderStatus::Completed));
    }

    public function test_next_returns_null_for_status_outside_the_flow(): void
    {
        $flow = OrderFlow::for(PaymentMethod::PayOnPickup, DeliveryMethod::Pickup);

        // „Opłacone" nie należy do tej ścieżki — nie ma z czego iść dalej.
        $this->assertNull($flow->next(OrderStatus::Paid));
    }

    public function test_flow_comes_from_the_order_snapshot(): void
    {
        $order = Order::factory()->create([
            'payment_method' => PaymentMethod::BankTransfer,
            'delivery_method' => DeliveryMethod::Pickup,
        ]);

        $this->assertSame(OrderStatus::AwaitingPayment, $order->flow()->initial());
    }
}
