<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\ConsentChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\MarketingConsentRequest;
use App\Http\Requests\Storefront\PasswordUpdateRequest;
use App\Http\Requests\Storefront\ProfileUpdateRequest;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Panel klienta „Moje konto" (guard `customer`, wyłącznie własne dane sklepu):
 * historia zamówień, edycja danych profilu, zmiana hasła i usunięcie konta (RODO).
 * Wszystko scope'owane do zalogowanego klienta — cudzego zamówienia nie pokażemy.
 */
class AccountController extends Controller
{
    private function customer(): Customer
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $customer;
    }

    public function index(): Renderable
    {
        $customer = $this->customer();

        return view('storefront.account.index', [
            'customer' => $customer,
            // Anulowane nie są zakupem — nie podbijają ani licznika, ani kwoty
            // (historia zamówień pokazuje je dalej, patrz `orders()` niżej).
            'ordersCount' => $customer->orders()->countedAsSale()->count(),
            'totalSpent' => $customer->orders()->countedAsSale()->sum('total_gross'),
            'lastOrder' => $customer->orders()->withCount('items')->latest('id')->first(),
        ]);
    }

    public function orders(): Renderable
    {
        $orders = $this->customer()->orders()
            ->withCount('items')
            ->latest('id')
            ->paginate(10);

        return view('storefront.account.orders', [
            'orders' => $orders,
        ]);
    }

    public function order(Order $order, Request $request): Renderable
    {
        $this->authorizeOrder($order);

        return view('storefront.account.order', [
            'order' => $order->load('items'),
            'back' => $this->safeBack($request->query('powrot')),
        ]);
    }

    public function edit(): Renderable
    {
        return view('storefront.account.edit', [
            'customer' => $this->customer(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $this->customer()->update($request->safe()->only('name', 'surname', 'phone'));

        return redirect('/moje-konto/dane')->with('status', 'Zapisaliśmy Twoje dane.');
    }

    /**
     * Zgody marketingowe. Zapisujemy TYLKO gdy stan faktycznie się zmienił —
     * inaczej każde kliknięcie „Zapisz" odświeżałoby datę i IP zgody, niszcząc
     * dowód, KIEDY klient jej naprawdę udzielił.
     */
    public function consents(MarketingConsentRequest $request): RedirectResponse
    {
        $customer = $this->customer();
        $wants = $request->boolean('marketing_email');

        if ($wants !== $customer->hasConsent(ConsentChannel::Email)) {
            $customer->setConsent(ConsentChannel::Email, $wants, $request->ip());
        }

        // Komunikat mówi ogólnie „o produktach", a nie wylicza zakresu zgody —
        // wyliczanie duplikowałoby `config('legal.marketing_consent.text')` i
        // rozjeżdżało się z nim przy każdej zmianie treści.
        return redirect('/moje-konto/dane')->with('status', $wants
            ? 'Będziemy informować Cię o produktach.'
            : 'Nie będziemy już wysyłać Ci wiadomości o produktach.');
    }

    public function password(PasswordUpdateRequest $request): RedirectResponse
    {
        $this->customer()->forceFill([
            'password' => Hash::make($request->string('password')),
        ])->save();

        return redirect('/moje-konto/dane')->with('status', 'Hasło zostało zmienione.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $customer = $this->customer();

        // KOLEJNOŚĆ MA ZNACZENIE: najpierw wyloguj (SessionGuard::logout cyklizuje
        // remember-token i ZAPISUJE usera — po delete zrobiłby tym INSERT, wskrzeszając
        // konto), dopiero potem usuń. Zamówienia zostają (FK nullOnDelete → wracają do
        // „gościa", historia i numeracja sklepu nienaruszone).
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $customer->delete();

        return redirect('/')->with('status', 'Twoje konto zostało usunięte.');
    }

    /**
     * Zamówienie musi należeć do zalogowanego klienta — inaczej 404 (nie zdradzamy
     * nawet istnienia cudzego zamówienia).
     */
    private function authorizeOrder(Order $order): void
    {
        abort_unless($order->customer_id === $this->customer()->id, 404);
    }

    /**
     * Cel „← Powrót" ze szczegółu zamówienia = URL listy zapamiętany przy wejściu
     * (z paginacją: `?page=2`). Tylko ścieżka lokalna — obcina open-redirect
     * (`//host`, `/\host`, `http://…`). Brak/nieprawidłowy → lista zamówień.
     */
    private function safeBack(mixed $back): string
    {
        if (is_string($back)
            && str_starts_with($back, '/')
            && ! str_starts_with($back, '//')
            && ! str_starts_with($back, '/\\')) {
            return $back;
        }

        return '/moje-konto/zamowienia';
    }
}
