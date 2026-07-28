<?php

namespace App\Services;

use App\Exceptions\AiQuotaExceededException;
use App\Models\AiUsage;
use App\Models\Shop;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tygodniowy limit zadań AI na sklep. Powód istnienia: przy throttlingu 30/min
 * jedno konto mogłoby wygenerować ~43 tys. wywołań dziennie (≈ 60 zł), a darmowy
 * Kram nie przynosi ani grosza. Samo wywołanie kosztuje ułamek grosza, więc
 * limity są hojne — chronią przed pętlą i skryptem, nie przed sprzedawcą.
 *
 * JEDNOSTKĄ JEST ZADANIE, NIE WYWOŁANIE. Długi tekst poprawiany „przez AI" dzieli
 * się w przeglądarce na kilkanaście fragmentów, ale dla sprzedawcy to jedno
 * kliknięcie i tak ma się liczyć. Fragmenty jednego zadania niosą wspólny
 * identyfikator; jednostkę pobieramy tylko przy jego PIERWSZYM wystąpieniu.
 * Klient, który podrzuciłby nowy identyfikator do każdego fragmentu, zużyłby
 * WIĘCEJ jednostek — obejście działa na jego niekorzyść.
 */
class AiQuota
{
    /**
     * Jak długo pamiętamy identyfikator zadania. Musi z zapasem przykryć czas
     * poprawiania najdłuższego pola (kilkanaście fragmentów po kilka sekund).
     */
    private const TASK_TTL_MINUTES = 30;

    /**
     * Zużycie zapamiętane na czas żądania: [shop:period => liczba zadań].
     *
     * @var array<string, int>
     */
    private array $cachedUsage = [];

    /**
     * Pobiera jednostkę limitu dla sklepu. `$taskId` scala fragmenty jednego
     * kliknięcia w jedno zadanie (null = zadanie samodzielne, np. opis SEO).
     *
     * @throws AiQuotaExceededException gdy limit na bieżący tydzień jest wyczerpany
     */
    public function consume(Shop $shop, ?string $taskId = null): void
    {
        if ($taskId !== null && ! $this->isNewTask($shop, $taskId)) {
            return;     // kolejny fragment tego samego zadania — już policzone
        }

        $limit = self::limitFor($shop);

        if ($this->used($shop) >= $limit) {
            throw new AiQuotaExceededException($limit, self::resetsAt());
        }

        $this->increment($shop);
    }

    /**
     * Ile zadań sklep może jeszcze wykonać w tym tygodniu.
     */
    public function remaining(Shop $shop): int
    {
        return max(0, self::limitFor($shop) - $this->used($shop));
    }

    /**
     * Zużycie w bieżącym oknie.
     *
     * Wynik pamiętany na czas ŻĄDANIA: licznik trafia obok każdego przycisku AI,
     * a formularz produktu ma ich kilka — bez tego to samo zapytanie szłoby do
     * bazy kilka razy na jedno wyświetlenie strony.
     */
    public function used(Shop $shop): int
    {
        $key = $shop->id.':'.self::currentPeriod();

        return $this->cachedUsage[$key] ??= (int) AiUsage::where('shop_id', $shop->id)
            ->where('period', self::currentPeriod())
            ->value('tasks');
    }

    /**
     * Limit z uprawnień sklepu (snapshot pakietu, z możliwością ręcznego
     * podbicia przez admina — jak `max_products`).
     */
    public static function limitFor(Shop $shop): int
    {
        return (int) $shop->entitlement('ai_weekly_limit');
    }

    /**
     * Klucz okna: numer tygodnia ISO, np. `2026-W31`. Tydzień zaczyna się w
     * poniedziałek, a format ISO nie rozjeżdża się na przełomie roku.
     */
    public static function currentPeriod(?CarbonInterface $at = null): string
    {
        return ($at ?? Carbon::now())->format('o-\WW');
    }

    /**
     * Moment odnowienia limitu — początek najbliższego poniedziałku.
     */
    public static function resetsAt(?CarbonInterface $at = null): CarbonInterface
    {
        return ($at ?? Carbon::now())->copy()->startOfWeek()->addWeek();
    }

    /**
     * Czy ten identyfikator zadania widzimy po raz pierwszy. Znacznik trzymamy w
     * cache, bo to dane ulotne — po tygodniu nie mają żadnej wartości.
     */
    private function isNewTask(Shop $shop, string $taskId): bool
    {
        // `add()` jest atomowe: przy dwóch fragmentach wysłanych równocześnie
        // tylko jeden dostanie `true`, więc zadanie policzy się raz.
        return Cache::add('ai-task:'.$shop->id.':'.$taskId, true, now()->addMinutes(self::TASK_TTL_MINUTES));
    }

    /**
     * Atomowy inkrement licznika okna — bez odczytu i zapisu w dwóch krokach,
     * które przy równoczesnych żądaniach gubiłyby zliczenia.
     */
    private function increment(Shop $shop): void
    {
        $period = self::currentPeriod();
        $now = now();

        // Pamięć podręczna żądania przestaje być prawdziwa w chwili inkrementu.
        unset($this->cachedUsage[$shop->id.':'.$period]);

        DB::table('ai_usages')->upsert(
            [[
                'shop_id' => $shop->id,
                'period' => $period,
                'tasks' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['shop_id', 'period'],
            ['tasks' => DB::raw('tasks + 1'), 'updated_at' => DB::raw("'".$now->toDateTimeString()."'")],
        );
    }
}
