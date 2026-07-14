<?php

namespace App\Enums;

/**
 * Statusy zamówień. Płaska lista wszystkich możliwych stanów — o tym, KTÓRE z
 * nich dotyczą konkretnego zamówienia i w jakiej kolejności, rozstrzyga ścieżka
 * (`App\Support\OrderFlow`), bo zależy to od metody płatności i dostawy.
 *
 * Nie ma tu „Wysłane": jeśli sklep zrealizował zamówienie, to znaczy, że je
 * wysłał — para „Wysłane → Zrealizowane" byłaby krokiem bez treści. Wysyłka
 * dołoży „Gotowe do wysyłki" jako odpowiednik „Gotowe do odbioru".
 */
enum OrderStatus: string
{
    case New = 'new';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case ReadyForPickup = 'ready_for_pickup';
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
            self::Completed => 'Zrealizowane',
            self::Cancelled => 'Anulowane',
        };
    }

    /**
     * Anulowanie to jedyny status nieodwracalny: oddaje towar na stan, więc
     * powrót musiałby zdjąć go ponownie — a mogło go w międzyczasie zabraknąć.
     * Anulowane zamówienie zostaje w systemie wyłącznie informacyjnie.
     */
    public function isTerminal(): bool
    {
        return $this === self::Cancelled;
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
            self::Completed => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-stone-200 text-stone-600',
        };
    }
}
