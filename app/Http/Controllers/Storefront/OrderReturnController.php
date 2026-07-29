<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\OrderReturnException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\OrderReturnRequest;
use App\Models\Order;
use App\Services\OrderMailer;
use App\Services\OrderReturnService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Publiczny formularz odstąpienia od umowy („Zwrot"), dostępny po tokenie
 * zamówienia — bez logowania, tak samo dla klienta z konta i dla gościa.
 * Ustawa wymaga, by złożenie oświadczenia było łatwe; wymuszanie rejestracji
 * albo szukania w panelu byłoby zaporą, której prawo nie przewiduje.
 *
 * Strona żyje w JEDNYM adresie (`/zwrot/{token}`), do którego prowadzi i mail
 * po zakupie, i „Moje konto" — klient nie musi pamiętać, którędy wszedł.
 *
 * Bezpieczeństwo jak na stronie płatności: token rozszyfrowujemy do id, a
 * zamówienie scope'ujemy do sklepu z subdomeny, więc token jednego sklepu nie
 * sięgnie zamówienia innego.
 */
class OrderReturnController extends Controller
{
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $order = $this->resolve($request, $token);

        if ($order === null) {
            return redirect()->to('/');
        }

        return view('storefront.order-return', [
            'shop' => $request->attributes->get('shop'),
            'order' => $order,
        ]);
    }

    /**
     * Przyjmuje oświadczenie. Kształt danych sprawdził Form Request, ilości i
     * sufity sprawdza serwis (pod blokadą wiersza) — jego komunikat jest pisany
     * do klienta, więc pokazujemy go wprost.
     */
    public function store(OrderReturnRequest $request, string $token, OrderReturnService $returns, OrderMailer $mailer): RedirectResponse
    {
        $order = $this->resolve($request, $token);

        if ($order === null) {
            return redirect()->to('/');
        }

        if (! $order->acceptsReturns()) {
            return redirect()->to('/zwrot/'.$token);
        }

        try {
            $return = $returns->register($order, $request->quantities(), $request->declaration());

            // Sklep dowiaduje się o zwrocie, klient dostaje potwierdzenie — to
            // drugie jest OBOWIĄZKIEM (art. 30 ust. 2 ustawy o prawach
            // konsumenta): oświadczenie złożone elektronicznie trzeba
            // niezwłocznie potwierdzić na trwałym nośniku.
            $mailer->returnSubmitted($order->fresh()->load('items'), $return);
            $mailer->returnAcknowledged($order->fresh()->load('items'), $return);
        } catch (OrderReturnException $e) {
            return redirect()
                ->to('/zwrot/'.$token)
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->to('/zwrot/'.$token)
            ->with('status', 'Przyjęliśmy Twoje oświadczenie o odstąpieniu od umowy. Sklep dostał powiadomienie i skontaktuje się w sprawie zwrotu pieniędzy.');
    }

    /**
     * Zamówienie spod tokenu, scope'owane do sklepu z subdomeny. Ładujemy od razu
     * produkty (flaga art. 38) i oś czasu (od niej liczy się termin), żeby widok
     * nie dociągał ich zapytanie po zapytaniu.
     */
    private function resolve(Request $request, string $token): ?Order
    {
        $shop = $request->attributes->get('shop');
        $id = Order::decodePaymentToken($token);

        return $id !== null
            ? $shop->orders()->with(['items.product', 'statusEvents', 'returns.items'])->find($id)
            : null;
    }
}
