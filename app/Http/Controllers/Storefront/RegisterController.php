<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\RegisterRequest;
use App\Models\Shop;
use App\Services\CustomerActivationMailer;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Rejestracja klienta w sklepie (guard `customer`). Minimum danych: e-mail.
 * Konto powstaje NIEAKTYWNE (bez hasła); klient dostaje mailem podpisany link do
 * ustawienia hasła (CustomerActivationMailer). Nie logujemy od razu — dopiero po
 * aktywacji. Sklep pochodzi z middleware `tenant` (ResolveShop).
 */
class RegisterController extends Controller
{
    public function create(): Renderable|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect('/');
        }

        return view('storefront.auth.register');
    }

    public function store(RegisterRequest $request, CustomerActivationMailer $mailer): RedirectResponse
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');

        // shop_id nie jest mass-assignable — konto przez relację sklepu. Bez hasła
        // i bez email_verified_at → stan „nieaktywne" do czasu kliknięcia w mail.
        $customer = $shop->customers()->create([
            'email' => $request->string('email'),
        ]);

        $mailer->send($customer);

        // Zapamiętany adres (ekran potwierdzenia + ewentualne ponowne wysłanie później).
        $request->session()->put('registered_email', $customer->email);

        return redirect('/rejestracja/potwierdzenie');
    }

    public function registered(Request $request): Renderable
    {
        return view('storefront.auth.registered', [
            'email' => $request->session()->get('registered_email'),
        ]);
    }
}
