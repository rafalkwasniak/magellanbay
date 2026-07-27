<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dokłada nagłówki bezpieczeństwa do każdej odpowiedzi. Jedno miejsce dla całej
 * platformy: centrala i wszystkie storefronty sprzedawców dostają to samo, więc
 * poprawka nie wymaga niczego od sprzedawcy.
 *
 * Nagłówki i HSTS konfigurowane w `config/security.php` — wartość `null` znaczy
 * „nie wysyłaj", żeby dało się dostroić pojedynczy nagłówek bez ruszania kodu.
 *
 * Nie nadpisujemy nagłówka, który odpowiedź już niesie: kontroler albo inne
 * middleware mogły ustawić go świadomie dla konkretnego przypadku.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ((array) config('security.headers', []) as $header => $value) {
            if ($value !== null && ! $response->headers->has($header)) {
                $response->headers->set($header, $value);
            }
        }

        // HSTS ma sens wyłącznie na połączeniu szyfrowanym — po HTTP przeglądarka
        // i tak go ignoruje, a lokalnie potrafi zablokować pracę na http://.
        if ($request->secure() && config('security.hsts.enabled')) {
            $response->headers->set('Strict-Transport-Security', $this->hstsValue());
        }

        return $response;
    }

    private function hstsValue(): string
    {
        $value = 'max-age='.(int) config('security.hsts.max_age');

        if (config('security.hsts.include_subdomains')) {
            $value .= '; includeSubDomains';
        }

        if (config('security.hsts.preload')) {
            $value .= '; preload';
        }

        return $value;
    }
}
