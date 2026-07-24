<?php

namespace App\Services;

use App\Enums\AnalyticsPeriod;
use App\Models\Shop;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Analityka Poziomu 1: wszystkie liczby wyliczane Z DANYCH, KTÓRE JUŻ MAMY
 * (`orders`) — wejście na dashboard NIE zapisuje niczego i nie potrzebuje żadnej
 * tabeli śledzenia. Anulowane zamówienia odpadają (scope `countedAsSale`).
 *
 * „Nie obciążać shared hosta": dwa lekkie zapytania (bieżące + poprzednie okno),
 * bucketowanie w PHP zamiast funkcji dat SQL (parytet SQLite w testach ↔ MySQL na
 * produkcji), wynik trzymany krótko w cache. Przy skali sklepów butikowych okno to
 * kilkadziesiąt–kilkaset wierszy, więc pobranie i zsumowanie w PHP jest tańsze niż
 * kombinowanie z driver-specyficznym GROUP BY.
 */
class ShopAnalytics
{
    /** Krótki TTL: dashboard nie musi być co do sekundy świeży, a chroni bazę przy odświeżaniu. */
    private const CACHE_TTL_SECONDS = 120;

    /**
     * @return array{
     *     period: AnalyticsPeriod,
     *     kpis: array<string, array{value: float, delta: float|null, spark: list<float>}>
     * }
     */
    public function for(Shop $shop, AnalyticsPeriod $period): array
    {
        return Cache::remember(
            "analytics:{$shop->id}:{$period->value}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->compute($shop, $period),
        );
    }

    /**
     * @return array{period: AnalyticsPeriod, kpis: array<string, array{value: float, delta: float|null, spark: list<float>}>}
     */
    private function compute(Shop $shop, AnalyticsPeriod $period): array
    {
        $now = now();
        $start = $period->start($now);

        // Poprzednie okno = ta sama długość tuż przed bieżącym (uczciwe „vs poprzedni okres").
        $durationSeconds = $now->getTimestamp() - $start->getTimestamp();
        $prevStart = $start->copy()->subSeconds($durationSeconds);

        $current = $this->orders($shop, $start, $now);
        $previous = $this->orders($shop, $prevStart, $start);

        $buckets = $this->bucketKeys($start, $now, $period->bucketUnit());
        $grouped = $current->groupBy(fn ($order) => $this->bucketKey($order->created_at, $period->bucketUnit()));

        return [
            'period' => $period,
            'kpis' => [
                'revenue' => [
                    'value' => $this->revenue($current),
                    'delta' => $this->delta($this->revenue($current), $this->revenue($previous)),
                    'spark' => $this->sparkline($buckets, $grouped, fn (Collection $rows) => $this->revenue($rows)),
                ],
                'orders' => [
                    'value' => (float) $current->count(),
                    'delta' => $this->delta((float) $current->count(), (float) $previous->count()),
                    'spark' => $this->sparkline($buckets, $grouped, fn (Collection $rows) => (float) $rows->count()),
                ],
                'aov' => [
                    'value' => $this->aov($current),
                    'delta' => $this->delta($this->aov($current), $this->aov($previous)),
                    'spark' => $this->sparkline($buckets, $grouped, fn (Collection $rows) => $this->aov($rows)),
                ],
                'customers' => [
                    'value' => (float) $this->customers($current),
                    'delta' => $this->delta((float) $this->customers($current), (float) $this->customers($previous)),
                    'spark' => $this->sparkline($buckets, $grouped, fn (Collection $rows) => (float) $this->customers($rows)),
                ],
            ],
        ];
    }

    /**
     * Zamówienia liczone jako zakup w oknie [od, do). Pobieramy tylko kolumny
     * potrzebne do metryk — bez dociągania całych rekordów.
     *
     * @return Collection<int, \App\Models\Order>
     */
    private function orders(Shop $shop, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $shop->orders()
            ->countedAsSale()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->get(['total_gross', 'buyer_email', 'created_at']);
    }

    /**
     * @param  Collection<int, \App\Models\Order>  $orders
     */
    private function revenue(Collection $orders): float
    {
        return (float) $orders->sum(fn ($order) => (float) $order->total_gross);
    }

    /**
     * Średni koszyk (AOV) = obrót / liczba zamówień; brak zamówień → 0.
     *
     * @param  Collection<int, \App\Models\Order>  $orders
     */
    private function aov(Collection $orders): float
    {
        $count = $orders->count();

        return $count > 0 ? $this->revenue($orders) / $count : 0.0;
    }

    /**
     * Liczba unikalnych klientów w oknie = unikalne (znormalizowane) adresy e-mail
     * kupujących. Obejmuje i zalogowanych, i gości — e-mail jest wspólnym kluczem.
     *
     * @param  Collection<int, \App\Models\Order>  $orders
     */
    private function customers(Collection $orders): int
    {
        return $orders
            ->map(fn ($order) => mb_strtolower(trim((string) $order->buyer_email)))
            ->filter()
            ->unique()
            ->count();
    }

    /**
     * Zmiana procentowa vs poprzednie okno. Null, gdy brak bazy odniesienia
     * (poprzedni okres = 0) — wtedy „—" zamiast mylącego „+∞%".
     */
    private function delta(float $current, float $previous): ?float
    {
        if ($previous <= 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Uporządkowana lista kluczy kubełków od początku okna do teraz (z dziurami =
     * kubełki bez zamówień, żeby sparkline miał równe odstępy w czasie).
     *
     * @return list<string>
     */
    private function bucketKeys(CarbonInterface $start, CarbonInterface $end, string $unit): array
    {
        $keys = [];
        $cursor = $unit === 'month' ? $start->copy()->startOfMonth() : $start->copy()->startOfDay();
        $last = $this->bucketKey($end, $unit);

        do {
            $key = $this->bucketKey($cursor, $unit);
            $keys[] = $key;
            $cursor = $unit === 'month' ? $cursor->addMonth() : $cursor->addDay();
        } while ($key !== $last && count($keys) < 400);

        return $keys;
    }

    private function bucketKey(CarbonInterface $moment, string $unit): string
    {
        return $unit === 'month' ? $moment->format('Y-m') : $moment->format('Y-m-d');
    }

    /**
     * Sparkline = wartość metryki w każdym kubełku, w kolejności czasu. Kubełek bez
     * zamówień daje 0.0, żeby wykres nie „ściskał" osi czasu.
     *
     * @param  list<string>  $buckets
     * @param  Collection<string, Collection<int, \App\Models\Order>>  $grouped
     * @param  callable(Collection<int, \App\Models\Order>): float  $metric
     * @return list<float>
     */
    private function sparkline(array $buckets, Collection $grouped, callable $metric): array
    {
        return array_map(
            fn (string $key) => $grouped->has($key) ? $metric($grouped->get($key)) : 0.0,
            $buckets,
        );
    }
}
