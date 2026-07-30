<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaynowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Publiczna strona płatności zamówienia, dostępna po tokenie (zaszyfrowany
 * identyfikator zamówienia — patrz `Order::paymentToken()`). Jedno miejsce, do
 * którego kierują WSZYSTKIE ścieżki dokończenia płatności: mail, „Moje konto",
 * powrót z Paynow i ekran podziękowania. Działa bez logowania — dla kupującego
 * z konta i gościa tak samo, bo prawo dostępu niesie sam token, nie sesja.
 *
 * Bezpieczeństwo: token rozszyfrowujemy do id, a zamówienie i tak scope'ujemy do
 * sklepu z subdomeny (`shop()->orders()`), więc token jednego sklepu nie sięgnie
 * zamówienia innego. Zapłata i tak tylko zasila sprzedawcę — nie ma czym szkodzić.
 */
class PaymentController extends Controller
{
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $order = $this->resolve($request, $token);

        if ($order === null) {
            return redirect()->to('/');
        }

        return view('storefront.payment', [
            'shop' => $request->attributes->get('shop'),
            'order' => $order,
        ]);
    }

    /**
     * Rozpoczyna płatność online: tworzy płatność w Paynow, zapisuje jej
     * identyfikator (po nim webhook odnajdzie zamówienie) i przekierowuje do
     * bramki. Powrót wraca na tę samą stronę (`continueUrl`), a o zapłacie
     * rozstrzyga webhook. Płacimy tylko za nieopłacone zamówienie online przy
     * realnie włączonych płatnościach — inaczej wracamy na stronę bez akcji.
     */
    public function pay(Request $request, string $token, PaynowService $paynow): RedirectResponse
    {
        $order = $this->resolve($request, $token);

        if ($order === null) {
            return redirect()->to('/');
        }

        $shop = $request->attributes->get('shop');

        // `canFinishOnlinePayment`, nie `onlinePaymentsEnabled`: zamówienie
        // złożone przed wygaśnięciem abonamentu musi dać się dopłacić.
        if (! $order->isAwaitingOnlinePayment() || ! $shop->canFinishOnlinePayment()) {
            return redirect()->to('/platnosc/'.$token);
        }

        $continueUrl = $request->getSchemeAndHttpHost().'/platnosc/'.$token;
        $payment = $paynow->createPayment($order, $continueUrl);

        if ($payment === null) {
            return redirect()
                ->to('/platnosc/'.$token)
                ->with('error', 'Nie udało się rozpocząć płatności. Spróbuj ponownie za chwilę.');
        }

        $order->forceFill([
            'payment_external_id' => $payment['paymentId'],
            'payment_status' => $payment['status'],
        ])->save();

        return redirect()->away($payment['redirectUrl']);
    }

    /**
     * Zamówienie spod tokenu, scope'owane do sklepu z subdomeny. null, gdy token
     * uszkodzony/podrobiony albo wskazuje zamówienie spoza tego sklepu.
     */
    private function resolve(Request $request, string $token): ?Order
    {
        $shop = $request->attributes->get('shop');
        $id = Order::decodePaymentToken($token);

        return $id !== null ? $shop->orders()->with('items')->find($id) : null;
    }
}
