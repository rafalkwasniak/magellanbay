<?php

namespace App\Enums;

/**
 * Rodzaj rabatu, jaki daje kod. Dwa pierwsze zdejmują pieniądze z PRODUKTÓW
 * (nigdy z wysyłki — to zasada całego modułu), trzeci robi dokładnie odwrotnie:
 * nie rusza produktów, zeruje koszt dostawy.
 *
 * `value` w kodzie znaczy więc różne rzeczy zależnie od typu (procent / złotówki
 * / nic) — dlatego pytanie „czy ten typ w ogóle potrzebuje wartości?" jest tutaj,
 * a nie rozsiane po formularzach.
 */
enum DiscountType: string
{
    case Percent = 'percent';
    case Amount = 'amount';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Procent',
            self::Amount => 'Kwota',
            self::FreeShipping => 'Darmowa wysyłka',
        };
    }

    /**
     * Czy rabat zdejmuje pieniądze z produktów. Rozstrzyga, od czego liczymy
     * podstawę (koszyk / linia produktu) i czy w ogóle dotykamy `items_total`.
     */
    public function appliesToItems(): bool
    {
        return $this !== self::FreeShipping;
    }

    /**
     * Czy rabat zeruje koszt dostawy. Świadomy wyjątek od reguły „kody działają
     * tylko na produkty": darmowa wysyłka to najczęściej oczekiwany kod w sklepie,
     * a mechanicznie jest czymś zupełnie innym niż zniżka na towar.
     */
    public function appliesToShipping(): bool
    {
        return $this === self::FreeShipping;
    }

    /**
     * Czy typ wymaga podania wartości. Darmowa wysyłka nie ma czego przyjąć —
     * jej „wartość" wynika z kosztu dostawy w konkretnym zamówieniu.
     */
    public function requiresValue(): bool
    {
        return $this !== self::FreeShipping;
    }
}
