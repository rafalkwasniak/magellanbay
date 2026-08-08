<?php

namespace Tests\Feature\Shipping;

use App\Enums\DeliveryMethod;
use App\Enums\IntegrationType;
use App\Enums\ParcelSize;
use App\Enums\SendingMethod;
use App\Services\Shipping\ParcelSpec;
use App\Jobs\CreateInpostShipment;
use App\Livewire\Seller\OrderShipment;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nadawanie przesyłek z karty zamówienia: bramka, job, ponowienie i etykieta.
 * Wszystkie wywołania InPostu zaślepione — suita nigdy nie nadaje paczek.
 */
class OrderShipmentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Shop} */
    private function sellerWithShipx(bool $enabled = true): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->withCourierShipping()->create(['owner_id' => $seller->id]);
        $shop->integrations()->create([
            'type' => IntegrationType::Shipping,
            'enabled' => $enabled,
            'config' => ['token' => 'TAJNY', 'organization_id' => '6700', 'environment' => 'sandbox'],
        ]);

        return [$seller, $shop->fresh()];
    }

    private function lockerOrder(Shop $shop, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'delivery_method' => DeliveryMethod::ParcelLocker,
            'parcel_locker_code' => 'KRA01A',
            'buyer_phone' => '+48888000111',
        ], $attributes));
    }

    private function courierOrder(Shop $shop, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'delivery_method' => DeliveryMethod::Courier,
            'parcel_locker_code' => null,
            'ship_street' => 'Kościuszki',
            'ship_building_number' => '157',
            'ship_postal_code' => '35-005',
            'ship_city' => 'Rzeszów',
            'buyer_phone' => '+48888000111',
        ], $attributes));
    }

    public function test_seller_describes_courier_parcel_with_dimensions(): void
    {
        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->courierOrder($shop);

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('Nadaj przesyłkę')
            ->assertSee('Wymiary i waga paczki')
            // Gabaryt skrytki przy kurierze nie znaczy nic — nie może się pojawić.
            ->assertDontSee('Gabaryt paczki');
    }

    public function test_courier_order_without_address_cannot_be_shipped(): void
    {
        [, $shop] = $this->sellerWithShipx();
        $order = $this->courierOrder($shop, ['ship_street' => null, 'ship_city' => null]);

        $this->assertFalse($order->canBeShipped());
    }

    public function test_courier_parcel_is_prefilled_from_shop_defaults(): void
    {
        [$seller, $shop] = $this->sellerWithShipx();
        $shop->update([
            'courier_parcel_length_cm' => 30,
            'courier_parcel_width_cm' => 20,
            'courier_parcel_height_cm' => 10,
            'courier_parcel_weight_kg' => 2.5,
        ]);

        $order = $this->courierOrder($shop->fresh());

        Livewire::actingAs($seller)
            ->test(OrderShipment::class, ['order' => $order])
            ->assertSet('length', '30')
            ->assertSet('width', '20')
            ->assertSet('height', '10')
            // Waga po polsku, z przecinkiem — tak samo jak ceny w Ustawieniach.
            ->assertSet('weight', '2,5');
    }

    public function test_courier_dispatch_sends_dimensions_and_sending_method(): void
    {
        Queue::fake();

        [$seller, $shop] = $this->sellerWithShipx();
        $shop->update(['shipment_sending_method' => SendingMethod::DispatchOrder]);
        $order = $this->courierOrder($shop->fresh());

        Livewire::actingAs($seller)
            ->test(OrderShipment::class, ['order' => $order])
            // Sposób nadania podpowiedziany z Ustawień sklepu.
            ->assertSet('sendingMethod', 'dispatch_order')
            ->set('length', '40')
            ->set('width', '30')
            // Przecinek dziesiętny — tak wpisuje polski sprzedawca.
            ->set('height', '15')
            ->set('weight', '3,5')
            ->call('ask')
            ->call('create');

        Queue::assertPushed(CreateInpostShipment::class, function ($job) use ($order) {
            return $job->order->is($order)
                && $job->parcel->size === null
                && $job->parcel->lengthCm === 40
                && $job->parcel->heightCm === 15
                && $job->parcel->weightKg === 3.5
                && $job->sending === SendingMethod::DispatchOrder;
        });
    }

    public function test_seller_can_override_sending_method_for_a_single_parcel(): void
    {
        Queue::fake();

        [$seller, $shop] = $this->sellerWithShipx();
        // W Ustawieniach domyślnie paczkomat, ale TA paczka ma pojechać kurierem.
        $order = $this->lockerOrder($shop);

        Livewire::actingAs($seller)
            ->test(OrderShipment::class, ['order' => $order])
            ->assertSet('sendingMethod', 'parcel_locker')
            ->set('sendingMethod', 'dispatch_order')
            ->call('ask')
            ->call('create');

        Queue::assertPushed(
            CreateInpostShipment::class,
            fn ($job) => $job->sending === SendingMethod::DispatchOrder
        );
    }

    public function test_unknown_sending_method_is_rejected_at_dispatch(): void
    {
        Queue::fake();

        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop);

        // Wartość jedzie wprost do ShipX i jest tam wiążąca — PaczkoPunkt (`pok`)
        // wymagałby jeszcze wskazania punktu, więc nie ma go w enumie.
        Livewire::actingAs($seller)
            ->test(OrderShipment::class, ['order' => $order])
            ->set('sendingMethod', 'pok')
            ->call('create')
            ->assertHasErrors('sendingMethod');

        Queue::assertNothingPushed();
    }

    public function test_courier_parcel_is_validated_against_inpost_limits(): void
    {
        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->courierOrder($shop);

        Livewire::actingAs($seller)
            ->test(OrderShipment::class, ['order' => $order])
            ->set('length', '150')
            ->set('width', '20')
            ->set('height', '10')
            ->set('weight', '30')
            ->call('ask')
            ->assertHasErrors(['length', 'weight'])
            ->assertSet('confirming', false);
    }

    public function test_courier_parcel_requires_all_values(): void
    {
        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->courierOrder($shop);

        Livewire::actingAs($seller)
            ->test(OrderShipment::class, ['order' => $order])
            ->set('length', '')
            ->set('width', '')
            ->set('height', '')
            ->set('weight', '')
            ->call('create')
            ->assertHasErrors(['length', 'width', 'height', 'weight']);

        $this->assertFalse($order->fresh()->hasShipment());
    }

    public function test_seller_sees_dispatch_button_for_locker_order(): void
    {
        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop);

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('Nadaj przesyłkę')
            ->assertSee('Gabaryt paczki');
    }

    public function test_button_is_hidden_when_integration_is_off(): void
    {
        [$seller, $shop] = $this->sellerWithShipx(enabled: false);
        $order = $this->lockerOrder($shop);

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Nadaj przesyłkę');
    }

    public function test_button_is_hidden_for_courier_delivery_without_address(): void
    {
        // Kurier JEST już obsługiwany — ale bez adresu nie ma dokąd nadać, więc
        // przycisk nie może obiecywać nadania, które InPost i tak odrzuci.
        // (Wcześniej ten test pilnował czegoś innego: że kuriera nie robimy
        // w ogóle. To już nieprawda.)
        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, [
            'delivery_method' => DeliveryMethod::Courier,
            'parcel_locker_code' => null,
        ]);

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Nadaj przesyłkę');
    }

    public function test_dispatching_queues_the_job(): void
    {
        Queue::fake();
        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop);

        Livewire::actingAs($seller)
            ->test(OrderShipment::class, ['order' => $order])
            ->set('size', 'medium')
            // Nadanie idzie przez potwierdzenie: „Nadaj" tylko je otwiera.
            ->call('ask')
            ->assertSet('confirming', true)
            ->assertSee('Gabaryt B')
            ->assertSee('KRA01A')
            ->call('create');

        Queue::assertPushed(CreateInpostShipment::class, fn ($job) => $job->order->is($order) && $job->parcel->size === ParcelSize::B);
    }

    public function test_confirmation_can_be_dismissed_without_dispatching(): void
    {
        Queue::fake();
        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop);

        Livewire::actingAs($seller)
            ->test(OrderShipment::class, ['order' => $order])
            ->call('ask')
            ->call('dismiss')
            ->assertSet('confirming', false);

        Queue::assertNothingPushed();
    }

    public function test_dispatching_immediately_shows_pending_state(): void
    {
        // Regresja: „Nadawanie…" musi zapalić się OD RAZU po kliknięciu, a nie
        // dopiero gdy kolejka (cron, do minuty) wykona zadanie. Inaczej przycisk
        // wraca do postaci wyjściowej i sprzedawca myśli, że klik nie zadziałał.
        Queue::fake();
        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop);

        $order->requestShipment(ParcelSpec::locker(ParcelSize::A), SendingMethod::ParcelLocker);

        $order->refresh();
        $this->assertTrue($order->isShipmentPending());
        $this->assertFalse($order->canBeShipped(), 'W trakcie nadawania nie wolno zlecić drugi raz.');
        $this->assertNull($order->shipment_id, 'Numer przesyłki nadaje dopiero InPost.');
    }

    public function test_stuck_queued_order_is_released_for_retry(): void
    {
        // Kolejka padła i zadanie nigdy nie ruszyło — po kwadransie oddajemy
        // sprzedawcy możliwość ponowienia zamiast wiecznego „Nadajemy…".
        Http::fake();
        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, [
            'shipment_status' => Order::SHIPMENT_QUEUED,
            'shipment_queued_at' => now()->subMinutes(20),
        ]);

        $this->artisan('shipments:refresh')->assertSuccessful();

        $order->refresh();
        $this->assertFalse($order->isShipmentPending());
        $this->assertTrue($order->canBeShipped());
        $this->assertStringContainsString('Spróbuj ponownie', $order->shipment_error);
    }

    public function test_job_stores_shipment_trace(): void
    {
        Http::fake(['*' => Http::response([
            'id' => 14180408,
            'status' => 'created',
            'tracking_number' => null,
        ], 201)]);

        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop);

        (new CreateInpostShipment($order, ParcelSpec::locker(ParcelSize::A), SendingMethod::ParcelLocker))->handle(app(\App\Services\Shipping\ShipxClient::class));

        $order->refresh();
        $this->assertSame(14180408, (int) $order->shipment_id);
        $this->assertSame('created', $order->shipment_status);
        $this->assertSame(ParcelSize::A, $order->shipment_size);
        $this->assertTrue($order->hasShipment());
        $this->assertTrue($order->isShipmentPending());
    }

    public function test_job_is_idempotent(): void
    {
        Http::fake(['*' => Http::response(['id' => 1, 'status' => 'created'], 201)]);

        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, ['shipment_id' => 999, 'shipment_status' => 'confirmed']);

        (new CreateInpostShipment($order, ParcelSpec::locker(ParcelSize::A), SendingMethod::ParcelLocker))->handle(app(\App\Services\Shipping\ShipxClient::class));

        // Druga paczka NIE powstaje — każde nadanie kosztuje sprzedawcę.
        Http::assertNothingSent();
        $this->assertSame(999, (int) $order->fresh()->shipment_id);
    }

    public function test_job_records_hidden_purchase_failure(): void
    {
        // ShipX zwraca 200 przy nieudanym zakupie — powód siedzi w `transactions`.
        Http::fake(['*' => Http::response([
            'id' => 14180403,
            'status' => 'offer_selected',
            'transactions' => [['status' => 'failure', 'details' => ['error' => 'debt_collection']]],
        ], 201)]);

        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop);

        (new CreateInpostShipment($order, ParcelSpec::locker(ParcelSize::A), SendingMethod::ParcelLocker))->handle(app(\App\Services\Shipping\ShipxClient::class));

        $order->refresh();
        $this->assertStringContainsString('Brak środków', $order->shipment_error);
        $this->assertFalse($order->isShipmentPending());
        $this->assertTrue($order->canBeShipped(), 'Po błędzie musi dać się ponowić.');
    }

    public function test_retry_clears_previous_failed_shipment(): void
    {
        Queue::fake();
        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, [
            'shipment_id' => 111,
            'shipment_status' => 'offer_selected',
            'shipment_error' => 'Brak środków na koncie InPost. Zasil konto i spróbuj ponownie.',
        ]);

        $order->requestShipment(ParcelSpec::locker(ParcelSize::A), SendingMethod::ParcelLocker);

        // Ślad nieudanej przesyłki znika, inaczej guard `hasShipment()` w jobie
        // zablokowałby ponowienie i sprzedawca utknąłby na zawsze.
        $order->refresh();
        $this->assertNull($order->shipment_id);
        $this->assertNull($order->shipment_error);
        Queue::assertPushed(CreateInpostShipment::class);
    }

    public function test_paid_shipment_cannot_be_dispatched_again(): void
    {
        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, ['shipment_id' => 5, 'shipment_status' => 'confirmed']);

        $this->assertFalse($order->canBeShipped());
        $this->assertFalse($order->requestShipment(ParcelSpec::locker(ParcelSize::A), SendingMethod::ParcelLocker));
    }

    public function test_label_is_streamed_from_inpost(): void
    {
        Http::fake(['*' => Http::response('%PDF-1.4 etykieta', 200)]);

        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, [
            'shipment_id' => 14180408,
            'shipment_status' => 'confirmed',
            'shipment_tracking_number' => '642582187600000017868961',
        ]);

        $response = $this->actingAs($seller)->get(route('seller.orders.label', $order));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('%PDF-1.4 etykieta', $response->getContent());
    }

    public function test_tracking_link_is_hidden_on_sandbox_and_shown_on_production(): void
    {
        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, [
            'shipment_id' => 5,
            'shipment_status' => 'confirmed',
            'shipment_tracking_number' => '642582187600000017868961',
        ]);

        // Sandbox: numeru nie ma w wyszukiwarce InPostu, więc zamiast martwego
        // linku pokazujemy wyjaśnienie.
        $this->actingAs($seller)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Śledź przesyłkę')
            ->assertSee('Nadanie testowe (sandbox)');

        $shop->integration(IntegrationType::Shipping)->update([
            'config' => ['token' => 'TAJNY', 'organization_id' => '203242', 'environment' => 'production'],
        ]);

        $this->actingAs($seller->fresh())
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('Śledź przesyłkę')
            ->assertDontSee('Nadanie testowe (sandbox)');
    }

    public function test_label_is_not_available_before_shipment_is_paid(): void
    {
        Http::fake();

        [$seller, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, ['shipment_id' => 1, 'shipment_status' => 'offer_selected']);

        $this->actingAs($seller)->get(route('seller.orders.label', $order))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_label_of_foreign_order_is_forbidden(): void
    {
        Http::fake();

        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, ['shipment_id' => 1, 'shipment_status' => 'confirmed']);

        [$intruder] = $this->sellerWithShipx();

        $this->actingAs($intruder)->get(route('seller.orders.label', $order))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_refresh_command_updates_status_and_tracking(): void
    {
        Http::fake(['*' => Http::response([
            'id' => 14180408,
            'status' => 'confirmed',
            'tracking_number' => '642582187600000017868961',
            'transactions' => [['status' => 'success', 'details' => []]],
        ], 200)]);

        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, [
            'shipment_id' => 14180408,
            'shipment_status' => 'offer_selected',
            'shipped_at' => now(),
        ]);

        $this->artisan('shipments:refresh')->assertSuccessful();

        $order->refresh();
        $this->assertSame('confirmed', $order->shipment_status);
        $this->assertSame('642582187600000017868961', $order->shipment_tracking_number);
        $this->assertTrue($order->isShipmentReady());
    }

    public function test_delivery_pass_records_pickup_date_and_sharpens_withdrawal_deadline(): void
    {
        Http::fake(['*' => Http::response([
            'id' => 14180408,
            'status' => 'delivered',
            'tracking_number' => '642582187600000017868961',
        ], 200)]);

        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, [
            'shipment_id' => 14180408,
            'shipment_status' => 'confirmed',
            'shipped_at' => now()->subDays(2),
        ]);

        // Przed odbiorem termin w ogóle nie biegnie (zamówienie niezrealizowane).
        $this->assertNull($order->withdrawalDeadline());

        $this->artisan('shipments:refresh --deliveries')->assertSuccessful();

        $order->refresh();
        $this->assertNotNull($order->delivered_at, 'Data odbioru zapisuje się z chwilą doręczenia.');

        // Po odbiorze liczymy DOKŁADNIE 14 dni od niego — bez zapasu, bo nie ma
        // czego kompensować. To jedyny wariant zgodny z ustawą co do dnia.
        $this->assertTrue(
            $order->withdrawalDeadline()->isSameDay($order->delivered_at->copy()->addDays(14)),
            'Termin liczony od daty odbioru.'
        );

        // Status zamówienia zostaje NIETKNIĘTY — o „Zrealizowane" decyduje sprzedawca.
        $this->assertNotSame(\App\Enums\OrderStatus::Completed, $order->status);
    }

    public function test_pickup_date_is_recorded_only_once(): void
    {
        Http::fake(['*' => Http::response(['id' => 1, 'status' => 'delivered'], 200)]);

        [, $shop] = $this->sellerWithShipx();
        $recorded = now()->subDays(3);
        $order = $this->lockerOrder($shop, [
            'shipment_id' => 1,
            'shipment_status' => 'delivered',
            'shipped_at' => now()->subDays(5),
            'delivered_at' => $recorded,
        ]);

        $this->artisan('shipments:refresh --deliveries')->assertSuccessful();

        // Pierwotna data odbioru jest faktem — kolejne przebiegi jej nie nadpisują.
        $this->assertTrue($order->fresh()->delivered_at->isSameSecond($recorded));
    }

    public function test_withdrawal_box_is_shown_regardless_of_inpost(): void
    {
        [$seller, $shop] = $this->sellerWithShipx();

        // Odbiór osobisty, zero wspólnego z InPostem — karta i tak ma być,
        // bo termin na odstąpienie dotyczy zamówienia, nie przesyłki.
        $pickup = Order::factory()->create([
            'shop_id' => $shop->id,
            'delivery_method' => \App\Enums\DeliveryMethod::Pickup,
            'status' => \App\Enums\OrderStatus::Completed,
        ]);
        \App\Models\OrderItem::factory()->create(['order_id' => $pickup->id]);
        $pickup->statusEvents()->create([
            'from_status' => \App\Enums\OrderStatus::Processing,
            'to_status' => \App\Enums\OrderStatus::Completed,
        ]);

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $pickup))
            ->assertOk()
            ->assertSee('Odstąpienie od umowy')
            ->assertSee('Szacowany');

        // Z potwierdzoną datą odbioru ta sama karta mówi „dokładnie".
        $delivered = $this->lockerOrder($shop, [
            'shipment_id' => 9,
            'shipment_status' => 'delivered',
            'delivered_at' => now()->subDay(),
        ]);
        \App\Models\OrderItem::factory()->create(['order_id' => $delivered->id]);

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $delivered))
            ->assertOk()
            ->assertSee('Odstąpienie od umowy')
            ->assertSee('Liczony dokładnie');
    }

    public function test_withdrawal_window_does_not_expire_before_the_order_is_fulfilled(): void
    {
        // REGRESJA: rzecz robiona ręcznie przez trzy tygodnie. Wcześniej termin
        // liczyliśmy od DATY ZŁOŻENIA, więc „mijał", zanim klient dostał paczkę —
        // i zamykał formularz zwrotu, choć prawo dopiero zaczynało biec.
        [, $shop] = $this->sellerWithShipx();
        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'status' => \App\Enums\OrderStatus::Processing,
            'created_at' => now()->subDays(40),
        ]);
        \App\Models\OrderItem::factory()->create(['order_id' => $order->id]);
        $order->refresh();

        $this->assertNull($order->withdrawalDeadline(), 'Bez realizacji termin nie ma od czego biec.');
        $this->assertTrue($order->withinWithdrawalWindow(), 'Prawo klienta nie może wygasnąć przed dostawą.');

        // Ale FORMULARZ zwrotu jest jeszcze zamknięty — nie ma czego odsyłać.
        $this->assertFalse($order->hasBeenHandedOver());
        $this->assertFalse($order->acceptsReturns());
    }

    public function test_return_form_opens_only_after_handover(): void
    {
        [, $shop] = $this->sellerWithShipx();
        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'status' => \App\Enums\OrderStatus::Processing,
        ]);
        \App\Models\OrderItem::factory()->create(['order_id' => $order->id]);
        $order->refresh();

        // Przed wydaniem: strona tłumaczy, zamiast pokazywać formularz.
        $this->get('https://'.$shop->host().'/zwrot/'.$order->paymentToken())
            ->assertOk()
            ->assertSee('Zwrot zgłosisz po otrzymaniu zamówienia')
            ->assertDontSee('Wyślij oświadczenie o odstąpieniu');

        // Po odbiorze paczki formularz się otwiera — bez udziału sprzedawcy.
        $order->forceFill(['delivered_at' => now()])->save();
        $order->refresh();

        $this->assertTrue($order->hasBeenHandedOver());
        $this->assertTrue($order->acceptsReturns());

        $this->get('https://'.$shop->host().'/zwrot/'.$order->paymentToken())
            ->assertOk()
            ->assertSee('Wyślij oświadczenie o odstąpieniu');
    }

    public function test_account_explains_upcoming_return_option_before_handover(): void
    {
        [, $shop] = $this->sellerWithShipx();
        $customer = \App\Models\Customer::factory()->create(['shop_id' => $shop->id]);
        $order = $this->lockerOrder($shop, [
            'customer_id' => $customer->id,
            'status' => \App\Enums\OrderStatus::Processing,
        ]);
        \App\Models\OrderItem::factory()->create(['order_id' => $order->id]);

        // Karta „Zwrot" ma być WIDOCZNA już teraz — klient ma wiedzieć, że taka
        // droga istnieje, zamiast szukać jej na próżno.
        $this->actingAs($customer, 'customer')
            ->get('https://'.$shop->host().'/moje-konto/zamowienia/'.$order->id)
            ->assertOk()
            ->assertSee('Masz prawo odstąpić od umowy')
            ->assertSee('Formularz zwrotu pojawi się tutaj')
            ->assertDontSee('Zgłoś zwrot');
    }

    public function test_return_submission_is_rejected_before_handover(): void
    {
        // Bramka nie może być tylko wizualna — POST prosto na adres też odpada.
        [, $shop] = $this->sellerWithShipx();
        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'status' => \App\Enums\OrderStatus::Processing,
        ]);
        $item = \App\Models\OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 2]);
        $order->refresh();

        $this->post('https://'.$shop->host().'/zwrot/'.$order->paymentToken(), [
            'quantities' => [$item->id => 1],
            'customer_name' => 'Jan Testowy',
            'customer_address' => 'Polna 1, 00-001 Warszawa',
        ]);

        $this->assertSame('0.00', $item->fresh()->returned_quantity);
    }

    public function test_withdrawal_deadline_counts_from_completion_not_from_order_date(): void
    {
        [, $shop] = $this->sellerWithShipx();
        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'status' => \App\Enums\OrderStatus::Completed,
            'created_at' => now()->subDays(40),
        ]);
        \App\Models\OrderItem::factory()->create(['order_id' => $order->id]);
        $completedAt = now()->subDays(2);
        $order->statusEvents()->create([
            'from_status' => \App\Enums\OrderStatus::Processing,
            'to_status' => \App\Enums\OrderStatus::Completed,
        ]);
        // `created_at` nie jest wypełnialne (zdarzenie jest niezmienne), więc datę
        // cofamy osobno — ten sam chwyt co w WithdrawalNoticeTest.
        $order->statusEvents()->first()->forceFill(['created_at' => $completedAt])->save();
        $order->refresh();

        $expected = $completedAt->copy()
            ->addDays(config('legal.withdrawal.days') + config('legal.withdrawal.delivery_buffer_days'));

        $this->assertTrue($order->withdrawalDeadline()->isSameDay($expected));
        $this->assertTrue($order->withinWithdrawalWindow());
    }

    public function test_customer_sees_tracking_number_in_account(): void
    {
        [, $shop] = $this->sellerWithShipx();
        $customer = \App\Models\Customer::factory()->create(['shop_id' => $shop->id]);
        $order = $this->lockerOrder($shop, [
            'customer_id' => $customer->id,
            'shipment_id' => 5,
            'shipment_status' => 'confirmed',
            'shipment_tracking_number' => '642582187600000017868961',
        ]);

        $this->actingAs($customer, 'customer')
            ->get('https://'.$shop->host().'/moje-konto/zamowienia/'.$order->id)
            ->assertOk()
            ->assertSee('Numer przesyłki')
            ->assertSee('642582187600000017868961');
    }

    public function test_customer_is_mailed_when_the_parcel_is_dispatched(): void
    {
        Http::fake(['*' => Http::response([
            'id' => 14180408,
            'status' => 'confirmed',
            'tracking_number' => '642582187600000017868961',
        ], 200)]);

        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, [
            'shipment_id' => 14180408,
            'shipment_status' => 'offer_selected',
            'shipped_at' => now(),
        ]);

        $this->artisan('shipments:refresh')->assertSuccessful();

        $mail = \App\Models\EmailMessage::where('to_email', $order->buyer_email)->latest('id')->first();
        $this->assertNotNull($mail, 'Klient musi dostać maila o nadaniu paczki.');
        $this->assertStringContainsString('Paczka w drodze', $mail->subject);
        $this->assertStringContainsString('642582187600000017868961', json_encode($mail->intro_lines));

        // Drugi przebieg nic nie zmienia → żadnego dubla.
        $before = \App\Models\EmailMessage::count();
        $this->artisan('shipments:refresh')->assertSuccessful();
        $this->assertSame($before, \App\Models\EmailMessage::count());
    }

    public function test_dispatch_mail_for_courier_speaks_of_address_not_locker(): void
    {
        Http::fake(['*' => Http::response([
            'id' => 14180666,
            'status' => 'confirmed',
            'tracking_number' => '642582087600718021276453',
        ], 200)]);

        [, $shop] = $this->sellerWithShipx();
        $order = $this->courierOrder($shop, [
            'shipment_id' => 14180666,
            'shipment_status' => 'offer_selected',
            'shipped_at' => now(),
        ]);

        $this->artisan('shipments:refresh')->assertSuccessful();

        $mail = \App\Models\EmailMessage::where('to_email', $order->buyer_email)->latest('id')->first();
        $body = json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Adres dostawy', $body);
        $this->assertStringContainsString('Kościuszki 157', $body);
        // Przy kurierze NIE MA kodu odbioru ani skrytki — obietnica SMS-a
        // z kodem byłaby po prostu nieprawdą.
        $this->assertStringNotContainsString('paczkomat', mb_strtolower($body));
        $this->assertStringNotContainsString('kod do odbioru', mb_strtolower($body));
    }

    public function test_thank_you_mail_says_delivered_for_courier(): void
    {
        Http::fake(['*' => Http::response([
            'id' => 14180666,
            'status' => 'delivered',
            'tracking_number' => '642582087600718021276453',
        ], 200)]);

        [, $shop] = $this->sellerWithShipx();
        $order = $this->courierOrder($shop, [
            'shipment_id' => 14180666,
            'shipment_status' => 'confirmed',
            'shipped_at' => now()->subDay(),
        ]);

        $this->artisan('shipments:refresh', ['--deliveries' => true])->assertSuccessful();

        $mail = \App\Models\EmailMessage::where('to_email', $order->buyer_email)
            ->where('subject', 'like', 'Dziękujemy%')->latest('id')->first();

        $this->assertNotNull($mail, 'Klient musi dostać maila po doręczeniu paczki.');
        $this->assertStringContainsString('została dostarczona', json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE));
    }

    public function test_customer_is_thanked_and_told_about_returns_after_pickup(): void
    {
        Http::fake(['*' => Http::response([
            'id' => 14180408,
            'status' => 'delivered',
            'tracking_number' => '642582187600000017868961',
        ], 200)]);

        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, [
            'shipment_id' => 14180408,
            'shipment_status' => 'confirmed',
            'shipped_at' => now()->subDays(2),
        ]);
        \App\Models\OrderItem::factory()->create(['order_id' => $order->id]);

        $this->artisan('shipments:refresh --deliveries')->assertSuccessful();

        $mail = \App\Models\EmailMessage::where('to_email', $order->buyer_email)->latest('id')->first();
        $this->assertNotNull($mail);
        $this->assertStringContainsString('Dziękujemy za zakupy', $mail->subject);
        // Link do formularza zwrotu — bo w zamówieniu jest towar objęty prawem.
        $this->assertSame('Zgłoś zwrot', $mail->action_text);
        $this->assertStringContainsString('/zwrot/', (string) $mail->action_url);

        $before = \App\Models\EmailMessage::count();
        $this->artisan('shipments:refresh --deliveries')->assertSuccessful();
        $this->assertSame($before, \App\Models\EmailMessage::count(), 'Podziękowanie wysyłamy raz.');
    }

    public function test_thank_you_mail_omits_return_link_when_nothing_qualifies(): void
    {
        Http::fake(['*' => Http::response(['id' => 1, 'status' => 'delivered'], 200)]);

        [, $shop] = $this->sellerWithShipx();
        $product = \App\Models\Product::factory()->create([
            'shop_id' => $shop->id,
            'withdrawal_excluded' => true,
        ]);
        $order = $this->lockerOrder($shop, [
            'shipment_id' => 1,
            'shipment_status' => 'confirmed',
            'shipped_at' => now()->subDay(),
        ]);
        \App\Models\OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        $this->artisan('shipments:refresh --deliveries')->assertSuccessful();

        $mail = \App\Models\EmailMessage::where('to_email', $order->buyer_email)->latest('id')->first();
        $this->assertNotNull($mail);
        $this->assertNull($mail->action_text, 'Bez towaru objętego prawem nie zapraszamy do zwrotu.');
    }

    public function test_refresh_command_keeps_trace_when_api_returns_404(): void
    {
        // Sporadyczne 404 na istniejącej przesyłce nie może skasować śladu.
        Http::fake(['*' => Http::response(['error' => 'resource_not_found'], 404)]);

        [, $shop] = $this->sellerWithShipx();
        $order = $this->lockerOrder($shop, [
            'shipment_id' => 14180408,
            'shipment_status' => 'offer_selected',
            'shipped_at' => now(),
        ]);

        $this->artisan('shipments:refresh')->assertSuccessful();

        $order->refresh();
        $this->assertSame(14180408, (int) $order->shipment_id);
        $this->assertSame('offer_selected', $order->shipment_status);
        $this->assertNull($order->shipment_error);
    }
}
