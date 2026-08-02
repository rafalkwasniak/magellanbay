<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\PasswordResetLinkRequest;
use App\Http\Requests\Storefront\PasswordResetRequest;
use App\Models\Customer;
use App\Models\Shop;
use App\Services\CustomerActivationMailer;
use App\Services\CustomerPasswordResetMailer;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Odzyskiwanie hasła przez klienta sklepu.
 *
 * Konta klientów są SCOPE'OWANE DO SKLEPU — ten sam adres e-mail bywa kontem u
 * wielu sprzedawców. Szukamy więc wyłącznie w obrębie sklepu, z którego przyszło
 * żądanie, a link jest podpisany i niesie identyfikator konkretnego klienta.
 *
 * Tak jak w centrali: odpowiedź jest IDENTYCZNA niezależnie od tego, czy konto
 * istnieje — inaczej formularz zdradzałby sprzedawcy cudzą listę klientów, a
 * postronnemu mówił, kto kupuje w danym sklepie.
 *
 * Konto założone przy zamówieniu, ale nigdy nieaktywowane, dostaje mail
 * AKTYWACYJNY zamiast resetu — nie ma jeszcze hasła, które można by zmienić.
 */
class PasswordResetController extends Controller
{
    public function create(): Renderable
    {
        return view('storefront.auth.password-request');
    }

    public function store(
        PasswordResetLinkRequest $request,
        CustomerPasswordResetMailer $reset,
        CustomerActivationMailer $activation,
    ): RedirectResponse {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');

        $customer = $shop->customers()
            ->where('email', $request->string('email')->toString())
            ->first();

        if ($customer !== null) {
            $customer->isActivated()
                ? $reset->send($customer)
                : $activation->send($customer);
        }

        return back()->with('status', 'Jeśli konto o tym adresie istnieje, wysłaliśmy na nie wiadomość z dalszymi krokami. Sprawdź skrzynkę.');
    }

    public function edit(Request $request, Customer $customer): Renderable
    {
        $this->ensureBelongsToShop($request, $customer);

        return view('storefront.auth.password-reset', [
            'customer' => $customer,
            // Ten sam podpisany adres obsługuje POST — formularz wysyła na niego.
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    public function update(PasswordResetRequest $request, Customer $customer): RedirectResponse
    {
        $this->ensureBelongsToShop($request, $customer);

        $customer->forceFill([
            'password' => Hash::make($request->string('password')),
            // Konto odzyskane linkiem z własnej skrzynki — adres jest tym samym
            // potwierdzony, więc konto zakładane przy zamówieniu staje się pełne.
            'email_verified_at' => $customer->email_verified_at ?? now(),
            // Nowy token unieważnia „zapamiętaj mnie" na obcych urządzeniach.
            'remember_token' => Str::random(60),
        ])->save();

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect('/moje-konto')->with('status', 'Hasło zostało zmienione.');
    }

    /**
     * Podpis linku wiąże klienta z konkretną subdomeną, ale sprawdzamy jeszcze
     * `shop_id` — obrona w głąb, ten sam wzorzec co przy aktywacji.
     */
    private function ensureBelongsToShop(Request $request, Customer $customer): void
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');

        if ($customer->shop_id !== $shop->id) {
            throw new NotFoundHttpException;
        }
    }
}
