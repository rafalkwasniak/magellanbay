<?php

namespace App\Services\Shipping;

use App\Enums\SendingMethod;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klient API InPost ShipX — nadawanie przesyłek (paczkomat oraz kurier pod
 * adres) i pobieranie etykiet. Konfiguracja jest PER-SKLEP (token + Organization ID + środowisko
 * z `shop_integrations`, zaszyfrowane): przesyłkę nadaje sprzedawca ze swojego
 * konta i ze swojego salda, a Kramio niczego nie pośredniczy.
 *
 * BEZPIECZEŃSTWO: token ma zakres `api:shipx`, czyli umie nadawać paczki na
 * koszt sprzedawcy. Wolno go używać WYŁĄCZNIE tutaj, po stronie serwera —
 * nigdy w HTML (mapa paczkomatów w kasie chodzi na osobnym tokenie platformy
 * o zakresie `api:apipoints`). Do logów token NIE trafia.
 *
 * Zachowania API potwierdzone empirycznie na sandboxie (2026-08-07) — te
 * fakty są ważniejsze niż dokumentacja i sterują kodem poniżej:
 *  1. Utworzenie przesyłki zwraca status `created`, który sam przechodzi na
 *     `offer_selected` (tryb uproszczony sam wybiera ofertę).
 *  2. Przy ZASILONYM koncie zakup dzieje się AUTOMATYCZNIE (`confirmed`) —
 *     dlatego NIE wołamy `buy` w ciemno; odpytujemy status.
 *  3. Nieudany zakup NIE zgłasza się błędem HTTP: `buy` zwraca 200, a prawdziwa
 *     przyczyna leży w `transactions[].details` (np. `debt_collection` = brak
 *     środków). Stąd `failureReason()`.
 *  4. Etykieta jest dostępna dopiero od statusu `confirmed`; wcześniej API
 *     odpowiada 400 `shipment_status_incorrect`.
 *  5. API potrafi zwrócić 404 na ISTNIEJĄCĄ przesyłkę przy kilku szybkich
 *     zapytaniach pod rząd — 404 przy odpytywaniu NIE znaczy „przesyłka
 *     zniknęła" i nie może kasować śladu w bazie.
 */
class ShipxClient
{
    /** Statusy, przy których przesyłka jest opłacona i etykieta jest gotowa. */
    public const READY_STATUSES = ['confirmed', 'dispatched_by_sender', 'collected_from_sender', 'taken_by_courier', 'adopted_at_source_branch', 'sent_from_source_branch', 'delivered'];

    /**
     * Tworzy przesyłkę dla zamówienia — paczkomatową albo kurierską pod adres,
     * zależnie od metody dostawy wybranej przez klienta. Zwraca surową odpowiedź
     * ShipX (m.in. `id`, `status`, `tracking_number`) albo null, gdy sklep nie
     * jest skonfigurowany lub API odmówiło.
     *
     * @return array<string, mixed>|null
     */
    public function createShipment(Order $order, ParcelSpec $parcel, SendingMethod $sending): ?array
    {
        $order->loadMissing('shop');
        $shop = $order->shop;

        if ($shop === null || ! $shop->shipxConfigured()) {
            Log::channel('shipx')->warning('Nadanie pominięte: brak konfiguracji ShipX.', [
                'order_id' => $order->id,
                'shop_id' => $shop?->id,
            ]);

            return null;
        }

        if (! $order->hasShipmentDestination()) {
            Log::channel('shipx')->warning('Nadanie pominięte: zamówienie bez celu dostawy.', [
                'order_id' => $order->id,
                'delivery_method' => $order->delivery_method?->value,
            ]);

            return null;
        }

        $payload = $this->buildPayload($order, $parcel, $sending);

        Log::channel('shipx')->info('ShipX: tworzenie przesyłki.', [
            'order_id' => $order->id,
            'shop_id' => $shop->id,
            'environment' => $shop->shipxEnvironment(),
            'payload' => $payload,
        ]);

        $response = $this->send(
            $shop,
            fn (string $base, string $token) => Http::withToken($token)
                ->acceptJson()
                ->timeout($this->timeout())
                ->post($base.'/v1/organizations/'.$shop->shipxOrganizationId().'/shipments', $payload),
            ['order_id' => $order->id]
        );

        if ($response === null || ! $response->successful()) {
            Log::channel('shipx')->error('ShipX: nie udało się utworzyć przesyłki.', [
                'order_id' => $order->id,
                'status' => $response?->status(),
                'body' => $response?->body(),
            ]);

            return null;
        }

        $data = $response->json();

        Log::channel('shipx')->info('ShipX: przesyłka utworzona.', [
            'order_id' => $order->id,
            'shipment_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
        ]);

        return is_array($data) ? $data : null;
    }

