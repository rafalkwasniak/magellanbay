<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kasa storefrontu. Formularz i składanie obsługuje komponent Livewire
 * `Checkout`; kontroler renderuje powłokę oraz stronę podziękowania. Numer
 * złożonego zamówienia przychodzi przez sesję (nie przez URL — żeby nie dało
 * się podejrzeć cudzego zamówienia po numerze).
 *
 * Płatność online dokańcza się na osobnej, tokenowej stronie (`PaymentController`) —
 * ekran podziękowania tylko do niej linkuje, tak samo jak mail i „Moje konto".
 */
class CheckoutController extends Controller
{
    public function show(Request $request): View
    {
        $shop = $request->attributes->get('shop');

        return view('storefront.checkout', ['shop' => $shop]);
    }

    public function confirmation(Request $request): View|RedirectResponse
    {
        $shop = $request->attributes->get('shop');
        $orderId = session()->get('recent_order_id');

        $order = $orderId !== null
            ? $shop->orders()->with('items')->find($orderId)
            : null;

        if ($order === null) {
            return redirect()->to('/');
        }

        return view('storefront.order-confirmation', ['shop' => $shop, 'order' => $order]);
    }
}
