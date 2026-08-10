<?php

namespace App\Http\Controllers;

use App\Enums\ConsentChannel;
use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Wypisanie sprzedawcy z wiadomości handlowych Kramio — z podpisanego linku
 * w stopce, BEZ logowania.
 *
 * Bliźniak {@see Storefront\UnsubscribeController}, ale na centrali i na
 * `User` zamiast `Customer`. Te same dwie decyzje co tam:
 *
 * 1. **Samo wejście wypisuje.** Zgoda musi być odwoływalna równie łatwo, jak
 *    została udzielona (RODO art. 7 ust. 3) — bez logowania, bez ankiety.
 * 2. **Przycisk „przywróć" na tej samej stronie.** Kliknięcie przez pomyłkę
 *    albo przez skaner poczty naprawia jeden klik. Gorszym błędem jest
 *    utrudnić wypis, niż wypisać kogoś przypadkiem i dać mu wrócić.
 *
 * Wypis dotyczy WYŁĄCZNIE treści handlowych. Faktura, wygaśnięcie pakietu czy
 * zmiana regulaminu idą dalej — są niezbędne do wykonania umowy i nie wolno
 * ich tą zgodą blokować. Ekran mówi o tym wprost, żeby nikt nie sądził, że
 * właśnie odciął sobie fakturę.
 */
class PlatformUnsubscribeController extends Controller
{
    public function show(User $user): Renderable
    {
        // Wypis natychmiastowy. `setMarketingConsent` jest idempotentne, więc
        // powrót na ten sam link niczego nie psuje.
        $user->setMarketingConsent(ConsentChannel::Email, false);

        return view('platform.unsubscribe', [
            'user' => $user,
            // Własny podpisany adres — dopisanie parametru do bieżącego URL-a
            // unieważniłoby jego podpis.
            'restoreUrl' => URL::signedRoute('platform.unsubscribe.restore', ['user' => $user->getKey()]),
        ]);
    }

    /**
     * Przywrócenie zgody. Dowód (data, IP, wersja treści) zapisuje się od nowa,
     * bo to nowa, świadoma decyzja — a nie „cofnięcie" poprzedniej.
     */
    public function restore(Request $request, User $user): Renderable
    {
        $user->setMarketingConsent(ConsentChannel::Email, true, $request->ip());

        return view('platform.unsubscribe', [
            'user' => $user,
            'restored' => true,
        ]);
    }
}
