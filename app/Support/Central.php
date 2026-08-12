<?php

namespace App\Support;

/**
 * Bezwzględny adres na domenie centrali.
 *
 * Powód istnienia: trasy centrali NIE mają przypiętej domeny (odpowiadają na
 * każdym hoście, który nie jest subdomeną sklepu), więc `route()` i `url()`
 * wywołane NA SUBDOMENIE zbudują adres tej subdomeny. Dla części ścieżek to nie
 * tylko brzydkie, ale wprost mylące: `/rejestracja` na storefroncie zakłada
 * konto KLIENTA sklepu, a nie sklep w Kramio — link „załóż sklep" trafiłby więc
 * w zupełnie inny formularz.
 *
 * Każdy link, który ma prowadzić na platformę niezależnie od tego, gdzie jest
 * renderowany (strona wolnej subdomeny, chrome layoutów wspólnych dla obu
 * światów), buduj tutaj.
 */
class Central
{
    /**
     * Zwraca adres bez końcowego ukośnika dla korzenia — tak samo jak `url()`,
     * żeby linki na stronach centrali wyglądały identycznie jak dotąd.
     */
    public static function url(string $path = '/'): string
    {
        $path = ltrim($path, '/');

        return rtrim((string) config('app.url'), '/').($path === '' ? '' : '/'.$path);
    }
}
