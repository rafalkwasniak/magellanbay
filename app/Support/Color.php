<?php

namespace App\Support;

/**
 * Drobne operacje na kolorach dla warstwy motywów. Kolor własny sklepu
 * („kolor przewodni") nadpisuje tylko token `brand`; tekst NA tym kolorze
 * (`brand_ink`) musi być czytelny niezależnie od tego, jak jasny/ciemny kolor
 * wybierze sprzedawca — dlatego liczymy go, a nie pozwalamy wybrać.
 *
 * Ta sama formuła żyje po stronie JS (podgląd na żywo w panelu Wygląd) —
 * trzymaj obie w zgodzie, gdyby próg kiedyś się zmienił.
 */
class Color
{
    /**
     * Czarny lub biały tusz — ten, który lepiej czyta się na podanym tle.
     * Jasność liczona percepcyjnie (0.299R + 0.587G + 0.114B, znormalizowana
     * do 0–1). Powyżej 0.6 kolor jest jasny → ciemny tekst; poniżej → biały.
     *
     * @param  string  $hex  Kolor tła w formacie „#RRGGBB" (lub bez „#").
     */
    public static function readableInkOn(string $hex): string
    {
        $hex = ltrim($hex, '#');

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.6 ? '#1A1A1A' : '#FFFFFF';
    }
}
