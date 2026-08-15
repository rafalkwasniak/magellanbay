<?php

namespace App\Enums;

/**
 * Stan rozpatrzenia zgłoszenia treści bezprawnej.
 *
 * Trzy stany, świadomie bez „w toku": przy jednoosobowej obsłudze stan pośredni
 * i tak nikt by nie klikał, a zgłoszenie albo czeka, albo jest rozstrzygnięte.
 * Rozszerzenie listy = jeden case tutaj (rozstrzygnięcie zawsze z uzasadnieniem,
 * bo art. 17 DSA wymaga podania powodów).
 */
enum ContentReportStatus: string
{
    case New = 'new';
    case Upheld = 'upheld';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nowe',
            self::Upheld => 'Uznane',
            self::Rejected => 'Odrzucone',
        };
    }

    public function isDecided(): bool
    {
        return $this !== self::New;
    }

    /**
     * Klasy plakietki stanu — jedno źródło dla listy i karty zgłoszenia, żeby
     * kolory nie rozjechały się przy pierwszej zmianie.
     *
     * Bursztyn = czeka na Ciebie, zieleń = zgłoszenie uznane, czerwień =
     * odrzucone. Odrzucone dostaje tło `-50` zamiast `-100`, bo `bg-rose-100`
     * NIE MA w zbudowanym CSS — klasa spoza buildu nic nie robi po cichu, więc
     * plakietka wyszłaby bez tła. Zrównanie odcieni wymaga przebudowy CSS.
     *
     * @return array{0: string, 1: string} tło, kolor tekstu
     */
    public function badgeClasses(): array
    {
        return match ($this) {
            self::New => ['bg-amber-100', 'text-amber-900'],
            self::Upheld => ['bg-emerald-100', 'text-emerald-800'],
            self::Rejected => ['bg-rose-50', 'text-rose-700'],
        };
    }
}
