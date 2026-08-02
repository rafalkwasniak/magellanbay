<?php

namespace App\Http\Controllers;

use App\Support\CookieConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Zapis decyzji o ciasteczkach. Obsługuje zarówno centralę, jak i storefronty —
 * ciasteczko przypina się do hosta, z którego przyszło żądanie, więc zgoda dla
 * jednego sklepu nie obowiązuje w innym.
 *
 * ZWYKŁY FORMULARZ, nie żądanie w tle. Dwa powody: storefront celowo nie ładuje
 * JavaScriptu, więc baner ma działać także bez niego, a po zgodzie i tak trzeba
 * przeładować stronę — skrypt Google dokłada SERWER przy kolejnym żądaniu.
 */
class CookieConsentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $cookie = match ($request->input('decision')) {
            'accept' => CookieConsent::accept(),
            'decline' => CookieConsent::decline(),
            // „Zmień decyzję" z linku w stopce: kasujemy ciasteczko, więc baner
            // pojawia się ponownie i użytkownik wybiera od nowa.
            default => CookieConsent::forget(),
        };

        return back()->withCookie($cookie);
    }
}
