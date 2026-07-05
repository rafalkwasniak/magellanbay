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

    /**
     * Klasy Tailwind plakietki statusu (miękkie tło + tekst) — jedno źródło
     * kolorów dla listy i szczegółu zamówienia. Ciepła paleta panelu.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::New => 'bg-amber-100 text-amber-800',
            self::AwaitingPayment => 'bg-orange-100 text-orange-800',
            self::Paid => 'bg-emerald-100 text-emerald-800',
            self::Processing => 'bg-sky-100 text-sky-800',
            self::ReadyForPickup => 'bg-violet-100 text-violet-800',
            self::Shipped => 'bg-indigo-100 text-indigo-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-stone-200 text-stone-600',
        };
    }
}
