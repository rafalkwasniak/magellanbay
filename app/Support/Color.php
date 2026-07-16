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

    /**
     * Wariant koloru czytelny NA podanym (jasnym) tle. Odwrotność `readableInkOn`:
     * tam liczymy tusz na kolorze, tu bierzemy sam kolor JAKO tekst na tle. Jeśli
     * kontrast wystarcza — kolor bez zmian; jeśli za jasny (np. pastelowy „kolor
     * przewodni" sklepu na białej karcie maila) — przyciemniamy go krokowo w stronę
     * czerni, aż osiągnie próg WCAG AA (4.5:1). Dzięki temu dekor-kolor może barwić
     * nagłówek maila i jest widoczny nawet wtedy, gdy motyw sklepu jest ciemny (a
     * karta maila biała), przez co token `ink` motywu byłby tu za jasny.
     *
     * @param  string  $color  kolor tekstu „#RRGGBB" (lub bez „#")
     * @param  string  $on  kolor tła „#RRGGBB" (domyślnie biała karta maila)
     */
    public static function readableOn(string $color, string $on = '#ffffff'): string
    {
        [$r, $g, $b] = self::rgb($color);
        $bgLuminance = self::relativeLuminance(...self::rgb($on));

        // Maks. 24 kroki po 10% w stronę czerni — z zapasem starcza na dowolny kolor.
        for ($step = 0; $step <= 24; $step++) {
            $luminance = self::relativeLuminance($r, $g, $b);
            $ratio = (max($luminance, $bgLuminance) + 0.05) / (min($luminance, $bgLuminance) + 0.05);

            if ($ratio >= 4.5) {
                break;
            }

            $r = (int) round($r * 0.9);
            $g = (int) round($g * 0.9);
            $b = (int) round($b * 0.9);
        }

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * „#RRGGBB" → [R, G, B] (0–255).
     *
     * @return array{int, int, int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Względna jasność wg WCAG (z korekcją gamma sRGB) — do liczenia kontrastu.
     */
    private static function relativeLuminance(int $r, int $g, int $b): float
    {
        $channel = static function (int $value): float {
            $v = $value / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }
}
