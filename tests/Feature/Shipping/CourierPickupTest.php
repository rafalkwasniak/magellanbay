<?php

namespace Tests\Feature\Shipping;

use App\Enums\DeliveryMethod;
use App\Enums\IntegrationType;
use App\Enums\SendingMethod;
use App\Livewire\Seller\CourierPickup;
use App\Models\DispatchOrder;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Zamawianie odbioru paczek przez kuriera InPostu — JEDNO zlecenie na wiele
 * przesyłek. Wszystkie wywołania zaślepione: suita nigdy nie zamawia kuriera.
 *
 * Odpowiedzi odwzorowują kształty z sandboxa (2026-08-08), łącznie z tym, co
 * najbardziej zaskakuje: utworzenie zlecenia zwraca 201, a odrzucenie
 * przychodzi dopiero przy kolejnym odpytaniu.
 */
class CourierPickupTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Shop} */
    private function sellerWithShipx(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->withCourierShipping()->create([
            'owner_id' => $seller->id,
            'street' => 'Kościuszki',
            'building_number' => '157',
            'postal_code' => '35-005',
            'city' => 'Rzeszów',
        ]);
        $shop->integrations()->create([
            'type' => IntegrationType::Shipping,
            'enabled' => true,
            'config' => ['token' => 'TAJNY', 'organization_id' => '6700', 'environment' => 'sandbox'],
        ]);

        return [$seller, $shop->fresh()];
    }

    private function awaitingPickupOrder(Shop $shop, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'delivery_method' => DeliveryMethod::ParcelLocker,
            'parcel_locker_code' => 'KRA01A',
            'shipment_id' => random_int(1000, 999999),
            'shipment_status' => 'confirmed',
            'shipment_sending_method' => SendingMethod::DispatchOrder,
            'shipped_at' => now(),
        ], $attributes));
    }

    public function test_one_request_covers_many_parcels(): void
    {
        Http::fake(['*' => Http::response(['id' => 68050, 'status' => 'new'], 201)]);

        [$seller, $shop] = $this->sellerWithShipx();
        $first = $this->awaitingPickupOrder($shop);
        $second = $this->awaitingPickupOrder($shop, ['delivery_method' => DeliveryMethod::Courier, 'parcel_locker_code' => null]);

        Livewire::actingAs($seller)
            ->test(CourierPickup::class)
            // Domyślnie zaznaczone wszystko — „zamawiam po to, co dziś nadałem”.
            ->assertSet('selected', [$first->id, $second->id])
            ->call('ask')
            ->call('request');

        // Paczkomatowa i kurierska w JEDNYM zleceniu — obie da się oddać kurierowi.
        Http::assertSent(function (Request $request) use ($first, $second) {
            $body = $request->data();

            return str_contains($request->url(), '/dispatch_orders')
                && count($body['shipments']) === 2
                && in_array((int) $first->shipment_id, $body['shipments'], true)
                && in_array((int) $second->shipment_id, $body['shipments'], true)
                && $body['address']['street'] === 'Kościuszki'
                && $body['address']['post_code'] === '35-005';
        });

        $dispatchOrder = DispatchOrder::first();
        $this->assertSame(68050, (int) $dispatchOrder->shipx_id);
        $this->assertSame($dispatchOrder->id, $first->fresh()->dispatch_order_id);
        $this->assertSame($dispatchOrder->id, $second->fresh()->dispatch_order_id);
    }

    public function test_parcels_dropped_at_locker_never_reach_the_courier_request(): void
    {
        Http::fake(['*' => Http::response(['id' => 68051, 'status' => 'new'], 201)]);

        [$seller, $shop] = $this->sellerWithShipx();
        $this->awaitingPickupOrder($shop, ['shipment_sending_method' => SendingMethod::ParcelLocker]);

        // Taka paczka jest u InPostu w stanie `CustomerDelivering` i wywróciłaby
        // CAŁE zlecenie, nie tylko własną pozycję.
        Livewire::actingAs($seller)
            ->test(CourierPickup::class)
            ->assertSet('selected', [])
            ->assertSee('Nie ma paczek czekających na kuriera');

        Http::assertNothingSent();
    }

    public function test_parcel_already_in_a_request_is_not_offered_again(): void
    {
        Http::fake(['*' => Http::response(['id' => 68052, 'status' => 'new'], 201)]);

        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->awaitingPickupOrder($shop);

        Livewire::actingAs($seller)->test(CourierPickup::class)->call('ask')->call('request');

        $this->assertNotNull($order->fresh()->dispatch_order_id);

        Livewire::actingAs($seller)
            ->test(CourierPickup::class)
            ->assertSet('selected', []);
    }

    public function test_rejected_request_releases_parcels_and_explains_why(): void
    {
        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->awaitingPickupOrder($shop);

        // Dwie różne odpowiedzi rozróżnione ADRESEM, nie kolejnością wywołań:
        // powtórne `Http::fake()` nie nadpisuje wcześniejszej zaślepki, tylko
        // dokłada się za nią — i wygrywa ta pierwsza.
        Http::fake([
            '*/organizations/*/dispatch_orders' => Http::response(['id' => 68053, 'status' => 'new'], 201),
            // InPost rozstrzyga zlecenie DOPIERO PO chwili — i potrafi je
            // odrzucić, mimo że utworzenie zwróciło 201.
            '*/v1/dispatch_orders/*' => Http::response([
                'id' => 68053,
                'status' => 'rejected',
                'errors' => ['details' => 'Parcel (6425...) status should be Prepared, but is CustomerDelivering.'],
            ], 200),
        ]);

        Livewire::actingAs($seller)->test(CourierPickup::class)->call('ask')->call('request');

        $this->assertNotNull($order->fresh()->dispatch_order_id);

        $this->artisan('shipments:refresh')->assertSuccessful();

        $dispatchOrder = DispatchOrder::first()->fresh();
        $this->assertTrue($dispatchOrder->isRejected());
        $this->assertStringContainsString('wrzucę do Paczkomatu', $dispatchOrder->error);

        // Paczka wraca na listę — inaczej sprzedawca nie mógłby zamówić ponownie.
        $this->assertNull($order->fresh()->dispatch_order_id);
    }

    public function test_accepted_request_is_reported_as_confirmed(): void
    {
        [$seller, $shop] = $this->sellerWithShipx();
        $this->awaitingPickupOrder($shop);

        Http::fake([
            '*/organizations/*/dispatch_orders' => Http::response(['id' => 68054, 'status' => 'new'], 201),
            '*/v1/dispatch_orders/*' => Http::response(['id' => 68054, 'status' => 'sent', 'errors' => null], 200),
        ]);

        Livewire::actingAs($seller)->test(CourierPickup::class)->call('ask')->call('request');

        $this->artisan('shipments:refresh')->assertSuccessful();

        $this->assertTrue(DispatchOrder::first()->fresh()->isAccepted());
    }

    public function test_shop_without_address_cannot_order_a_courier(): void
    {
        Http::fake();

        [$seller, $shop] = $this->sellerWithShipx();
        $shop->update(['street' => null, 'building_number' => null]);
        $this->awaitingPickupOrder($shop->fresh());

        Livewire::actingAs($seller)
            ->test(CourierPickup::class)
            ->assertSee('Uzupełnij adres sklepu');

        Http::assertNothingSent();
    }

    public function test_orders_list_nudges_when_parcels_wait_for_a_courier(): void
    {
        [$seller, $shop] = $this->sellerWithShipx();
        $this->awaitingPickupOrder($shop);

        $this->actingAs($seller)
            ->get(route('seller.orders.index'))
            ->assertOk()
            ->assertSee('paczka czeka na kuriera')
            ->assertSee('Zamów kuriera');
    }

    public function test_orders_list_stays_quiet_without_parcels_to_collect(): void
    {
        [$seller, $shop] = $this->sellerWithShipx();
        // Paczka wrzucana do paczkomatu nie wymaga zamawiania odbioru.
        $this->awaitingPickupOrder($shop, ['shipment_sending_method' => SendingMethod::ParcelLocker]);

        $this->actingAs($seller)
            ->get(route('seller.orders.index'))
            ->assertOk()
            ->assertDontSee('Zamów kuriera');
    }

    public function test_screen_is_closed_without_shipx(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->withCourierShipping()->create(['owner_id' => $seller->id]);

        $this->actingAs($seller)
            ->get(route('seller.shipments.pickup'))
            ->assertRedirect(route('seller.orders.index'));
    }
}
