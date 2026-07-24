<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Okno czasowe działu Analityka. Świadomie okna KROCZĄCE (ostatnie N dni/miesięcy),
 * nie kalendarzowe — żeby porównanie „vs poprzedni okres" było uczciwe: bieżący
 * niepełny miesiąc kalendarzowy zestawiony z pełnym poprzednim zawsze wygląda
 * sztucznie nisko. Kroczące okno tej samej długości omija ten artefakt.
 *
 * Poziom 1 analityki liczy się z danych, które już mamy (orders) — okno to tylko
 * granica `whereBetween` po `created_at`. Bucket (dzień/miesiąc) steruje ziarnem
 * sparkline'u: krótkie okna po dniu, roczne po miesiącu (12 czytelnych punktów).
 */
enum AnalyticsPeriod: string
{
    case Last7Days = '7d';
    case Last30Days = '30d';
    case Last12Months = '12m';

    public static function default(): self
    {
        return self::Last30Days;
    }

    /**
     * Wartość z żądania → enum, z bezpiecznym fallbackiem (nieznany parametr URL
     * nie może wywrócić strony ani ujawnić błędu — po prostu domyślne okno).
     */
    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    public function label(): string
    {
        return match ($this) {
            self::Last7Days => 'Ostatnie 7 dni',
            self::Last30Days => 'Ostatnie 30 dni',
            self::Last12Months => 'Ostatnie 12 miesięcy',
        };
    }

    /**
     * Początek bieżącego okna względem „teraz". Koniec okna = teraz (podaje serwis).
     */
    public function start(CarbonInterface $now): CarbonInterface
    {
        return match ($this) {
            self::Last7Days => $now->copy()->subDays(7),
            self::Last30Days => $now->copy()->subDays(30),
            self::Last12Months => $now->copy()->subMonths(12),
        };
    }

    /**
     * Jednostka kubełka sparkline'u: 'day' dla krótkich okien, 'month' dla rocznego.
     */
    public function bucketUnit(): string
    {
        return $this === self::Last12Months ? 'month' : 'day';
    }
}
