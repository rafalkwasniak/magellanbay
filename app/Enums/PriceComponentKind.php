<?php

namespace App\Enums;

/**
 * Rodzaj składnika ceny pozycji zamówienia.
 *
 * Klient chciał, żeby cena była widoczna „z czterech części": produkt, licencja
 * za logotyp, wykonanie graweru, licencja za grafikę graweru. To NIE są cztery
 * rodzaje — to trzy rodzaje w czterech wystąpieniach. Licencja jest jedna,
 * niezależnie od tego, czy dotyczy logotypu, czy grafiki; różni je etykieta
 * i partner, a nie zachowanie.
 *
 * Rozróżnienie ma znaczenie arytmetyczne: `Licence` podlega regule „suma po
 * firmach, maksimum wewnątrz jednej", pozostałe sumują się zwyczajnie.
 */
enum PriceComponentKind: string
{
    /** Cena samego towaru z katalogu. */
    case Product = 'product';

    /** Dopłata za opcję — wykonanie nadruku, graweru, wybrany wariant. */
    case Option = 'option';

    /** Opłata należna licencjodawcy za użycie jego znaku lub grafiki. */
    case Licence = 'licence';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Produkt',
            self::Option => 'Personalizacja',
            self::Licence => 'Opłata licencyjna',
        };
    }
}
