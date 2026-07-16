<?php

namespace App\Enums;

/**
 * Metody dostawy (spec „Dostawy"). MVP: odbiór osobisty. Kolejne (kurier,
 * dostawa własna) dojdą jako nowe case'y + konfiguracja per-sklep.
 */
enum DeliveryMethod: string
{
    case Pickup = 'pickup';
    case Courier = 'courier';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Odbiór osobisty',
            self::Courier => 'Kurier',
        };
    }

    /**
     * Czy metoda wiąże się z WYSYŁKĄ (a więc i z kosztem dostawy). Odbiór
     * osobisty — nie: nie ma czego dowozić, więc w podsumowaniu nie pokazujemy
     * wiersza „Dostawa" (a „gratis" ma sens dopiero przy wysyłce z progiem
     * darmowej dostawy, np. kurier/paczkomat). Kolejne metody będą shipped=true.
     */
    public function isShipped(): bool
    {
        return match ($this) {
            self::Pickup => false,
            self::Courier => true,
        };
    }
}
