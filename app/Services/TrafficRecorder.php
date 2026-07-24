<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Zapis ruchu do dziennego agregatu `shop_stats` — atomowy inkrement licznika,
 * bez rosnącej tabeli zdarzeń. Wzorzec „increment, a gdy brak wiersza — wstaw i
 * increment": `increment()` to jedno `UPDATE col = col + n` (atomowe), a przy
 * pierwszej odsłonie dnia dokładamy wiersz przez `insertOrIgnore` (odporne na
 * wyścig dwóch równoległych pierwszych odsłon). Wszystko cross-DB (MySQL na
 * produkcji, SQLite w testach) — bez driver-specyficznego ON DUPLICATE KEY.
 *
 * Nazwę metryki bierzemy wyłącznie z whitelisty — kolumna trafia do zapytania,
 * więc nie może pochodzić z danych żądania.
 */
class TrafficRecorder
{
    private const METRICS = ['visits', 'product_views'];

    public function record(int $shopId, string $metric, int $amount = 1): void
    {
        if (! in_array($metric, self::METRICS, true) || $amount < 1) {
            return;
        }

        $date = now()->toDateString();

        $updated = DB::table('shop_stats')
            ->where('shop_id', $shopId)
            ->where('date', $date)
            ->increment($metric, $amount);

        if ($updated === 0) {
            DB::table('shop_stats')->insertOrIgnore([
                'shop_id' => $shopId,
                'date' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('shop_stats')
                ->where('shop_id', $shopId)
                ->where('date', $date)
                ->increment($metric, $amount);
        }
    }
}
