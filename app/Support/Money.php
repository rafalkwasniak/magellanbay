<?php

namespace App\Support;

/**
 * Formatowanie kwot w PLN dla storefrontu: „1 234,56 zł" (spacja jako separator
 * tysięcy, przecinek dziesiętny — konwencja polska). Jedno miejsce prawdy, żeby
 * cena wyglądała tak samo na kafelku, karcie produktu i w koszyku.
 */
class Money
{
    public static function pln(float|string $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ').' zł';
    }
}
