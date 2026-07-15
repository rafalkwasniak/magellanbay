<?php

namespace App\Enums;

/**
 * Kanały zgód marketingowych klienta. Wartość enumu to kolumna `channel` w
 * `customer_consents` (kod po angielsku).
 *
 * Dziś jest tylko e-mail, ale zgoda z założenia jest PER KANAŁ — można chcieć
 * maili i nie chcieć SMS-ów. Dołożenie kanału to nowy `case` tutaj plus wiersz
 * w bazie; bez migracji i bez ruszania `customers`. Telefon zbieramy już dziś
 * (ekran aktywacji), więc SMS nie jest hipotezą.
 */
enum ConsentChannel: string
{
    case Email = 'email';

    /**
     * Czytelna nazwa kanału (UI, panel).
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
        };
    }
}
