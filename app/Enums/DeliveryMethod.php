<?php

namespace App\Enums;

/**
 * Metody dostawy (spec „Dostawy"). MVP: odbiór osobisty. Kolejne (kurier,
 * dostawa własna) dojdą jako nowe case'y + konfiguracja per-sklep.
 */
enum DeliveryMethod: string
{
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Odbiór osobisty',
        };
    }
}
