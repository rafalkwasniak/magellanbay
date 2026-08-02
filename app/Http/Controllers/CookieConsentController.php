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
        [$cookie, $message] = match ($request->input('decision')) {
            'accept' => [CookieConsent::accept(), 'Dzięki — zgoda na ciasteczka analityczne zapisana.'],
            'decline' => [CookieConsent::decline(), 'Zapisane. Używamy tylko ciasteczek niezbędnych do działania.'],
            // „Ciasteczka" z linku w stopce: kasujemy ciasteczko, więc baner
            // pojawia się ponownie i użytkownik wybiera od nowa.
            default => [CookieConsent::forget(), 'Decyzja o ciasteczkach wyczyszczona — zapytamy o nią ponownie.'],
        };

        // Potwierdzenie jest tu KONIECZNE, nie kosmetyczne. Kliknięcie w link
        // wraca na tę samą stronę, a w panelu nie ma banera, który pokazałby
        // efekt — bez komunikatu wyglądałoby to, jakby przycisk nie działał.
        return back()->with('success', $message)->withCookie($cookie);
    }
}
