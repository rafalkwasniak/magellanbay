<?php

namespace App\Support;

use App\Models\ProductPriceHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Wyliczenie „najniższej ceny z 30 dni przed obniżką" (dyrektywa Omnibus).
 *
 * Liczymy najniższą cenę, która obowiązywała w ciągu 30 dni *poprzedzających*
 * wejście bieżącej ceny — własny okres bieżącej ceny jest wykluczony. Dzięki
 * temu:
 *  - tuż po obniżce zwracamy wcześniejszą (wyższą) cenę — wymóg ustawowy,
 *  - gdy bieżąca cena obowiązuje już ponad 30 dni, okno jest puste i zwracamy
 *    null (obniżka „utrwaliła się", nie ma czego ujawniać),
 *  - przy podwyżce wynik jest niższy od bieżącej ceny i zostaje odfiltrowany
 *    w warstwie wyżej (Product::lowestPriceLast30Days).
 */
class OmnibusPrice
{
    public const int WINDOW_DAYS = 30;

    /**
     * Najniższa cena brutto obowiązująca w oknie [now − 30 dni, wejście bieżącej ceny).
     * Zwraca null, gdy w tym oknie nie było żadnej wcześniejszej ceny.
     *
     * @param  Collection<int, ProductPriceHistory>  $history  wpisy historii ceny produktu
     */
    public static function lowestBeforeCurrent(Collection $history, CarbonInterface $now): ?float
    {
        if ($history->isEmpty()) {
            return null;
        }

        $rows = $history->sortBy('recorded_at')->values();
        $changeDate = $rows->last()->recorded_at;
        $windowStart = $now->copy()->subDays(self::WINDOW_DAYS);

        // Bieżąca cena obowiązuje już ponad 30 dni — okno przed nią jest puste.
        if ($changeDate->lessThanOrEqualTo($windowStart)) {
            return null;
        }

        $count = $rows->count();
        $lowest = null;

        foreach ($rows as $i => $row) {
            $start = $row->recorded_at;
            $end = ($i + 1 < $count) ? $rows[$i + 1]->recorded_at : $now;

            // Przedział obowiązywania [start, end) przecięty z oknem
            // [windowStart, changeDate). Bieżąca cena (start == changeDate)
            // wypada poza oknem i nie jest brana pod uwagę.
            if ($end->greaterThan($windowStart) && $start->lessThan($changeDate)) {
                $price = (float) $row->price_gross;
                $lowest = $lowest === null ? $price : min($lowest, $price);
            }
        }

        return $lowest;
    }
}
