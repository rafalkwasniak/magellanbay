<?php

namespace App\Http\Middleware;

use App\Support\Mode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zamyka trasy należące do warstwy PLATFORMY, gdy aplikacja pracuje jako sklep
 * dedykowany: rejestrację sprzedawcy, landing z cennikiem, ekran „Mój pakiet",
 * konsolę administratora, usuwanie własnego sklepu i webhook opłat za pakiety.
 *
 * DLACZEGO MIDDLEWARE, A NIE `if` WOKÓŁ DEFINICJI TRAS: gdyby trasy przestały
 * istnieć, każde `route('register')` w widoku rzuciłoby RouteNotFoundException
 * i wywróciło całą stronę — a takich odwołań jest w Blade sporo (ekran
 * logowania, stopka, landing). Tutaj trasa istnieje i jej nazwa nadal się
 * rozwiązuje, tylko odpowiada 404. Odnośniki chowamy osobno, w widokach.
 *
 * 404, nie 403: w sklepie dedykowanym te adresy mają nie istnieć. 403 mówiłby
 * „to tu jest, ale nie dla ciebie" i zdradzał, że pod spodem siedzi platforma.
 */
class EnsureSaasMode
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Mode::dedicated(), 404);

        return $next($request);
    }
}