    /**
     * Stan przesyłki. Zwraca null przy błędzie połączenia LUB 404 — wołający
     * musi potraktować null jako „nie wiem", nie jako „nie istnieje" (patrz
     * punkt 5 w opisie klasy).
     *
     * @return array<string, mixed>|null
     */
    public function shipment(Shop $shop, int $shipmentId): ?array
    {
        $response = $this->send(
            $shop,
            fn (string $base, string $token) => Http::withToken($token)
                ->acceptJson()
                ->timeout($this->timeout())
                ->get($base.'/v1/shipments/'.$shipmentId),
            ['shipment_id' => $shipmentId]
        );

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Zamawia jeden przyjazd kuriera po WSZYSTKIE wskazane przesyłki.
     *
     * Adres podajemy wprost, zamiast wskazywać zapisany punkt odbioru z panelu
     * InPostu — API traktuje to jako „albo–albo" (podanie obu naraz kończy się
     * błędem `dispatch_point_and_address_cannot_be_mixed`), a wariant z adresem
     * nie wymaga od sprzedawcy ŻADNEJ konfiguracji po stronie InPostu.
     *
     * UWAGA: 201 tutaj NIE znaczy, że kurier przyjedzie. InPost weryfikuje
     * zlecenie asynchronicznie i potrafi je odrzucić chwilę później — stan
     * trzeba odpytać przez {@see dispatchOrder()}.
     *
     * @param  array<int, int>  $shipmentIds
     * @param  array<string, string>  $address
     * @return array<string, mixed>|null
     */
    public function createDispatchOrder(Shop $shop, array $shipmentIds, array $address, ?string $comment = null): ?array
    {
        $payload = array_filter([
            'shipments' => array_values($shipmentIds),
            'address' => $address,
            'comment' => $comment,
        ], fn ($value) => filled($value));

        Log::channel('shipx')->info('ShipX: zamawianie odbioru kuriera.', [
            'shop_id' => $shop->id,
            'shipments' => $payload['shipments'],
        ]);

        $response = $this->send(
            $shop,
            fn (string $base, string $token) => Http::withToken($token)
                ->acceptJson()
                ->timeout($this->timeout())
                ->post($base.'/v1/organizations/'.$shop->shipxOrganizationId().'/dispatch_orders', $payload),
            ['shop_id' => $shop->id]
        );

        if ($response === null || ! $response->successful()) {
            Log::channel('shipx')->error('ShipX: nie udało się zamówić odbioru.', [
                'shop_id' => $shop->id,
                'status' => $response?->status(),
                'body' => $response?->body(),
            ]);

            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Stan zlecenia odbioru. null = „nie wiem" (błąd sieci albo sporadyczne 404
     * na istniejącym zasobie) — nie kasować śladu w bazie.
     *
     * @return array<string, mixed>|null
     */
    public function dispatchOrder(Shop $shop, int $dispatchOrderId): ?array
    {
        $response = $this->send(
            $shop,
            fn (string $base, string $token) => Http::withToken($token)
                ->acceptJson()
                ->timeout($this->timeout())
                ->get($base.'/v1/dispatch_orders/'.$dispatchOrderId),
            ['dispatch_order_id' => $dispatchOrderId]
        );

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Powód odrzucenia zlecenia — po polsku, gotowy dla sprzedawcy. InPost
     * zwraca surowy komunikat walidacji po angielsku, więc najczęstszy przypadek
     * tłumaczymy na zdanie, z którym da się coś zrobić.
     *
     * @param  array<string, mixed>  $dispatchOrder
     */
    public static function dispatchFailureReason(array $dispatchOrder): ?string
    {
        if (($dispatchOrder['status'] ?? null) !== 'rejected') {
            return null;
        }

        $details = $dispatchOrder['errors']['details'] ?? null;
        $details = is_string($details) ? $details : null;

        // Najczęstsza przyczyna: paczka zadeklarowana jako „wrzucę do
        // paczkomatu". InPost nazywa ten stan `CustomerDelivering`.
        if ($details !== null && str_contains($details, 'CustomerDelivering')) {
            return 'InPost odrzucił zamówienie kuriera: któraś z paczek została nadana jako „wrzucę do Paczkomatu”. Takiej kurier nie odbierze — nadaj ją ponownie z wyborem odbioru przez kuriera.';
        }

        return 'InPost odrzucił zamówienie kuriera'.($details !== null ? ' ('.$details.')' : '.');
    }

    /**
     * Etykieta w PDF (surowa zawartość pliku) albo null, gdy jeszcze nie ma
     * czego drukować. Wołać dopiero, gdy `isReady()` — inaczej API odpowie 400.
     */
    public function label(Shop $shop, int $shipmentId): ?string
    {
        $response = $this->send(
            $shop,
            fn (string $base, string $token) => Http::withToken($token)
                ->timeout($this->timeout())
                ->get($base.'/v1/shipments/'.$shipmentId.'/label', ['format' => 'pdf', 'type' => 'normal']),
            ['shipment_id' => $shipmentId]
        );

        if ($response === null || ! $response->successful()) {
            Log::channel('shipx')->warning('ShipX: etykieta niedostępna.', [
                'shipment_id' => $shipmentId,
                'status' => $response?->status(),
                'body' => $response?->body(),
            ]);

            return null;
        }

        Log::channel('shipx')->info('ShipX: pobrano etykietę.', [
            'shipment_id' => $shipmentId,
            'bytes' => strlen($response->body()),
        ]);

        return $response->body();
    }

    /**
     * Czy przesyłka jest opłacona i gotowa do wydrukowania etykiety.
     *
     * @param  array<string, mixed>  $shipment
     */
    public static function isReady(array $shipment): bool
    {
        return in_array($shipment['status'] ?? null, self::READY_STATUSES, true);
    }

    /**
     * Powód nieudanego zakupu przesyłki — po polsku, gotowy dla sprzedawcy.
     * ShipX nie zgłasza tego błędem HTTP (punkt 3 w opisie klasy), więc bez
     * zaglądania w `transactions` przesyłka po prostu wisiałaby bez wyjaśnienia.
     *
     * Zwraca null, gdy nic nie zawiodło (albo zawiodło, ale późniejsza próba
     * się powiodła — liczy się OSTATNIA transakcja).
     *
     * @param  array<string, mixed>  $shipment
     */
    public static function failureReason(array $shipment): ?string
    {
        $transactions = $shipment['transactions'] ?? [];

        if (! is_array($transactions) || $transactions === []) {
            return null;
        }

        $last = end($transactions);

        if (! is_array($last) || ($last['status'] ?? null) !== 'failure') {
            return null;
        }

        $error = $last['details']['error'] ?? null;

        return match ($error) {
            'debt_collection' => 'Brak środków na koncie InPost. Zasil konto i spróbuj ponownie.',
            'offer_expired' => 'Oferta InPostu wygasła. Spróbuj nadać przesyłkę ponownie.',
            default => 'InPost odrzucił nadanie'.(is_string($error) && $error !== '' ? ' (kod: '.$error.')' : '').'.',
        };
    }

    /**
     * Ciało żądania tworzącego przesyłkę. Telefon odbiorcy jest OBOWIĄZKOWY —
     * przy paczkomacie InPost wysyła na niego SMS z kodem odbioru, przy kurierze
     * służy do awizacji; kasa wymaga go przy każdym zamówieniu, więc zawsze jest.
     *
     * `sending_method` wysyłamy ZAWSZE i jawnie. Bez niego każda oferta
     * kurierska wraca jako niedostępna (`sending_method_required`), a domyślne
     * ustawienie z panelu InPostu przez API nie działa. Wartość jest WIĄŻĄCA —
     * patrz {@see SendingMethod}.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(Order $order, ParcelSpec $parcel, SendingMethod $sending): array
    {
        $toLocker = $order->delivery_method?->requiresParcelLocker() === true;

        $receiver = array_filter([
            'first_name' => $order->buyer_name,
            'last_name' => $order->buyer_surname,
            'email' => $order->buyer_email,
            'phone' => $this->localPhone($order->buyer_phone),
        ], fn ($value) => filled($value));

        if (! $toLocker) {
            $receiver['address'] = $this->receiverAddress($order);
        }

        return [
            'receiver' => $receiver,
            'parcels' => [$parcel->toShipxParcel()],
            'custom_attributes' => array_filter([
                'sending_method' => $sending->value,
                'target_point' => $toLocker ? $order->parcel_locker_code : null,
            ], fn ($value) => filled($value)),
            'service' => config('services.inpost.shipx.service.'.($toLocker ? 'locker' : 'courier')),
            'reference' => 'Zamówienie '.$order->number,
        ];
    }

    /**
     * Adres odbiorcy w kształcie ShipX.
     *
     * PUŁAPKA: obiekt adresu w ShipX NIE MA pola na numer mieszkania — są tylko
     * `street` i `building_number`. Nasz numer lokalu trzeba więc dokleić do
     * numeru budynku, inaczej kurier dojedzie pod samą klatkę.
     *
     * @return array<string, string>
     */
    private function receiverAddress(Order $order): array
    {
        $building = (string) $order->ship_building_number;

        if (filled($order->ship_apartment_number)) {
            $building .= '/'.$order->ship_apartment_number;
        }

        return [
            'street' => (string) $order->ship_street,
            'building_number' => $building,
            'city' => (string) $order->ship_city,
            'post_code' => (string) $order->ship_postal_code,
            'country_code' => 'PL',
        ];
    }

    /**
     * Telefon w postaci krajowej (9 cyfr) — InPost odrzuca numery z prefiksem
     * `+48`, a my trzymamy je znormalizowane właśnie z prefiksem.
     */
    private function localPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (str_starts_with($digits, '48') && strlen($digits) === 11) {
            $digits = substr($digits, 2);
        }

        return $digits === '' ? null : $digits;
    }

    /**
     * Wspólna obsługa wywołania: adres środowiska sklepu + token, wyjątek
     * połączenia zamieniony na null (nigdy nie wywracamy panelu sprzedawcy
     * przez awarię po stronie InPostu).
     *
     * @param  callable(string, string): Response  $call
     * @param  array<string, mixed>  $context
     */
    private function send(Shop $shop, callable $call, array $context = []): ?Response
    {
        $base = $shop->shipxBaseUrl();
        $token = $shop->shipxToken();

        if (blank($base) || blank($token)) {
            return null;
        }

        try {
            return $call($base, $token);
        } catch (\Throwable $e) {
            Log::channel('shipx')->error('ShipX: wyjątek połączenia.', $context + [
                'shop_id' => $shop->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function timeout(): int
    {
        return (int) config('services.inpost.shipx.timeout', 30);
    }
}
