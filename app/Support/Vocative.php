<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

/**
 * Wołacz imienia na potrzeby powitań („Cześć Anno,"). Jedno miejsce prawdy dla
 * maili, panelu i storefrontu.
 *
 * Zasada: NIE ZGADUJEMY. Imię jest w słowniku (`config/vocative.php`) → wołacz.
 * Nie ma go → zwracamy mianownik, bo „Cześć Kevin" jest zwyczajnie w porządku,
 * a zgadywana odmiana („Cześć Kevino") ośmieszałaby sklep. Trafienie daje „wow",
 * pudło daje poziom większości sklepów — obie ścieżki są bezpieczne.
 *
 * null leci tylko wtedy, gdy w polu imienia nie ma imienia (pusto, cyfry, nazwa
 * firmy) — wtedy wołający pisze bezosobowe „Dzień dobry", zamiast witać się
 * z „FIRMA XYZ SP. Z O.O.".
 */
class Vocative
{
    /**
     * Jak zwrócić się do tej osoby po imieniu — wołacz, mianownik, albo null
     * gdy w polu nie ma imienia.
     */
    public static function of(?string $name): ?string
    {
        $field = Str::of((string) $name)->squish();

        if (! self::looksLikeName($field)) {
            return null;
        }

        $first = $field->explode(' ')->first();

        // Formularze produkują też „ANNA" — mianownikowy fallback nie ma powodu
        // krzyczeć. Słownik i tak dopasowuje po lowercase, więc dotyczy to ogona.
        if (mb_strtoupper($first) === $first) {
            $first = Str::ucfirst(mb_strtolower($first));
        }

        return config('vocative.'.mb_strtolower($first), $first);
    }

    /**
     * Gotowa linia powitania maila: „Cześć Anno," albo „Dzień dobry," gdy nie
     * wiemy, do kogo piszemy.
     */
    public static function greeting(?string $name): string
    {
        $vocative = self::of($name);

        return $vocative ? 'Cześć '.$vocative.',' : 'Dzień dobry,';
    }

    /**
     * To samo powitanie, ale bez przecinka — do użycia jako NAGŁÓWEK maila,
     * gdzie po nim nie idzie zdanie, tylko treść. „Cześć Rafale," w wielkim
     * nagłówku wyglądałoby na urwane w pół słowa.
     */
    public static function headline(?string $name): string
    {
        return rtrim(self::greeting($name), ',');
    }

    /**
     * Czy CAŁE pole wygląda na imię (ewentualnie z nazwiskiem): same litery,
     * spacje i myślniki, sensowna długość pierwszego członu. Kropka, przecinek
     * czy cyfra zdradzają nazwę firmy („FIRMA XYZ SP. Z O.O.") albo śmieć —
     * sprawdzamy to na całości, bo pierwszy token bywa niewinny („FIRMA…").
     *
     * Gołego „Firma XYZ" bez interpunkcji to nie złapie i złapać nie może —
     * od rzadkiego imienia odróżnia je tylko znaczenie, a tego nie zgadujemy.
     */
    private static function looksLikeName(Stringable $field): bool
    {
        return preg_match('/^\p{L}[\p{L}\- ]{1,60}$/u', (string) $field) === 1
            && mb_strlen((string) $field->explode(' ')->first()) >= 2;
    }
}
