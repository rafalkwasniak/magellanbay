<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Zamiana tekstu (np. nazwy sklepu) na slug = etykietę subdomeny. Bezstanowy
 * serwis wołany w `prepareForValidation()` Form Requestu — slug jest liczony po
 * stronie serwera (źródło prawdy) zanim zobaczą go reguły i baza; podgląd w
 * przeglądarce jest tylko kosmetyczny.
 *
 * `Str::slug` transliteruje polskie znaki (ą→a, ł→l, ...), sprowadza do małych
 * liter, łączy wyrazy myślnikiem i przycina myślniki z brzegów. Dodatkowo
 * pilnujemy limitu długości etykiety DNS (63 znaki). Unikalności NIE liczymy
 * tutaj — to robi walidacja (`unique:shops,slug`), żeby kolizja dała czytelny
 * błąd, a nie cichą zmianę adresu.
 */
class SlugService
{
    /** Maksymalna długość etykiety DNS. */
    private const MAX_LENGTH = 63;

    public function make(?string $value): string
    {
        $slug = Str::slug((string) $value);

        if (strlen($slug) > self::MAX_LENGTH) {
            $slug = rtrim(substr($slug, 0, self::MAX_LENGTH), '-');
        }

        return $slug;
    }
}
