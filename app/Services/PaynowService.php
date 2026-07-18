<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Płatności online przez Paynow (bramka mBanku). Konfiguracja jest PER-SKLEP
 * (klucz API + klucz podpisu ze `shop_integrations`, zaszyfrowane) — pieniądze
 * płyną klient → Paynow → sprzedawca, wprost. Ten serwis:
 *  - tworzy płatność (`POST /v1/payments`) i zwraca `redirectUrl`, na który
 *    kierujemy kupującego, oraz `paymentId` (globalnie unikalny — po nim webhook
 *    odnajdzie zamówienie);
 *  - podpisuje żądania i weryfikuje podpis powiadomień (webhooków).
 *
 * Świadome ograniczenia zakresu:
 *  - NIE zmienia statusu zamówienia ani nie wysyła maili — to robi wołający
 *    (checkout przy tworzeniu, webhook przy potwierdzeniu przez `OrderStatusChanger`).
 *  - NIE sprawdza uprawnienia pakietu — brama `online_payments` dojdzie osobno.
 *
 * UWAGA (do potwierdzenia na sandboksie E2E): dokładny schemat podpisu i kształt
 * żądania są zgodne z dokumentacją Paynow v1 — podpis to Base64(HMAC-SHA256) po
 * surowym ciele, klucz = „klucz obliczania podpisu". Metoda `sign()` jest
 * celowo wyizolowana, żeby ewentualna korekta schematu dotknęła jednego miejsca.
 */
class PaynowService
{
    /**
     * Statusy Paynow oznaczające, że pieniądze wpłynęły. Tylko z tego stanu
     * przenosimy zamówienie na „Opłacone".
     */
    public const STATUS_CONFIRMED = 'CONFIRMED';

    /**
     * Tworzy płatność w Paynow dla zamówienia i zwraca dane do przekierowania:
     * `paymentId`, `redirectUrl`, `status`. null, gdy brak konfiguracji lub API
     * zwróciło błąd — wołający decyduje, co wtedy pokazać kupującemu.
     *
     * `$continueUrl` = adres, na który Paynow odeśle kupującego po płatności
     * (strona podziękowania sklepu). Notyfikacja (webhook) jest niezależna i to
     * ona, a nie ten powrót, jest źródłem prawdy o zapłacie.
     *
     * @return array{paymentId: string, redirectUrl: string, status: string}|null
     */
    public function createPayment(Order $order, string $continueUrl): ?array
    {
        $order->loadMissing('shop');
        $shop = $order->shop;

        $apiKey = $shop?->paynowApiKey();
        $signatureKey = $shop?->paynowSignatureKey();
        $baseUrl = $this->baseUrl($shop);

        if (blank($apiKey) || blank($signatureKey) || blank($baseUrl)) {
            Log::channel('paynow')->warning('Paynow pominięty: brak konfiguracji.', [
                'order_id' => $order->id,
                'shop_id' => $shop?->id,
            ]);

            return null;
        }

        $body = $this->encode([
            'amount' => $this->grosze($order->total_gross),
            'currency' => 'PLN',
            // externalId musi być unikalny po stronie Paynow, a kupujący może
            // ponowić płatność (np. po porzuceniu pierwszej) — dlatego doklejamy
            // losowy sufiks do numeru zamówienia. Korelacja z zamówieniem i tak
            // idzie po paymentId (zapisywanym przy każdej próbie), nie po externalId.
            'externalId' => $order->number.'-'.Str::lower(Str::random(6)),
            'description' => 'Zamówienie #'.$order->number,
            'buyer' => ['email' => $order->buyer_email],
            'continueUrl' => $continueUrl,
        ]);

        // Audyt PRZED wysyłką — bez kluczy (sekrety trzymamy z boku).
        Log::channel('paynow')->info('Paynow: tworzenie płatności.', [
            'order_id' => $order->id,
            'shop_id' => $shop->id,
            'amount' => $this->grosze($order->total_gross),
            'environment' => $shop->paynowEnvironment(),
        ]);

        try {
            $response = Http::withHeaders([
                'Api-Key' => $apiKey,
                'Signature' => $this->sign($signatureKey, $body),
                'Idempotency-Key' => (string) Str::uuid(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(20)->withBody($body, 'application/json')
                ->post(rtrim($baseUrl, '/').'/v1/payments');
        } catch (\Throwable $e) {
            Log::channel('paynow')->error('Paynow: wyjątek połączenia.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful() || blank($response->json('paymentId')) || blank($response->json('redirectUrl'))) {
            Log::channel('paynow')->error('Paynow: nieoczekiwana odpowiedź.', [
                'order_id' => $order->id,
                'http_status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        Log::channel('paynow')->info('Paynow: płatność utworzona.', [
            'order_id' => $order->id,
            'payment_id' => $response->json('paymentId'),
            'status' => $response->json('status'),
        ]);

        return [
            'paymentId' => (string) $response->json('paymentId'),
            'redirectUrl' => (string) $response->json('redirectUrl'),
            'status' => (string) $response->json('status'),
        ];
    }

    /**
     * Czy podpis powiadomienia zgadza się z ciałem żądania. Weryfikacja niewrażliwa
     * na czas (`hash_equals`), żeby nie wyciekać informacji timingiem. Wołający
     * najpierw odnajduje zamówienie po `paymentId`, stąd ma klucz podpisu sklepu.
     */
    public function verifyNotificationSignature(string $signatureKey, string $rawBody, ?string $providedSignature): bool
    {
        if (blank($providedSignature)) {
            return false;
        }

        return hash_equals($this->sign($signatureKey, $rawBody), $providedSignature);
    }

    /**
     * Podpis Paynow: Base64(HMAC-SHA256(klucz podpisu, ciało)). Jedno miejsce dla
     * żądań wychodzących i weryfikacji webhooków — patrz uwaga w docblocku klasy.
     */
    public function sign(string $signatureKey, string $body): string
    {
        return base64_encode(hash_hmac('sha256', $body, $signatureKey, true));
    }

    /**
     * Adres API operatora dla środowiska sklepu (sandbox/produkcja). null, gdy
     * brak sklepu — wtedy i tak nie ma z czym się łączyć.
     */
    private function baseUrl(?Shop $shop): ?string
    {
        if ($shop === null) {
            return null;
        }

        return config('services.paynow.base_url.'.$shop->paynowEnvironment());
    }

    /**
     * Kwota w groszach (Paynow oczekuje liczby całkowitej najmniejszych jednostek).
     * Zaokrąglamy przed rzutowaniem, żeby grosz nie uciekł na błędzie float.
     */
    private function grosze(string|float $gross): int
    {
        return (int) round(((float) $gross) * 100);
    }

    /**
     * Serializacja ciała żądania. Podpisujemy DOKŁADNIE ten sam string, który
     * wysyłamy — stąd jedna funkcja kodująca (bez ponownego `json_encode` gdzie
     * indziej), by podpis liczył się z bajt-w-bajt identycznego ciała.
     *
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
