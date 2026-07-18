<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Services\PaynowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kasa storefrontu. Formularz i składanie obsługuje komponent Livewire
 * `Checkout`; kontroler renderuje powłokę oraz stronę podziękowania. Numer
 * złożonego zamówienia przychodzi przez sesję (nie przez URL — żeby nie dało
 * się podejrzeć cudzego zamówienia po numerze).
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

    /**
     * Rozpoczyna płatność online dla świeżo złożonego zamówienia (przycisk na
     * ekranie podsumowania). Zamówienie bierzemy z sesji — tak samo jak
     * podsumowanie, żeby nie dało się zapłacić za cudze po numerze. Tworzymy
     * płatność w Paynow, zapisujemy jej identyfikator (po nim webhook odnajdzie
     * zamówienie) i przekierowujemy kupującego do bramki. Powrót wraca na ten sam
     * ekran podsumowania (`continueUrl`), a o zapłacie i tak rozstrzyga webhook.
     */
    public function pay(Request $request, PaynowService $paynow): RedirectResponse
    {
        $shop = $request->attributes->get('shop');
        $orderId = session()->get('recent_order_id');

        $order = $orderId !== null
            ? $shop->orders()->find($orderId)
            : null;

        if ($order === null) {
            return redirect()->to('/');
        }

        // Płacimy tylko za zamówienie online, które wciąż czeka na wpłatę, i tylko
        // gdy sklep ma płatności realnie włączone. Inaczej wracamy na podsumowanie.
        if ($order->payment_method !== PaymentMethod::Online
            || $order->status !== OrderStatus::AwaitingPayment
            || ! $shop->onlinePaymentsEnabled()) {
            return redirect()->to('/kasa/dziekujemy');
        }

        $continueUrl = $request->getSchemeAndHttpHost().'/kasa/dziekujemy';
        $payment = $paynow->createPayment($order, $continueUrl);

        if ($payment === null) {
            return redirect()
                ->to('/kasa/dziekujemy')
                ->with('error', 'Nie udało się rozpocząć płatności. Spróbuj ponownie za chwilę.');
        }

        $order->forceFill([
            'payment_external_id' => $payment['paymentId'],
            'payment_status' => $payment['status'],
        ])->save();

        return redirect()->away($payment['redirectUrl']);
    }
}
