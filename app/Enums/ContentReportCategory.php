<?php

namespace App\Enums;

/**
 * Rodzaj zarzutu w zgłoszeniu treści bezprawnej (art. 16 DSA).
 *
 * Kategorie są po to, żeby zgłaszający nie musiał znać kwalifikacji prawnej —
 * wybiera to, co widzi. Wartości po angielsku (warstwa kodu), etykiety po polsku
 * (warstwa widoczna), zgodnie z konwencją projektu.
 */
enum ContentReportCategory: string
{
    case Counterfeit = 'counterfeit';
    case Copyright = 'copyright';
    case ProhibitedGoods = 'prohibited_goods';
    case Fraud = 'fraud';
    case PersonalData = 'personal_data';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Counterfeit => 'Podróbka lub naruszenie znaku towarowego',
            self::Copyright => 'Naruszenie praw autorskich (zdjęcie, opis, grafika)',
            self::ProhibitedGoods => 'Towar zakazany albo wymagający zezwolenia',
            self::Fraud => 'Oszustwo lub wprowadzanie w błąd',
            self::PersonalData => 'Cudze dane osobowe opublikowane bez zgody',
            self::Other => 'Inne',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
