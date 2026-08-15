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
     * odrzucone — wszystkie w tym samym odcieniu `-100`, żeby miały równą wagę.
     *
     * `bg-rose-100` weszła do buildu dopiero razem z tym plikiem: Tailwind
     * generuje wyłącznie klasy, które gdzieś w projekcie WYSTĘPUJĄ, więc dopóki
     * nikt jej nie użył, nie istniała. Test `ContentReportPanelTest` czyta
     * zbudowany arkusz i pilnuje, że każda z tych klas w nim jest — klasa spoza
     * buildu nic nie robi po cichu, a plakietka wychodzi bez tła.
     *
     * @return array{0: string, 1: string} tło, kolor tekstu
     */
    public function badgeClasses(): array
    {
        return match ($this) {
            self::New => ['bg-amber-100', 'text-amber-900'],
            self::Upheld => ['bg-emerald-100', 'text-emerald-800'],
            self::Rejected => ['bg-rose-100', 'text-rose-700'],
        };
    }
}
