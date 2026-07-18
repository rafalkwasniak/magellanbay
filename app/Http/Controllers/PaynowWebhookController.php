<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderStatusChanger;
use App\Services\PaynowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Powiadomienia (webhooki) Paynow o zmianie statusu płatności. To ŹRÓDŁO PRAWDY
 * o zapłacie — nie powrót kupującego na stronę podziękowania, który jest tylko
 * UX. Trasa jest publiczna (Paynow nie ma sesji ani tokenu CSRF), więc broni jej
 * podpis: bez klucza podpisu sklepu nie da się sfałszować powiadomienia.
 *
 * Kolejność jest celowa: najpierw odnajdujemy zamówienie po `paymentId` (globalnie
 * unikalnym), by poznać sklep i jego klucz podpisu, a DOPIERO potem weryfikujemy
 * podpis nad surowym ciałem. Statusowi z ciała nie ufamy, dopóki podpis się nie
 * zgodzi — samo `paymentId` służy tylko do znalezienia klucza.
 *
 * Idempotencja: „Opłacone" ustawiamy tylko z „Oczekuje na płatność", więc drugi
 * webhook CONFIRMED jest bezpiecznym no-opem (dodatkowo pilnuje tego sam
 * `OrderStatusChanger`, który nie zmienia statusu na ten sam).
 */
class PaynowWebhookController extends Controller
{
    public function __invoke(Request $request, PaynowService $paynow, OrderStatusChanger $changer): JsonResponse
    {
        $rawBody = $request->getContent();
        $data = json_decode($rawBody, true);

        $paymentId = is_array($data) ? ($data['paymentId'] ?? null) : null;
        $status = is_array($data) ? ($data['status'] ?? null) : null;

        if (blank($paymentId) || blank($status)) {
            return response()->json(['ok' => false], 400);
        }

        $order = Order::where('payment_external_id', $paymentId)->first();

        if ($order === null) {
            // Nieznana płatność: potwierdzamy odbiór (żeby Paynow nie ponawiał bez
            // końca), ale nic nie robimy. Może dotyczyć innego środowiska/instancji.
            Log::channel('paynow')->warning('Webhook: nieznana płatność.', ['payment_id' => $paymentId]);

            return response()->json(['ok' => true]);
        }

        $signatureKey = $order->shop?->paynowSignatureKey();

        if (blank($signatureKey) || ! $paynow->verifyNotificationSignature($signatureKey, $rawBody, $request->header('Signature'))) {
            Log::channel('paynow')->error('Webhook: błędny podpis.', [
                'payment_id' => $paymentId,
                'order_id' => $order->id,
            ]);

            return response()->json(['ok' => false], 400);
        }

        // Dziennik ostatniego statusu operatora — także wtedy, gdy nie zmienia
        // naszego (np. PENDING/REJECTED). Ślad przydaje się przy reklamacjach.
        $order->forceFill(['payment_status' => $status])->save();

        if ($status === PaynowService::STATUS_CONFIRMED && $order->status === OrderStatus::AwaitingPayment) {
            // Przejście przez serwis: zapis na oś czasu + mail „Opłacone" do
            // kupującego lecą tą samą drogą, co ręczna zmiana w panelu.
            $changer->change($order, OrderStatus::Paid, 'Płatność online potwierdzona.');

            // Auto-FV: gdy sklep tak ustawił (pełny pakiet: płatność online +
            // Fakturownia). Guard `requestInvoice()` sam sprawdzi, czy Fakturownia
            // jest włączona i czy FV jeszcze nie ma — więc to tylko dodatkowa zgoda.
            if ($order->shop?->autoInvoiceAfterPayment()) {
                $order->requestInvoice();
            }
        }

        Log::channel('paynow')->info('Webhook: przetworzony.', [
            'payment_id' => $paymentId,
            'order_id' => $order->id,
            'status' => $status,
        ]);

        return response()->json(['ok' => true]);
    }
}
