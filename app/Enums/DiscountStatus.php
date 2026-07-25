<?php

namespace App\Enums;

/**
 * Stan kodu rabatowego — WYLICZANY, nigdy nie zapisywany w bazie. Sprzedawca
 * ustawia tylko `is_active`, daty i limit użyć; „wygasł"/„wyczerpany" to wnioski
 * z tych danych i z liczby zamówień. Gdyby stan był kolumną, natychmiast
 * rozjechałby się z rzeczywistością (kod „aktywny", choć minęła data ważności).
 */
enum DiscountStatus: string
{
    case Inactive = 'inactive';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Expired = 'expired';
    case Exhausted = 'exhausted';

    public function label(): string
    {
        return match ($this) {
            self::Inactive => 'Nieaktywny',
            self::Scheduled => 'Zaplanowany',
            self::Active => 'Aktywny',
            self::Expired => 'Wygasł',
            self::Exhausted => 'Wyczerpany',
        };
    }

    /**
     * Czy w tym stanie kod da się wykorzystać w koszyku. Tylko „Aktywny" —
     * pozostałe stany są informacją dla sprzedawcy, dlaczego kod nie działa.
     */
    public function isUsable(): bool
    {
        return $this === self::Active;
    }

    /**
     * Klasy Tailwind plakietki — ta sama konwencja co przy statusach zamówień
     * (miękkie tło + tekst, ciepła paleta panelu). Zielony = działa; szary =
     * skończył się w naturalny sposób; bursztyn = czeka na swój termin.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-100 text-emerald-800',
            self::Scheduled => 'bg-amber-100 text-amber-800',
            self::Inactive => 'bg-stone-200 text-stone-600',
            self::Expired, self::Exhausted => 'bg-stone-100 text-stone-500',
        };
    }
}
