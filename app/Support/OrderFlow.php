<?php

namespace App\Support;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;

/**
 * Ścieżka statusów zamówienia = funkcja (metoda płatności × metoda dostawy).
 * Nie ma jednej płaskiej listy statusów dla wszystkich zamówień: przy płatności
 * przy odbiorze nie istnieje „Opłacone", przy przedpłacie nie istnieje „Nowe".
 * Statusy spoza własnej ścieżki dla danego zamówienia po prostu NIE ISTNIEJĄ —
 * nie są chowane w UI, tylko niedostępne (egzekwuje `OrderStatusChanger`).
 *
 * Zasada naczelna: statusów ma być jak najmniej. Każdy musi coś znaczyć dla
 * kupującego — bo każda zmiana wysyła mu maila.
 *
 * `Cancelled` celowo NIE należy do ścieżki: to nie krok realizacji, tylko jej
 * przerwanie. Jest osiągalne z każdego statusu i jest terminalne.
 */
final class OrderFlow
{
    /**
     * @param  list<OrderStatus>  $statuses  kolejność = kolejność realizacji
     */
    private function __construct(private readonly array $statuses) {}

    public static function for(PaymentMethod $payment, DeliveryMethod $delivery): self
    {
        // Wydanie towaru. Wysyłka dołoży tu „Gotowe do wysyłki" — świadomie BEZ
        // osobnego „Wysłane": jeśli sklep zrealizował zamówienie, to znaczy, że
        // je wysłał, więc para „Wysłane → Zrealizowane" byłaby krokiem bez treści.
        // Nowa metoda dostawy wywali tu UnhandledMatchError — i dobrze, ścieżkę
        // trzeba wtedy dopisać świadomie, a nie odziedziczyć po cichu.
        $handover = match ($delivery) {
            DeliveryMethod::Pickup => OrderStatus::ReadyForPickup,
            // Pobraniowe idą tą samą ścieżką co przedpłacone wysyłki: z punktu
            // widzenia sprzedawcy to ta sama praca (spakuj, nadaj, wydrukuj
            // etykietę), a moment zapłaty niesie `delivered_at`, nie status.
            DeliveryMethod::Courier, DeliveryMethod::ParcelLocker,
            DeliveryMethod::CourierCod, DeliveryMethod::ParcelLockerCod => OrderStatus::ReadyForShipment,
        };

        // Przedpłata: brak „Nowego" — zaraz po złożeniu i tak oczekujemy wpłaty,
        // więc ten status żyłby sekundę i zmuszał sprzedawcę do pustego kliknięcia.
        $statuses = $payment->isPrepaid()
            ? [OrderStatus::AwaitingPayment, OrderStatus::Paid, OrderStatus::Processing, $handover, OrderStatus::Completed]
            : [OrderStatus::New, OrderStatus::Processing, $handover, OrderStatus::Completed];

        return new self($statuses);
    }

    public static function forOrder(Order $order): self
    {
        return self::for($order->payment_method, $order->delivery_method);
    }

    /**
     * @return list<OrderStatus>
     */
    public function statuses(): array
    {
        return $this->statuses;
    }

    /**
     * Status, w którym zamówienie startuje po złożeniu.
     */
    public function initial(): OrderStatus
    {
        return $this->statuses[0];
    }

    public function includes(OrderStatus $status): bool
    {
        return in_array($status, $this->statuses, true);
    }

    /**
     * Kolejny krok ścieżki = status sugerowany sprzedawcy. Null na ostatnim
     * kroku i dla statusów spoza ścieżki (m.in. `Cancelled`).
     */
    public function next(OrderStatus $from): ?OrderStatus
    {
        $index = array_search($from, $this->statuses, true);

        if ($index === false) {
            return null;
        }

        return $this->statuses[$index + 1] ?? null;
    }
}
