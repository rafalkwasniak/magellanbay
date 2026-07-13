<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ActivationRequest;
use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Aktywacja konta klienta z podpisanego linku mailowego (middleware `signed`).
 * Klient ustawia pierwsze hasło (i opcjonalnie dane profilu), po czym: e-mail
 * zostaje potwierdzony, wcześniejsze zamówienia gościa o tym adresie w tym sklepie
 * przypinają się do konta, a klient zostaje od razu zalogowany (guard `customer`).
 * Podpis linku wiąże konkretnego klienta z konkretną subdomeną — dodatkowo
 * pilnujemy zgodności `shop_id` (obrona w głąb).
 */
class ActivationController extends Controller
{
    public function create(Request $request, Customer $customer): Renderable|RedirectResponse
    {
        $this->ensureBelongsToShop($request, $customer);

        if ($customer->isActivated()) {
            return redirect('/logowanie')->with('status', 'To konto jest już aktywne — zaloguj się.');
        }

        return view('storefront.auth.activate', [
            'customer' => $customer,
            // Ten sam podpisany URL obsługuje POST — przekazujemy go jako action.
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    public function store(ActivationRequest $request, Customer $customer): RedirectResponse
    {
        $this->ensureBelongsToShop($request, $customer);

        if ($customer->isActivated()) {
            return redirect('/logowanie')->with('status', 'To konto jest już aktywne — zaloguj się.');
        }

        // E-mail jest stały (identyfikator konta) — nie ruszamy go z formularza.
        $customer->forceFill([
            'name' => $request->input('name') ?: $customer->name,
            'surname' => $request->input('surname') ?: $customer->surname,
            'phone' => $request->input('phone') ?: $customer->phone,
            'password' => Hash::make($request->string('password')),
            'email_verified_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        $claimed = $customer->claimGuestOrders();

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect('/')->with('status', $this->welcomeMessage($claimed));
    }

    /**
     * Konto musi należeć do sklepu spod tej subdomeny — inaczej 404 (obrona w głąb
     * ponad podpisem linku, który już to wiąże).
     */
    private function ensureBelongsToShop(Request $request, Customer $customer): void
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');

        abort_unless($customer->shop_id === $shop->id, 404);
    }

    /**
     * Komunikat powitalny po aktywacji; gdy przypięto wcześniejsze zamówienia —
     * z ich liczbą w poprawnej polskiej odmianie.
     */
    private function welcomeMessage(int $claimed): string
    {
        $base = 'Twoje konto jest już aktywne. Miłych zakupów!';

        if ($claimed < 1) {
            return $base;
        }

        return 'Twoje konto jest już aktywne. Przypisaliśmy do niego '.$claimed.' '.$this->ordersWord($claimed).' złożone wcześniej.';
    }

    /**
     * Polska odmiana słowa „zamówienie" przez liczbę (1 / 2–4 / 5+ z wyjątkami 12–14).
     */
    private function ordersWord(int $n): string
    {
        if ($n === 1) {
            return 'zamówienie';
        }

        $mod10 = $n % 10;
        $mod100 = $n % 100;

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return 'zamówienia';
        }

        return 'zamówień';
    }
}
