<?php

namespace App\Http\Controllers;

use App\Models\PackagePayment;
use App\Services\PackagePaymentService;
use App\Services\PaynowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhooki Paynow dla opłat za PAKIETY Kramio — konto platformy, więc podpis
 * weryfikujemy kluczem z `.env`, nie kluczem sklepu. Poza tym ten sam wzorzec
 * co webhook zamówień: publiczna trasa broniona podpisem, wiersz odnajdywany po
 * `paymentId`, idempotentne zastosowanie (drugi CONFIRMED to no-op w `apply()`).
 */
class PackagePaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaynowService $paynow, PackagePaymentService $payments): JsonResponse
    {
        $rawBody = $request->getContent();
        $data = json_decode($rawBody, true);

        $paymentId = is_array($data) ? ($data['paymentId'] ?? null) : null;
        $status = is_array($data) ? ($data['status'] ?? null) : null;

        if (blank($paymentId) || blank($status)) {
            return response()->json(['ok' => false], 400);
        }

        $signatureKey = config('services.paynow.platform.signature_key');

        if (blank($signatureKey) || ! $paynow->verifyNotificationSignature($signatureKey, $rawBody, $request->header('Signature'))) {
            Log::channel('paynow')->error('Webhook pakietów: błędny podpis.', ['payment_id' => $paymentId]);

            return response()->json(['ok' => false], 400);
        }

        $payment = PackagePayment::where('payment_id', $paymentId)->first();

        if ($payment === null) {
            Log::channel('paynow')->warning('Webhook pakietów: nieznana płatność.', ['payment_id' => $paymentId]);

            return response()->json(['ok' => true]);
        }

        if ($status === PaynowService::STATUS_CONFIRMED) {
            $payments->apply($payment);
        } elseif (in_array($status, ['REJECTED', 'EXPIRED', 'ERROR'], true) && ! $payment->isApplied()) {
            // Stan terminalny bez wpłaty: oznaczamy porażkę, żeby ekran przestał
            // pokazywać „czeka na potwierdzenie" i zaprosił do ponowienia.
            // Guard na `isApplied` — dziwna kolejność webhooków (CONFIRMED,
            // potem spóźniony REJECTED) nie może cofnąć udanego zakupu.
            $payment->forceFill(['status' => 'failed'])->save();
        }

        Log::channel('paynow')->info('Webhook pakietów: przetworzony.', ['payment_id' => $paymentId, 'status' => $status]);

        return response()->json(['ok' => true]);
    }
}
