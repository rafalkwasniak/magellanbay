<?php

namespace App\Enums;

/**
 * Status = publiczna widoczność sklepu, napędzana wyłącznie produktami:
 * Draft (Szkic, ukryty) = brak aktywnych produktów; Active (Aktywny, widoczny)
 * = ≥1 aktywny produkt. Przy rejestracji powstaje Draft (zarezerwowana
 * subdomena, pusty sklep). Przejścia w obie strony wykonuje automatycznie
 * App\Observers\ProductObserver → Shop::refreshVisibility() — nie ustawiamy
 * statusu ręcznie. Ewentualne zawieszenie przez admina to przyszła, osobna
 * (prostopadła) flaga, nie ten enum.
 */
enum ShopStatus: string
{
    case Draft = 'draft';
    case Active = 'active';

    /**
     * Czytelna nazwa statusu (do UI).
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Szkic',
            self::Active => 'Aktywny',
        };
    }
}
