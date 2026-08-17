<?php

namespace Tests\Feature\Shipping;

use App\Enums\DeliveryMethod;
use App\Enums\IntegrationType;
use App\Enums\ParcelSize;
use App\Enums\SendingMethod;
use App\Exceptions\OrderEditException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Services\OrderEditor;
use App\Services\Shipping\ParcelSpec;
use App\Services\Shipping\ShipxClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Nadanie przesyłki POBRANIOWEJ i jej konsekwencje. Wywołania zaślepione —
 * suita nigdy nie strzela do InPostu.
 *
 * Kształty odpowiedzi odwzorowują sondę z sandboxu (17.08): nieudany zakup
 * przesyłki pobraniowej NIE jest błędem HTTP, tylko `transactions[].details`
 * przy statusie `offer_selected`.
 */
class CashOnDeliveryShipmentTest extends TestCase
{
    use RefreshDatabase;

    private function shopWithShipx(): Shop
    {
        $shop = Shop::factory()->withCourierShipping()->create();
        $shop->integrations()->create([
            'type' => IntegrationType::Shipping,
            'enabled' => true,
            'config' => ['token' => 'TAJNY-TOKEN', 'organization_id' => '6700', 'environment' => 'sandbox'],
        ]);

        return $shop->fresh();
    }

    private function codOrder(Shop $shop, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'delivery_method' => DeliveryMethod::ParcelLockerCod,
            'parcel_locker_code' => 'KRA01A',
            'buyer_name' => 'Jan',
            'buyer_surname' => 'Testowy',
            'buyer_email' => 'jan@example.com',
            'buyer_phone' => '+48888000111',
            'total_gross' => 149.50,
        ], $attributes));
    }

    public function test_przesylka_pobraniowa_niesie_kwote_i_ubezpieczenie(): void
    {
        Http::fake(['sandbox-api-shipx-pl.easypack24.net/*' => Http::response(['id' => 14204470, 'status' => 'created'], 201)]);

        $shop = $this->shopWithShipx();
        $order = $this->codOrder($shop);

        app(ShipxClient::class)->createShipment($order, ParcelSpec::locker(ParcelSize::A), SendingMethod::ParcelLocker);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            // Kwota pobrania = CAŁA suma zamówienia (produkty po rabacie + dostawa).
            return $body['cod'] === ['amount' => 149.5, 'currency' => 'PLN']
                // InPost odrzuca pobranie z niższym ubezpieczeniem
                // („Insurance should be equal or higher than COD"), więc ta sama kwota.
                && $body['insurance'] === ['amount' => 149.5, 'currency' => 'PLN'];
        });
    }

    public function test_przesylka_bez_pobrania_nie_niesie_tych_pol(): void
    {
        Http::fake(['sandbox-api-shipx-pl.easypack24.net/*' => Http::response(['id' => 14204469, 'status' => 'created'], 201)]);

        $shop = $this->shopWithShipx();
        $order = $this->codOrder($shop, ['delivery_method' => DeliveryMethod::ParcelLocker]);

        app(ShipxClient::class)->createShipment($order, ParcelSpec::locker(ParcelSize::A), SendingMethod::ParcelLocker);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return ! isset($body['cod']) && ! isset($body['insurance']);
        });
    }

    public function test_brak_danych_konta_tlumaczy_sie_na_konkretna_instrukcje(): void
    {
        // Dokładny kształt z sondy 17.08. Komunikat InPostu mówi o danych
        // adresowych, a brakowało numeru rachunku — nasz tekst musi prowadzić
        // sprzedawcę tam, gdzie problem NAPRAWDĘ jest.
        $reason = ShipxClient::failureReason([
            'status' => 'offer_selected',
            'transactions' => [[
                'status' => 'failure',
                'details' => ['status' => 422, 'error' => 'company_data_missing', 'message' => 'customer_lack_of_address_data'],
            ]],
        ]);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('rachunku bankowego', $reason);
        $this->assertStringContainsString('Managerze Paczek', $reason);
    }

    public function test_zamowienia_pobraniowego_nie_da_sie_edytowac_po_nadaniu(): void
    {
        $shop = $this->shopWithShipx();
        $order = $this->codOrder($shop, ['shipment_id' => 14204470]);
        $item = $this->itemFor($order);

        $this->expectException(OrderEditException::class);
        $this->expectExceptionMessage('kwota pobrania');

        app(OrderEditor::class)->changeQuantity($item, 2);
    }

    public function test_przed_nadaniem_edycja_pobraniowego_dziala(): void
    {
        // Granicą jest NADANIE, nie metoda płatności: dopóki paczka nie poszła,
        // nic nie zostało u InPostu zamrożone i nie ma czego chronić.
        $shop = $this->shopWithShipx();
        $order = $this->codOrder($shop, ['shipment_id' => null]);
        $item = $this->itemFor($order);

        app(OrderEditor::class)->changeQuantity($item, 3);

        $this->assertSame('3.00', $item->fresh()->quantity);
    }

    private function itemFor(Order $order): OrderItem
    {
        $product = Product::factory()->create([
            'shop_id' => $order->shop_id,
            'track_stock' => false,
            'stock' => null,
            'price_gross' => 50.00,
        ]);

        return OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }
}
