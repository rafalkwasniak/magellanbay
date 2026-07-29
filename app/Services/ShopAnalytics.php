<?php

namespace App\Services;

use App\Enums\AnalyticsPeriod;
use App\Enums\OrderStatus;
use App\Enums\SaleUnit;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\ShopStat;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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

        $unit = $period->bucketUnit();
        $buckets = $this->buildBuckets($start, $now, $unit);
        $keys = array_column($buckets, 'key');
        $grouped = $current->groupBy(fn ($order) => $this->bucketKey($order->created_at, $unit));

        return [
            'period' => $period,
            'kpis' => [
                'revenue' => [
                    'value' => $this->revenue($current),
                    'delta' => $this->delta($this->revenue($current), $this->revenue($previous)),
                    'spark' => $this->sparkline($keys, $grouped, fn (Collection $rows) => $this->revenue($rows)),
                ],
                'orders' => [
                    'value' => (float) $current->count(),
                    'delta' => $this->delta((float) $current->count(), (float) $previous->count()),
                    'spark' => $this->sparkline($keys, $grouped, fn (Collection $rows) => (float) $rows->count()),
                ],
                'aov' => [
                    'value' => $this->aov($current),
                    'delta' => $this->delta($this->aov($current), $this->aov($previous)),
                    'spark' => $this->sparkline($keys, $grouped, fn (Collection $rows) => $this->aov($rows)),
                ],
                'customers' => [
                    'value' => (float) $this->customers($current),
                    'delta' => $this->delta((float) $this->customers($current), (float) $this->customers($previous)),
                    'spark' => $this->sparkline($keys, $grouped, fn (Collection $rows) => (float) $this->customers($rows)),
                ],
            ],
            // Seria czasowa dla wykresu „sprzedaż w czasie" (CP-B): jeden słupek na
            // kubełek, z etykietą osi (krótka) i pełnym opisem do tooltipa.
            'series' => array_map(fn (array $bucket) => [
                'label' => $bucket['label'],
                'full' => $bucket['full'],
                'revenue' => $grouped->has($bucket['key']) ? $this->revenue($grouped->get($bucket['key'])) : 0.0,
                'orders' => $grouped->has($bucket['key']) ? $grouped->get($bucket['key'])->count() : 0,
            ], $buckets),
            // CP-C: co się sprzedaje. Bestsellery wg obrotu (z pozycji zamówień) oraz
            // podział zamówień wg metody płatności i dostawy (udział w liczbie zamówień).
            'bestsellers' => $this->bestsellers($shop, $start, $now),
            'payment_split' => $this->split($current, 'payment_method'),
            'delivery_split' => $this->split($current, 'delivery_method'),
            // CP-D: klienci. Nowi vs powracający (wg historii sprzed okna) i najlepsi
            // klienci wg wartości zakupów w oknie.
            'customers_breakdown' => $this->customerBreakdown($shop, $current, $start),
            'top_customers' => $this->topCustomers($shop, $start, $now),
            // Poziom 2: ruch z agregatu `shop_stats` + konwersja (zamówienia/wizyty).
            'traffic' => $this->traffic($shop, $start, $now, $current->count()),
        ];
    }

    /**
     * Ruch w oknie z dziennego agregatu `shop_stats`: wizyty i wyświetlenia
     * produktów (suma dni), oraz konwersja = zamówienia / wizyty. Konwersja null
     * przy zerze wizyt (brak bazy — starsze okresy sprzed uruchomienia zliczania
     * ruchu), żeby UI pokazał „—" zamiast dzielić przez zero.
     *
     * @return array{visits: int, product_views: int, conversion: float|null}
     */
    private function traffic(Shop $shop, CarbonInterface $start, CarbonInterface $end, int $ordersCount): array
    {
        $row = ShopStat::query()
            ->where('shop_id', $shop->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COALESCE(SUM(visits), 0) as visits, COALESCE(SUM(product_views), 0) as product_views')
            ->first();

        $visits = (int) ($row->visits ?? 0);

        return [
            'visits' => $visits,
            'product_views' => (int) ($row->product_views ?? 0),
            'conversion' => $visits > 0 ? round($ordersCount / $visits * 100, 1) : null,
        ];
    }

    /**
     * Nowi vs powracający wśród klientów, którzy kupili w oknie. „Powracający" =
     * miał jakiekolwiek nie-anulowane zamówienie PRZED początkiem okna; reszta to
     * „nowi" (pierwszy zakup w tym oknie). Jedno dodatkowe lekkie zapytanie o same
     * e-maile sprzed okna; dopasowanie po znormalizowanym adresie w PHP.
     *
     * @param  Collection<int, \App\Models\Order>  $current
     * @return array{new: int, returning: int, returning_rate: float|null}
     */
    private function customerBreakdown(Shop $shop, Collection $current, CarbonInterface $start): array
    {
        $windowEmails = $current
            ->map(fn ($order) => mb_strtolower(trim((string) $order->buyer_email)))
            ->filter()
            ->unique();

        $total = $windowEmails->count();

        if ($total === 0) {
            return ['new' => 0, 'returning' => 0, 'returning_rate' => null];
        }

        $priorEmails = $shop->orders()
            ->countedAsSale()
            ->where('created_at', '<', $start)
            ->get(['buyer_email'])
            ->map(fn ($order) => mb_strtolower(trim((string) $order->buyer_email)))
            ->filter()
            ->unique();

        $returning = $windowEmails->intersect($priorEmails)->count();
        $new = $total - $returning;

        return [
            'new' => $new,
            'returning' => $returning,
            'returning_rate' => $returning / $total,
        ];
    }

    /**
     * Najlepsi klienci w oknie wg LICZBY KUPIONYCH PRODUKTÓW (sztuk), nie zamówień
     * ani kwoty — 20 sztuk w jednym zamówieniu > 3 osobne zamówienia po 1 sztuce.
     * Sumujemy ilości z pozycji zamówień; jednostki liczymy 1:1 (1 kg = 1 szt.),
     * a wynik podłoga do liczby całkowitej. Grupujemy po znormalizowanym e-mailu
     * kupującego (z zamówienia pozycji). Etykieta = imię i nazwisko, a gdy brak —
     * sam e-mail.
     *
     * @return list<array{label: string, items: int}>
     */
    private function topCustomers(Shop $shop, CarbonInterface $from, CarbonInterface $to, int $limit = 5): array
    {
        $items = OrderItem::query()
            ->whereHas('order', fn (Builder $query) => $query
                ->where('shop_id', $shop->id)
                ->where('status', '!=', OrderStatus::Cancelled->value)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to))
            ->with(['order' => fn ($query) => $query->select('id', 'buyer_email', 'buyer_name', 'buyer_surname')])
            ->get(['order_id', 'quantity', 'returned_quantity']);

        return $items
            ->filter(fn (OrderItem $item) => filled($item->order?->buyer_email))
            ->groupBy(fn (OrderItem $item) => mb_strtolower(trim((string) $item->order->buyer_email)))
            ->map(function (Collection $rows) {
                $order = $rows->first()->order;
                $name = trim(($order->buyer_name ?? '').' '.($order->buyer_surname ?? ''));

                return [
                    'label' => $name !== '' ? $name : (string) $order->buyer_email,
                    // Sztuki zwrócone nie są sprzedażą — liczymy ilość efektywną.
                    'items' => (int) floor((float) $rows->sum(fn (OrderItem $r) => $r->effectiveQuantity())),
                ];
            })
            ->sortByDesc('items')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Top produkty wg LICZBY SPRZEDANYCH SZTUK w oknie — „bestseller" to to, co
     * najlepiej się sprzedaje (ilościowo), nie najdroższe. Z migawek pozycji
     * zamówień (`order_items`), więc wierne nawet po zmianie/usunięciu produktu.
     * Grupujemy po produkcie (a gdy pozycja bez `product_id` — po nazwie migawki).
     * Anulowane zamówienia odpadają. `unit` niesie jednostkę (szt./kg) do
     * sformatowania ilości w widoku.
     *
     * @return list<array{name: string, quantity: float, unit: string}>
     */
    private function bestsellers(Shop $shop, CarbonInterface $from, CarbonInterface $to, int $limit = 5): array
    {
        $items = OrderItem::query()
            ->whereHas('order', fn (Builder $query) => $query
                ->where('shop_id', $shop->id)
                ->where('status', '!=', OrderStatus::Cancelled->value)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to))
            ->get(['product_id', 'name', 'quantity', 'returned_quantity', 'sale_unit']);

        return $items
            ->groupBy(fn (OrderItem $item) => $item->product_id ?? 'name:'.$item->name)
            ->map(fn (Collection $rows) => [
                'name' => (string) $rows->first()->name,
                'quantity' => (float) $rows->sum(fn (OrderItem $r) => $r->effectiveQuantity()),
                'unit' => ($rows->first()->sale_unit ?? SaleUnit::Piece)->value,
            ])
            ->sortByDesc('quantity')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Podział zamówień wg wartości enuma (`payment_method`/`delivery_method`):
     * liczba, obrót i udział w liczbie zamówień. Sortowane malejąco. Pusta lista
     * przy braku zamówień (widok pokaże stan pusty).
     *
     * @param  Collection<int, \App\Models\Order>  $orders
     * @return list<array{label: string, count: int, revenue: float, share: float}>
     */
    private function split(Collection $orders, string $attribute): array
    {
        $total = $orders->count();

        if ($total === 0) {
            return [];
        }

        return $orders
            ->groupBy(fn ($order) => $order->{$attribute}->value)
            ->map(fn (Collection $rows) => [
                'label' => $rows->first()->{$attribute}->label(),
                'count' => $rows->count(),
                'revenue' => $this->revenue($rows),
                'share' => $rows->count() / $total,
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
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
            ->get(['total_gross', 'buyer_email', 'buyer_name', 'buyer_surname', 'created_at', 'payment_method', 'delivery_method']);
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
     * Uporządkowane kubełki od początku okna do teraz (z dziurami = kubełki bez
     * zamówień, żeby oś czasu miała równe odstępy). Każdy niesie klucz grupujący,
     * krótką etykietę osi i pełny opis do tooltipa. Etykiety po polsku (locale pl).
     *
     * @return list<array{key: string, label: string, full: string}>
     */
    private function buildBuckets(CarbonInterface $start, CarbonInterface $end, string $unit): array
    {
        $buckets = [];
        $cursor = $unit === 'month' ? $start->copy()->startOfMonth() : $start->copy()->startOfDay();
        $last = $this->bucketKey($end, $unit);

        do {
            $key = $this->bucketKey($cursor, $unit);
            $buckets[] = [
                'key' => $key,
                'label' => $unit === 'month' ? $cursor->translatedFormat('M y') : $cursor->translatedFormat('j.m'),
                'full' => $unit === 'month' ? $cursor->translatedFormat('F Y') : $cursor->translatedFormat('j F Y'),
            ];
            $cursor = $unit === 'month' ? $cursor->addMonth() : $cursor->addDay();
        } while ($key !== $last && count($buckets) < 400);

        return $buckets;
    }

    private function bucketKey(CarbonInterface $moment, string $unit): string
    {
        return $unit === 'month' ? $moment->format('Y-m') : $moment->format('Y-m-d');
    }

    /**
     * Sparkline = wartość metryki w każdym kubełku, w kolejności czasu. Kubełek bez
     * zamówień daje 0.0, żeby wykres nie „ściskał" osi czasu.
     *
     * @param  list<string>  $keys
     * @param  Collection<string, Collection<int, \App\Models\Order>>  $grouped
     * @param  callable(Collection<int, \App\Models\Order>): float  $metric
     * @return list<float>
     */
    private function sparkline(array $keys, Collection $grouped, callable $metric): array
    {
        return array_map(
            fn (string $key) => $grouped->has($key) ? $metric($grouped->get($key)) : 0.0,
            $keys,
        );
    }
}
