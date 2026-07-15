<?php

namespace App\Services;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

/**
 * Zapisuje do osobnego kanału logu każde zapytanie wolniejsze niż próg z configu
 * — razem z SQL-em, bindings i miejscem w kodzie (plik:linia), żeby dało się
 * wrócić od wolnego zapytania do tego, co je wywołało.
 */
class SlowQueryLogger
{
    public function handle(QueryExecuted $query): void
    {
        $thresholdMs = (int) config('monitoring.slow_query_ms');

        if ($thresholdMs <= 0 || $query->time < $thresholdMs) {
            return;
        }

        Log::channel('slow_queries')->warning('Slow query', [
            'time_ms' => round($query->time, 2),
            'connection' => $query->connectionName,
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'origin' => $this->origin(),
        ]);
    }

    /**
     * Miejsce w kodzie aplikacji, które wywołało zapytanie. Zdarzenie leci przez
     * framework, więc prawdziwy wywołujący siedzi *za* ramkami vendora —
     * domknięcie listenera i ten serwis są przed nimi i trzeba je pominąć.
     */
    private function origin(): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;
        $vendor = $base.'vendor'.DIRECTORY_SEPARATOR;
        $seenVendor = false;

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $file = $frame['file'] ?? null;

            if ($file === null) {
                continue;
            }

            if (str_starts_with($file, $vendor)) {
                $seenVendor = true;

                continue;
            }

            if ($seenVendor && str_starts_with($file, $base)) {
                return substr($file, strlen($base)).':'.($frame['line'] ?? '?');
            }
        }

        return '(unknown origin)';
    }
}
