<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
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

        $orders = $customer->orders()
            ->withCount('items')
            ->latest('id')
            ->get();

        return view('storefront.account.index', [
            'customer' => $customer,
            'orders' => $orders,
        ]);
    }

    public function order(Order $order): Renderable
    {
        $this->authorizeOrder($order);

        return view('storefront.account.order', [
            'order' => $order->load('items'),
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
}
