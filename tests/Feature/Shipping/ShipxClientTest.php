<?php

namespace Tests\Feature\Shipping;

use App\Enums\DeliveryMethod;
use App\Enums\IntegrationType;
use App\Enums\ParcelSize;
use App\Models\Order;
use App\Models\Shop;
use App\Services\Shipping\ShipxClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Klient InPost ShipX. Wszystkie wywołania zaślepione (`Http::fake`) — suita
 * NIGDY nie strzela do prawdziwego API, bo każde nadanie to realna paczka za
 * realne pieniądze sprzedawcy.
 *
 * Odpowiedzi w zaślepkach odwzorowują kształty zaobserwowane na sandboxie
 * 2026-08-07, łącznie z tym, czego nie ma w dokumentacji (nieudany zakup
 * zgłoszony przez `transactions`, a nie kodem HTTP).
 */
class ShipxClientTest extends TestCase
{
    use RefreshDatabase;

    private function shopWithShipx(string $environment = 'sandbox'): Shop
    {
        $shop = Shop::factory()->withCourierShipping()->create();
        $shop->integrations()->create([
            'type' => IntegrationType::Shipping,
            'enabled' => true,
            'config' => ['token' => 'TAJNY-TOKEN', 'organization_id' => '6700', 'environment' => $environment],
        ]);

        return $shop->fresh();
    }

    private function lockerOrder(Shop $shop, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'delivery_method' => DeliveryMethod::ParcelLocker,
            'parcel_locker_code' => 'KRA01A',
            'buyer_name' => 'Jan',
            'buyer_surname' => 'Testowy',
            'buyer_email' => 'jan@example.com',
            'buyer_phone' => '+48888000111',
        ], $attributes));
    }

    public function test_creates_shipment_with_locker_payload(): void
    {
        Http::fake(['sandbox-api-shipx-pl.easypack24.net/*' => Http::response([
            'id' => 14180408,
            'status' => 'created',
            'tracking_number' => null,
        ], 201)]);

        $shop = $this->shopWithShipx();
        $order = $this->lockerOrder($shop);

        $result = app(ShipxClient::class)->createShipment($order, ParcelSize::A);

        $this->assertSame(14180408, $result['id']);

        Http::assertSent(function (Request $request) use ($order) {
            $body = $request->data();

            return str_contains($request->url(), '/v1/organizations/6700/shipments')
                && $request->hasHeader('Authorization', 'Bearer TAJNY-TOKEN')
                && $body['service'] === 'inpost_locker_standard'
                && $body['custom_attributes']['target_point'] === 'KRA01A'
                && $body['custom_attributes']['sending_method'] === 'parcel_locker'
                && $body['parcels'][0]['template'] === 'small'
                && $body['receiver']['email'] === 'jan@example.com'
                // Telefon bez prefiksu +48 — InPost przyjmuje sam numer krajowy.
                && $body['receiver']['phone'] === '888000111'
                && str_contains($body['reference'], (string) $order->number);
        });
    }

    public function test_uses_production_url_when_shop_switched_environment(): void
    {
        Http::fake(['api-shipx-pl.easypack24.net/*' => Http::response(['id' => 1, 'status' => 'created'], 201)]);

        $order = $this->lockerOrder($this->shopWithShipx('production'));

        app(ShipxClient::class)->createShipment($order, ParcelSize::B);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api-shipx-pl.easypack24.net/'));
    }

    public function test_does_not_call_api_without_configuration(): void
    {
        Http::fake();

        $shop = Shop::factory()->withCourierShipping()->create();
        $order = $this->lockerOrder($shop);

        $this->assertNull(app(ShipxClient::class)->createShipment($order, ParcelSize::A));

        Http::assertNothingSent();
    }

    public function test_does_not_call_api_without_locker_code(): void
    {
        Http::fake();

        $shop = $this->shopWithShipx();
        $order = $this->lockerOrder($shop, ['parcel_locker_code' => null]);

        $this->assertNull(app(ShipxClient::class)->createShipment($order, ParcelSize::A));

        Http::assertNothingSent();
    }

    public function test_returns_null_when_api_rejects_creation(): void
    {
        Http::fake(['*' => Http::response(['status' => 400, 'error' => 'validation_failed'], 400)]);

        $order = $this->lockerOrder($this->shopWithShipx());

        $this->assertNull(app(ShipxClient::class)->createShipment($order, ParcelSize::A));
    }

    public function test_failure_reason_reads_hidden_transaction_error(): void
    {
        // Kluczowy przypadek: zakup NIE udał się, ale API zwróciło 200 i status
        // `offer_selected`. Bez czytania `transactions` sprzedawca nie wiedziałby,
        // dlaczego paczka nie została nadana.
        $shipment = [
            'status' => 'offer_selected',
            'transactions' => [
                ['status' => 'failure', 'details' => ['status' => 422, 'error' => 'debt_collection']],
            ],
        ];

        $this->assertSame(
            'Brak środków na koncie InPost. Zasil konto i spróbuj ponownie.',
            ShipxClient::failureReason($shipment)
        );
        $this->assertFalse(ShipxClient::isReady($shipment));
    }

    public function test_failure_reason_is_null_when_last_transaction_succeeded(): void
    {
        // Liczy się OSTATNIA transakcja: nieudana próba sprzed doładowania konta
        // nie może zostawiać komunikatu o błędzie na opłaconej przesyłce.
        $shipment = [
            'status' => 'confirmed',
            'tracking_number' => '642582187600000017868961',
            'transactions' => [
                ['status' => 'failure', 'details' => ['error' => 'debt_collection']],
                ['status' => 'success', 'details' => []],
            ],
        ];

        $this->assertNull(ShipxClient::failureReason($shipment));
        $this->assertTrue(ShipxClient::isReady($shipment));
    }

    public function test_unknown_failure_code_still_produces_a_message(): void
    {
        $reason = ShipxClient::failureReason([
            'transactions' => [['status' => 'failure', 'details' => ['error' => 'something_new']]],
        ]);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('something_new', $reason);
    }

    public function test_downloads_label_pdf(): void
    {
        Http::fake(['*' => Http::response('%PDF-1.4 udawany', 200, ['Content-Type' => 'application/pdf'])]);

        $shop = $this->shopWithShipx();

        $label = app(ShipxClient::class)->label($shop, 14180408);

        $this->assertSame('%PDF-1.4 udawany', $label);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v1/shipments/14180408/label')
            && str_contains($request->url(), 'format=pdf'));
    }

    public function test_label_returns_null_before_shipment_is_paid(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 400,
            'error' => 'invalid_action',
            'message' => 'shipment_status_incorrect',
        ], 400)]);

        $this->assertNull(app(ShipxClient::class)->label($this->shopWithShipx(), 14180408));
    }

    public function test_shipment_lookup_tolerates_sporadic_404(): void
    {
        // Sandbox potrafi zwrócić 404 na ISTNIEJĄCĄ przesyłkę przy szybkich
        // zapytaniach pod rząd. null znaczy „nie wiem", nie „nie istnieje" —
        // wołający nie może na tej podstawie skasować śladu w bazie.
        Http::fake(['*' => Http::response(['status' => 404, 'error' => 'resource_not_found'], 404)]);

        $this->assertNull(app(ShipxClient::class)->shipment($this->shopWithShipx(), 14180408));
    }

    public function test_connection_exception_does_not_bubble_up(): void
    {
        Http::fake(fn () => throw new \RuntimeException('sieć padła'));

        $order = $this->lockerOrder($this->shopWithShipx());

        $this->assertNull(app(ShipxClient::class)->createShipment($order, ParcelSize::A));
    }
}
