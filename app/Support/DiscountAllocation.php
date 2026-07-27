<?php

namespace App\Support;

/**
 * Rozbicie kwoty rabatu na pozycje zamówienia. Potrzebne, bo rabat jest jedną
 * kwotą na całe zamówienie, a VAT liczy się per stawka — bez podziału nie da się
 * wystawić poprawnej faktury, gdy w koszyku są towary z różnymi stawkami.
 *
 * Podział jest proporcjonalny do wartości brutto linii i domyka się CO DO GROSZA
 * metodą największych reszt: każda pozycja dostaje część całkowitą, a groszowe
 * końcówki trafiają do linii o największych resztach. Naiwne zaokrąglanie każdej
 * linii osobno potrafi zgubić albo dorzucić grosz — a wtedy suma pozycji nie zgadza się
 * z kwotą rabatu na zamówieniu i faktura się nie spina.
 */
class DiscountAllocation
{
    /**
     * Dzieli kwotę na części proporcjonalne do wag, w groszach, bez gubienia reszty.
     *
     * @param  array<int|string, float>  $weights  wartości brutto linii
     * @return array<int|string, float>  kwota rabatu przypadająca na każdą linię
     */
    public static function spread(float $amount, array $weights): array
    {
        $zeros = array_map(fn () => 0.0, $weights);
        $totalWeight = array_sum($weights);

        if ($amount <= 0 || $totalWeight <= 0) {
            return $zeros;
        }

        $cents = (int) round($amount * 100);
        $parts = [];
        $remainders = [];
        $assigned = 0;

        foreach ($weights as $key => $weight) {
            $exact = $cents * max(0.0, $weight) / $totalWeight;
            $parts[$key] = (int) floor($exact);
            $remainders[$key] = $exact - $parts[$key];
            $assigned += $parts[$key];
        }

        // Nierozdzielone grosze idą do linii z największą resztą — przy remisie
        // decyduje kolejność pozycji, żeby wynik był powtarzalny.
        arsort($remainders);
        $left = $cents - $assigned;

        foreach (array_keys($remainders) as $key) {
            if ($left <= 0) {
                break;
            }

            $parts[$key]++;
            $left--;
        }

        // Kolejność kluczy wraca do wejściowej — wołający dopasowuje po indeksie.
        $result = [];
        foreach (array_keys($weights) as $key) {
            $result[$key] = $parts[$key] / 100;
        }

        return $result;
    }
}
