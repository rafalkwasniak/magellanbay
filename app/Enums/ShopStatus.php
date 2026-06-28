<?php

namespace App\Enums;

/**
 * Cykl życia sklepu. Przy rejestracji powstaje szkic (Draft) — sklep ma już
 * zarezerwowaną subdomenę, ale nie jest jeszcze publiczny. Aktywacja sklepu i
 * publikacja (po uzupełnieniu danych + pierwszym produkcie) przełączą go na
 * Active. Lista będzie rosła wraz z logiką widoczności ze specyfikacji.
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
