<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\ConsentChannel;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Wypisanie się z korespondencji seryjnej sklepu — z podpisanego linku w
 * stopce wiadomości, BEZ logowania.
 *
 * Zgoda musi być odwoływalna równie łatwo, jak została udzielona (RODO art. 7
 * ust. 3), więc jedno kliknięcie z maila kończy sprawę — bez szukania hasła,
 * bez ankiety „dlaczego odchodzisz". Dlatego samo wejście na stronę wypisuje;
 * strona jest już tylko potwierdzeniem.
 *
 * Kliknięcie przez pomyłkę (albo przez skaner poczty) naprawia przycisk
 * „przywróć" na tej samej stronie. Świadomie wybieramy tę kolejność: gorszym
 * błędem jest utrudnić wypis, niż wypisać kogoś przypadkiem i dać mu wrócić
 * jednym klikiem.
 *
 * Podpis linku wiąże klienta z subdomeną; dodatkowo pilnujemy zgodności
 * `shop_id` (obrona w głąb) — zgody są per sklep, więc wypis u jednego
 * sprzedawcy nie może dotknąć drugiego.
 */
class UnsubscribeController extends Controller
{
    public function show(Request $request, Customer $customer): Renderable
    {
        $this->ensureBelongsToShop($request, $customer);

        // Wypis natychmiastowy. `setConsent` jest idempotentne, więc powrót na
        // ten sam link (albo drugi klik) niczego nie psuje.
        $customer->setConsent(ConsentChannel::Email, false);

        $shop = $request->attributes->get('shop');

        return view('storefront.unsubscribe', [
            'shop' => $shop,
            'customer' => $customer,
            // Własny podpisany adres — dopisanie parametru do bieżącego URL-a
            // unieważniłoby jego podpis.
            'restoreUrl' => URL::signedRoute('storefront.unsubscribe.restore', [
                'shop' => $shop->slug,
                'customer' => $customer->id,
            ]),
        ]);
    }

    /**
     * Przywrócenie zgody dla kogoś, kto kliknął przez pomyłkę. Dowód zgody
     * (data, IP, wersja treści) zapisuje się od nowa, bo to nowa, świadoma
     * decyzja — a nie „cofnięcie" poprzedniej.
     */
    public function restore(Request $request, Customer $customer): Renderable
    {
        $this->ensureBelongsToShop($request, $customer);

        $customer->setConsent(ConsentChannel::Email, true, $request->ip());

        return view('storefront.unsubscribe', [
            'shop' => $request->attributes->get('shop'),
            'customer' => $customer,
            'restored' => true,
        ]);
    }

    /**
     * Klient musi należeć do sklepu z subdomeny — podpis potwierdza tożsamość
     * linku, to potwierdza, że dotyczy właściwego sklepu.
     */
    private function ensureBelongsToShop(Request $request, Customer $customer): void
    {
        abort_unless($customer->shop_id === $request->attributes->get('shop')?->id, 404);
    }
}
