<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\LoginRequest;
use App\Models\Shop;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Logowanie i wylogowanie klienta w obrębie jednego sklepu (guard `customer`).
 * Poświadczenia są scope'owane do sklepu (`shop_id`), więc ten sam e-mail loguje
 * do właściwego konta na właściwej subdomenie. Konta nieaktywne (bez hasła) nie
 * przejdą — rozróżniamy to od złych danych osobnym komunikatem.
 */
class AuthController extends Controller
{
    public function create(): Renderable|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect('/');
        }

        return view('storefront.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');

        $credentials = [
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'shop_id' => $shop->id,
        ];

        if (! Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            // Konto istnieje, ale nieaktywowane (brak hasła) → inny komunikat niż złe dane.
            $pending = $shop->customers()
                ->where('email', $request->string('email')->toString())
                ->whereNull('email_verified_at')
                ->exists();

            throw ValidationException::withMessages([
                'email' => $pending
                    ? 'To konto nie zostało jeszcze aktywowane — kliknij link aktywacyjny z maila.'
                    : 'Nieprawidłowy e-mail lub hasło.',
            ]);
        }

        $request->session()->regenerate();

        // Domyślnie prosto do panelu klienta; jeśli klient trafił na logowanie
        // z chronionej strony, `intended` ma pierwszeństwo i wraca tam.
        return redirect()->intended('/moje-konto');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
