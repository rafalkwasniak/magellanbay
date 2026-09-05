<?php

namespace App\Enums;

/**
 * Rodzaj grupy opcji produktu — dwa naprawdę różne pytania do kupującego.
 *
 * Nie ma trzeciego rodzaju „lista rozwijana bez dopłat" ani „pole liczbowe":
 * jedno i drugie to `Choice` bez dopłaty albo `Text` z limitem. Mnożenie
 * rodzajów rozmnożyłoby ekrany w panelu, a odpowiada na to samo pytanie.
 */
enum OptionGroupKind: string
{
    /** Formatka: zestaw pól tekstowych z limitami. Kupujący WPISUJE. */
    case Text = 'text';

    /** Biblioteka gotowych pozycji z dopłatami. Kupujący WSKAZUJE. */
    case Choice = 'choice';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Pola do wpisania',
            self::Choice => 'Wybór z biblioteki',
        };
    }

    /**
     * Zdanie dla sprzedawcy w panelu — mówi, co dostanie kupujący, a nie jak
     * to nazywa baza.
     */
    public function description(): string
    {
        return match ($this) {
            self::Text => 'Klient wpisuje własny tekst w przygotowane pola, każde z limitem znaków. Na przykład imię na kubku albo data na magnesie.',
            self::Choice => 'Klient wybiera jedną pozycję z przygotowanej przez Ciebie listy — z podglądem i własną dopłatą. Na przykład grafikę do wygrawerowania.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
