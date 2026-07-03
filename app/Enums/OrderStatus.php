<?php

namespace App\Enums;

/**
 * Statusy zamówień (spec „Statusy zamówień"). Wspólny zestaw dla wszystkich
 * pakietów; maszynę przejść i powiadomienia budujemy w module statusów. Nowe
 * zamówienie startuje w `New`.
 */
enum OrderStatus: string
{
    case New = 'new';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case ReadyForPickup = 'ready_for_pickup';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nowe',
            self::AwaitingPayment => 'Oczekuje na płatność',
            self::Paid => 'Opłacone',
            self::Processing => 'W realizacji',
            self::ReadyForPickup => 'Gotowe do odbioru',
            self::Shipped => 'Wysłane',
            self::Completed => 'Zrealizowane',
            self::Cancelled => 'Anulowane',
        };
    }
}
