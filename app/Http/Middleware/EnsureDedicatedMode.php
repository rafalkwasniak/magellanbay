<?php

namespace App\Http\Middleware;

use App\Support\Mode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lustro {@see EnsureSaasMode}: zamyka trasy, które mają sens WYŁĄCZNIE w sklepie
 * dedykowanym.
 *
 * Dziś jest to jedna rzecz — zgłoszenia treści bezprawnych w panelu właściciela.
 * W Kramio te same zgłoszenia rozpatruje PLATFORMA, i to nie jest szczegół
 * organizacyjny: cała nasza kwalifikacja jako dostawcy usługi hostingu (art. 6
 * DSA) opiera się na tym, że decyzję o cudzej treści podejmuje operator, a nie
 * sprzedawca, którego ta treść dotyczy. Sprzedawca rozstrzygający zgłoszenia
 * przeciwko własnemu sklepowi byłby sędzią we własnej sprawie.
 *
 * W sklepie dedykowanym ten konflikt nie istnieje, bo nie ma dwóch podmiotów —
 * właściciel jest jedynym adresatem zgłoszenia.
 *
 * 404, nie 403 — z tego samego powodu co w `EnsureSaasMode`: adres ma nie
 * istnieć, a nie mówić „to tu jest, ale nie dla ciebie".
 */
class EnsureDedicatedMode
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Mode::dedicated(), 404);

        return $next($request);
    }
}
