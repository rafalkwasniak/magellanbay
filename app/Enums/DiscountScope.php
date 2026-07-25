<?php

namespace App\Enums;

/**
 * Na co działa kod: na całą wartość produktów w koszyku, czy na jeden wskazany
 * produkt (wtedy podstawą rabatu jest wartość TEJ linii, nie całego koszyka).
 *
 * Zakres jest niezależny od progu „minimalna wartość koszyka" — próg zawsze
 * patrzy na całe zamówienie (bez wysyłki), nawet gdy rabat schodzi z jednej
 * pozycji.
 */
enum DiscountScope: string
{
    case Cart = 'cart';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::Cart => 'Cały koszyk',
            self::Product => 'Wybrany produkt',
        };
    }

    /**
     * Czy zakres wymaga wskazania produktu. Kod bez wskazanego produktu nie ma
     * czego przecenić, więc to warunek zapisu, nie kosmetyka formularza.
     */
    public function requiresProduct(): bool
    {
        return $this === self::Product;
    }
}
