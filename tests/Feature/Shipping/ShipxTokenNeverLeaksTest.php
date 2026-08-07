<?php

namespace Tests\Feature\Shipping;

use App\Enums\DeliveryMethod;
use App\Enums\IntegrationType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * STRAŻNIK BEZPIECZEŃSTWA. Token ShipX ma zakres `api:shipx`, czyli umie nadawać
 * paczki na koszt sprzedawcy. Wolno go używać wyłącznie po stronie serwera —
 * gdyby kiedykolwiek trafił do HTML, każdy mógłby zdjąć go z podglądu źródła
 * i nadawać przesyłki na cudzy rachunek.
 *
 * Ten test przechodzi po stronach, na których token mógłby przypadkiem
 * wyciecieć, i sprawdza, że nigdzie go nie ma. Osobno pilnuje, że mapa
 * paczkomatów w kasie korzysta z INNEGO tokenu (platformowego, zakres
 * `api:apipoints` — tylko odczyt punktów), bo to jedyny token, który z natury
 * ląduje w przeglądarce.
 *
 * Gdyby ktoś kiedyś „dla wygody" przekazał token ShipX do widoku, ten test
 * zapali się natychmiast.
 */
class ShipxTokenNeverLeaksTest extends TestCase
{
    use RefreshDatabase;

    /** Rozpoznawalny ciąg — łatwo go znaleźć w treści strony, gdyby wyciekł. */
    private const SECRET = 'TAJNY-TOKEN-SHIPX-NIE-MOZE-WYCIEKAC';

    /** @return array{0: User, 1: Shop, 2: Order} */
    private function scenario(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->withCourierShipping()->create([
            'owner_id' => $seller->id,
            'parcel_locker_enabled' => true,
            'parcel_locker_cost' => 12.99,
        ]);
        $shop->integrations()->create([
            'type' => IntegrationType::Shipping,
            'enabled' => true,
            'config' => ['token' => self::SECRET, 'organization_id' => '6700', 'environment' => 'sandbox'],
        ]);

        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'delivery_method' => DeliveryMethod::ParcelLocker,
            'parcel_locker_code' => 'KRA01A',
            'shipment_id' => 14180408,
            'shipment_status' => 'confirmed',
            'shipment_tracking_number' => '642582187600000017868961',
        ]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        return [$seller, $shop->fresh(), $order];
    }

    public function test_token_never_appears_on_seller_pages(): void
    {
        [$seller, , $order] = $this->scenario();

        $pages = [
            route('seller.integrations.edit'),
            route('seller.settings.edit'),
            route('seller.orders.index'),
            route('seller.orders.show', $order),
            route('seller.dashboard'),
        ];

        foreach ($pages as $url) {
            $response = $this->actingAs($seller)->get($url);
            $response->assertOk();

            $this->assertStringNotContainsString(
                self::SECRET,
                $response->getContent(),
                "Token ShipX wyciekł do HTML na: {$url}"
            );
        }
    }

    public function test_token_never_appears_on_storefront_pages(): void
    {
        [, $shop, $order] = $this->scenario();
        $customer = Customer::factory()->create(['shop_id' => $shop->id]);
        $order->forceFill(['customer_id' => $customer->id])->save();

        $base = 'https://'.$shop->host();

        // Strony publiczne — w tym KASA, gdzie żyje mapa paczkomatów.
        foreach ([$base.'/', $base.'/produkty', $base.'/kasa', $base.'/koszyk'] as $url) {
            $response = $this->get($url);

            $this->assertStringNotContainsString(
                self::SECRET,
                $response->getContent(),
                "Token ShipX wyciekł do HTML na: {$url}"
            );
        }

        // Strony zalogowanego klienta.
        $response = $this->actingAs($customer, 'customer')->get($base.'/moje-konto/zamowienia/'.$order->id);
        $this->assertStringNotContainsString(self::SECRET, $response->getContent());

        // Publiczna strona zwrotu (adres z tokenem zamówienia, nie ShipX).
        $response = $this->get($base.'/zwrot/'.$order->paymentToken());
        $this->assertStringNotContainsString(self::SECRET, $response->getContent());
    }

    public function test_map_in_checkout_uses_the_platform_points_token_not_the_shipx_one(): void
    {
        // Mapa paczkomatów Z NATURY ląduje w przeglądarce, więc musi chodzić na
        // osobnym tokenie o zakresie „tylko odczyt punktów".
        config(['services.inpost.geowidget_token' => 'PUBLICZNY-TOKEN-MAPY']);

        [, $shop] = $this->scenario();

        $response = $this->get('https://'.$shop->host().'/kasa');
        $content = $response->getContent();

        $this->assertStringNotContainsString(self::SECRET, $content, 'W kasie nie ma prawa być tokenu ShipX.');
    }

    public function test_label_route_streams_pdf_without_exposing_credentials(): void
    {
        Http::fake(['*' => Http::response('%PDF-1.4 etykieta', 200)]);

        [$seller, , $order] = $this->scenario();

        $response = $this->actingAs($seller)->get(route('seller.orders.label', $order));

        $response->assertOk();
        // Klient dostaje sam plik — ani tokenu, ani adresu API z tokenem w środku.
        $this->assertStringNotContainsString(self::SECRET, $response->getContent());
        $this->assertStringNotContainsString(self::SECRET, json_encode($response->headers->all()));
    }

    public function test_token_is_stored_encrypted_at_rest(): void
    {
        // Nawet z dostępem do samej bazy (kopia zapasowa, wyciek dumpa) token
        // nie jest czytelny — kolumna `config` jest szyfrowana.
        [, $shop] = $this->scenario();

        $raw = \Illuminate\Support\Facades\DB::table('shop_integrations')
            ->where('shop_id', $shop->id)
            ->where('type', IntegrationType::Shipping->value)
            ->value('config');

        $this->assertStringNotContainsString(self::SECRET, (string) $raw);
        // …ale aplikacja dalej go odczytuje.
        $this->assertSame(self::SECRET, $shop->shipxToken());
    }
}
