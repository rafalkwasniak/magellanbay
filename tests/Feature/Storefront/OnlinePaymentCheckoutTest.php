<?php

namespace Tests\Feature\Storefront;

use App\Enums\IntegrationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Livewire\Checkout;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Żywa ścieżka płatności online: kasa oferuje metodę tylko gdy sklep ją włączył,
 * ekran podsumowania niesie przycisk „Przejdź do płatności" dla nieopłaconego
 * zamówienia online, a trasa inicjująca tworzy płatność w Paynow i przekierowuje
 * kupującego. Kontrakt z operatorem jest zaślepiony — sprawdzamy naszą stronę.
 */
class OnlinePaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function shopWithOnlinePayments(): Shop
    {
        $shop = Shop::factory()->create([
            'street' => 'Kwiatowa', 'building_number' => '5', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'province' => 'mazowieckie',
            'pickup_enabled' => true, 'pay_on_pickup_enabled' => true,
        ]);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'API', 'signature_key' => 'SIGN', 'environment' => 'sandbox'],
        ]);

        return $shop->fresh();
    }

    private function base(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    public function test_checkout_offers_online_method_only_when_enabled(): void
    {
        $shop = $this->shopWithOnlinePayments();

        $options = Livewire::test(Checkout::class, ['shopId' => $shop->id])->instance()->paymentOptions();
        $this->assertArrayHasKey(PaymentMethod::Online->value, $options);

        // Wyłączenie integracji zdejmuje metodę z kasy — sama konfiguracja nie wystarcza.
        $shop->integration(IntegrationType::Payments)->update(['enabled' => false]);
        $options = Livewire::test(Checkout::class, ['shopId' => $shop->fresh()->id])->instance()->paymentOptions();
        $this->assertArrayNotHasKey(PaymentMethod::Online->value, $options);
    }

    public function test_confirmation_shows_pay_button_for_unpaid_online_order(): void
    {
        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::Online,
        ]);

        $this->withSession(['recent_order_id' => $order->id])
            ->get($this->base($shop).'/kasa/dziekujemy')
            ->assertOk()
            ->assertSee('Przejdź do płatności');
    }

    public function test_confirmation_shows_paid_state_and_no_button_once_paid(): void
    {
        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::Paid,
            'payment_method' => PaymentMethod::Online,
        ]);

        $this->withSession(['recent_order_id' => $order->id])
            ->get($this->base($shop).'/kasa/dziekujemy')
            ->assertOk()
            ->assertSee('Płatność potwierdzona')
            ->assertDontSee('Przejdź do płatności');
    }

    public function test_pay_creates_payment_and_redirects_to_paynow(): void
    {
        Http::fake(['*/v1/payments' => Http::response([
            'paymentId' => 'PAY-9', 'redirectUrl' => 'https://paynow.pl/go/9', 'status' => 'NEW',
        ], 201)]);

        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::Online,
            'total_gross' => 50,
        ]);

        $this->withSession(['recent_order_id' => $order->id])
            ->post($this->base($shop).'/kasa/platnosc')
            ->assertRedirect('https://paynow.pl/go/9');

        $order->refresh();
        $this->assertSame('PAY-9', $order->payment_external_id);
        $this->assertSame('NEW', $order->payment_status);
    }

    public function test_pay_refuses_non_online_order(): void
    {
        Http::fake();
        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::New,
            'payment_method' => PaymentMethod::PayOnPickup,
        ]);

        $this->withSession(['recent_order_id' => $order->id])
            ->post($this->base($shop).'/kasa/platnosc')
            ->assertRedirect($this->base($shop).'/kasa/dziekujemy');

        Http::assertNothingSent();
    }

    public function test_pay_without_session_order_redirects_home(): void
    {
        Http::fake();
        $shop = $this->shopWithOnlinePayments();

        $this->post($this->base($shop).'/kasa/platnosc')
            ->assertRedirect($this->base($shop));

        Http::assertNothingSent();
    }

    public function test_pay_falls_back_to_confirmation_with_error_when_gateway_fails(): void
    {
        Http::fake(['*/v1/payments' => Http::response(['error' => 'nope'], 500)]);

        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::Online,
        ]);

        $this->withSession(['recent_order_id' => $order->id])
            ->post($this->base($shop).'/kasa/platnosc')
            ->assertRedirect($this->base($shop).'/kasa/dziekujemy')
            ->assertSessionHas('error');

        $this->assertNull($order->refresh()->payment_external_id);
    }
}
