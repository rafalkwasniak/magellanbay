<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Shop;
use App\Services\PaynowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tworzenie płatności w Paynow oraz podpis żądań/weryfikacja webhooków. Kontrakt
 * sieciowy jest zaślepiony (`Http::fake`) — sprawdzamy NASZĄ stronę: właściwy
 * adres środowiska, nagłówki z kluczem i podpisem, kwotę w groszach, oraz że bez
 * konfiguracji nic nie wychodzi. Realny kontrakt z operatorem weryfikuje dopiero
 * test E2E na sandboksie.
 */
class PaynowServiceTest extends TestCase
{
    use RefreshDatabase;

    private function configuredShop(string $environment = 'sandbox'): Shop
    {
        $shop = Shop::factory()->create();
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'API-KEY-1', 'signature_key' => 'SIGN-KEY-1', 'environment' => $environment],
        ]);

        return $shop->fresh();
    }

    public function test_create_payment_hits_sandbox_with_signed_request_and_returns_redirect(): void
    {
        Http::fake([
            'api.sandbox.paynow.pl/v1/payments' => Http::response([
                'paymentId' => 'PAY-123',
                'redirectUrl' => 'https://paynow.pl/redirect/abc',
                'status' => 'NEW',
            ], 201),
        ]);

        $shop = $this->configuredShop('sandbox');
        $order = Order::factory()->for($shop)->create([
            'number' => 5001,
            'payment_method' => PaymentMethod::Online,
            'total_gross' => 123.45,
            'buyer_email' => 'kupujacy@example.com',
        ]);

        $result = app(PaynowService::class)->createPayment($order, 'https://sklep.kramio.pl/kasa/dziekujemy');

        $this->assertNotNull($result);
        $this->assertSame('PAY-123', $result['paymentId']);
        $this->assertSame('https://paynow.pl/redirect/abc', $result['redirectUrl']);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://api.sandbox.paynow.pl/v1/payments'
                && $request->hasHeader('Api-Key', 'API-KEY-1')
                && filled($request->header('Signature'))
                && filled($request->header('Idempotency-Key'))
                && $body['amount'] === 12345           // grosze
                && $body['currency'] === 'PLN'
                && str_starts_with($body['externalId'], '5001-')   // numer + sufiks na ponowienia
                && $body['buyer']['email'] === 'kupujacy@example.com';
        });
    }

    public function test_create_payment_uses_production_url_for_production_environment(): void
    {
        Http::fake(['api.paynow.pl/v1/payments' => Http::response(['paymentId' => 'P', 'redirectUrl' => 'https://x', 'status' => 'NEW'], 201)]);

        $shop = $this->configuredShop('production');
        $order = Order::factory()->for($shop)->create(['payment_method' => PaymentMethod::Online, 'total_gross' => 10]);

        app(PaynowService::class)->createPayment($order, 'https://x/ok');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.paynow.pl/v1/payments');
    }

    public function test_create_payment_returns_null_without_configuration(): void
    {
        Http::fake();

        $order = Order::factory()->create(['payment_method' => PaymentMethod::Online, 'total_gross' => 10]);

        $this->assertNull(app(PaynowService::class)->createPayment($order, 'https://x/ok'));
        Http::assertNothingSent();
    }

    public function test_create_payment_returns_null_on_error_response(): void
    {
        Http::fake(['*/v1/payments' => Http::response(['error' => 'bad'], 400)]);

        $shop = $this->configuredShop();
        $order = Order::factory()->for($shop)->create(['payment_method' => PaymentMethod::Online, 'total_gross' => 10]);

        $this->assertNull(app(PaynowService::class)->createPayment($order, 'https://x/ok'));
    }

    public function test_signature_round_trips_and_rejects_tampering(): void
    {
        $svc = app(PaynowService::class);
        $signature = $svc->sign('SIGN-KEY-1', '{"paymentId":"PAY-1","status":"CONFIRMED"}');

        $this->assertTrue($svc->verifyNotificationSignature('SIGN-KEY-1', '{"paymentId":"PAY-1","status":"CONFIRMED"}', $signature));
        // Zły podpis, zmienione ciało i zły klucz — każde psuje weryfikację.
        $this->assertFalse($svc->verifyNotificationSignature('SIGN-KEY-1', '{"paymentId":"PAY-1","status":"CONFIRMED"}', 'zły-podpis'));
        $this->assertFalse($svc->verifyNotificationSignature('SIGN-KEY-1', '{"paymentId":"PAY-1","status":"REJECTED"}', $signature));
        $this->assertFalse($svc->verifyNotificationSignature('INNY-KLUCZ', '{"paymentId":"PAY-1","status":"CONFIRMED"}', $signature));
        $this->assertFalse($svc->verifyNotificationSignature('SIGN-KEY-1', '{}', null));
    }
}
