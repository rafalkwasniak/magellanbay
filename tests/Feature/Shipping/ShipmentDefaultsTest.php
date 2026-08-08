<?php

namespace Tests\Feature\Shipping;

use App\Enums\SendingMethod;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Domyślne ustawienia nadawania per sklep i migawka paczki na zamówieniu.
 * Sam model danych — ekrany dochodzą w kolejnych krokach.
 */
class ShipmentDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nowy_sklep_nadaje_domyslnie_przez_paczkomat(): void
    {
        $shop = Shop::factory()->create();

        // Domyślna wartość pochodzi z bazy — sklep założony przed tą funkcją
        // ma zachować się tak samo, dlatego osobno sprawdzamy fallback niżej.
        $this->assertSame(SendingMethod::ParcelLocker, $shop->sendingMethod());
        $this->assertFalse($shop->sendingMethod()->isPaid());
    }

    public function test_sposob_nadania_zapisuje_sie_i_wraca_jako_enum(): void
    {
        $shop = Shop::factory()->create();

        $shop->update(['shipment_sending_method' => SendingMethod::DispatchOrder]);

        $this->assertSame(SendingMethod::DispatchOrder, $shop->fresh()->sendingMethod());
    }

    public function test_swiezy_model_bez_atrybutu_nie_wybiera_platnej_opcji(): void
    {
        // W bazie kolumna jest NOT NULL z wartością domyślną, więc null trafia
        // się tylko na modelu jeszcze niezapisanym — a taki obsługuje formularz
        // ustawień. Fallback nigdy nie może wskazać opcji PŁATNEJ.
        $this->assertSame(SendingMethod::ParcelLocker, (new Shop)->sendingMethod());
    }

    public function test_domyslna_paczka_wymaga_kompletu_wymiarow_i_wagi(): void
    {
        $shop = Shop::factory()->create();

        $this->assertNull($shop->courierParcelDefaults());

        // Sam wymiar bez wagi to za mało — ShipX potrzebuje wszystkiego naraz.
        $shop->update([
            'courier_parcel_length_cm' => 30,
            'courier_parcel_width_cm' => 20,
            'courier_parcel_height_cm' => 10,
        ]);

        $this->assertNull($shop->fresh()->courierParcelDefaults());

        $shop->update(['courier_parcel_weight_kg' => 2.5]);

        $this->assertSame(
            ['length' => 30, 'width' => 20, 'height' => 10, 'weight' => 2.5],
            $shop->fresh()->courierParcelDefaults()
        );
    }

    public function test_zerowy_wymiar_nie_jest_podpowiedzia(): void
    {
        $shop = Shop::factory()->create([
            'courier_parcel_length_cm' => 30,
            'courier_parcel_width_cm' => 0,
            'courier_parcel_height_cm' => 10,
            'courier_parcel_weight_kg' => 2,
        ]);

        $this->assertNull($shop->courierParcelDefaults());
    }

    public function test_zamowienie_trzyma_migawke_nadanej_paczki(): void
    {
        $shop = Shop::factory()->create();
        $order = Order::factory()->create(['shop_id' => $shop->id]);

        $order->forceFill([
            'shipment_sending_method' => SendingMethod::DispatchOrder,
            'shipment_length_cm' => 40,
            'shipment_width_cm' => 30,
            'shipment_height_cm' => 20,
            'shipment_weight_kg' => 3.25,
        ])->save();

        $order = $order->fresh();

        $this->assertSame(SendingMethod::DispatchOrder, $order->shipment_sending_method);
        $this->assertSame(40, $order->shipment_length_cm);
        $this->assertSame('3.25', $order->shipment_weight_kg);
    }
}
