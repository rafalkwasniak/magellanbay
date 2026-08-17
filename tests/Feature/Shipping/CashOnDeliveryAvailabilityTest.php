<?php

namespace Tests\Feature\Shipping;

use App\Enums\DeliveryMethod;
use App\Enums\IntegrationType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dostępność metod pobraniowych i ich cennik. Pobranie różni się od reszty
 * dostaw jednym warunkiem: WYMAGA skonfigurowanego InPostu, bo to on inkasuje
 * pieniądze od klienta i przelewa je sprzedawcy. Zwykły kurier może być dostawą
 * własną za 0 zł — pobraniowy nie może.
 */
class CashOnDeliveryAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function shopWithCod(bool $withInpost = true, array $attributes = []): Shop
    {
        $shop = Shop::factory()->package('booth')->create($attributes + [
            'courier_cod_enabled' => true,
            'courier_cod_cost' => 19.99,
            'parcel_locker_cod_enabled' => true,
            'parcel_locker_cod_cost' => 16.99,
        ]);

        if ($withInpost) {
            $shop->integrations()->create([
                'type' => IntegrationType::Shipping,
                'enabled' => true,
                'config' => ['token' => 'TAJNY-TOKEN', 'organization_id' => '6700', 'environment' => 'sandbox'],
            ]);
        }

        return $shop->fresh();
    }

    public function test_metody_pobraniowe_wymagaja_skonfigurowanego_inpostu(): void
    {
        $bezInpostu = $this->shopWithCod(withInpost: false);

        $this->assertFalse($bezInpostu->courierCodAvailable());
        $this->assertFalse($bezInpostu->parcelLockerCodAvailable());
        $this->assertNotContains(DeliveryMethod::CourierCod, $bezInpostu->availableDeliveryMethods());
        $this->assertNotContains(DeliveryMethod::ParcelLockerCod, $bezInpostu->availableDeliveryMethods());
    }

    public function test_z_inpostem_obie_metody_pobraniowe_sa_dostepne(): void
    {
        $shop = $this->shopWithCod();

        $this->assertTrue($shop->courierCodAvailable());
        $this->assertTrue($shop->parcelLockerCodAvailable());
        $this->assertContains(DeliveryMethod::CourierCod, $shop->availableDeliveryMethods());
        $this->assertContains(DeliveryMethod::ParcelLockerCod, $shop->availableDeliveryMethods());
    }

    public function test_pobranie_wymaga_uprawnienia_wysylki(): void
    {
        // Kram (darmowy) nie ma `courier_shipping`, więc nie ma też pobrania —
        // mimo włączonych fiszek i podłączonego InPostu.
        $kram = $this->shopWithCod();
        $kram->forceFill([
            'package' => 'stall',
            'entitlements' => config('shop.packages.stall.entitlements'),
        ])->save();

        $this->assertFalse($kram->fresh()->courierCodAvailable());
        $this->assertFalse($kram->fresh()->parcelLockerCodAvailable());
    }

    public function test_fiszki_sa_niezalezne_od_metod_przedplaconych(): void
    {
        // Sprzedawca chce WYŁĄCZNIE paczkomat pobraniowy — bez kuriera, bez
        // paczkomatu przedpłaconego. Taka konfiguracja musi się dać złożyć.
        $shop = $this->shopWithCod(attributes: [
            'courier_enabled' => false,
            'parcel_locker_enabled' => false,
            'courier_cod_enabled' => false,
        ]);

        $this->assertSame([DeliveryMethod::ParcelLockerCod], $shop->availableDeliveryMethods());
    }

    public function test_sklep_z_samym_pobraniem_przyjmuje_zamowienia(): void
    {
        // Bez tego sklep bez Paynow i bez przelewu wpadłby w tryb katalogu —
        // czyli dokładnie ten sprzedawca, dla którego pobranie powstało.
        $shop = $this->shopWithCod(attributes: [
            'bank_transfer_enabled' => false,
            'pickup_enabled' => false,
            'pay_on_pickup_enabled' => false,
        ]);

        $this->assertSame([], $shop->availablePaymentMethods());
        $this->assertTrue($shop->acceptsOrders());
    }

    public function test_cennik_pobrania_jest_wlasny_a_nie_doplata(): void
    {
        $shop = $this->shopWithCod(attributes: [
            'courier_enabled' => true,
            'courier_cost' => 15.99,
            'parcel_locker_enabled' => true,
            'parcel_locker_cost' => 12.99,
        ]);

        $this->assertSame(15.99, $shop->deliveryCostFor(DeliveryMethod::Courier, 50));
        $this->assertSame(19.99, $shop->deliveryCostFor(DeliveryMethod::CourierCod, 50));
        $this->assertSame(12.99, $shop->deliveryCostFor(DeliveryMethod::ParcelLocker, 50));
        $this->assertSame(16.99, $shop->deliveryCostFor(DeliveryMethod::ParcelLockerCod, 50));
    }

    public function test_prog_darmowej_dostawy_dziala_osobno_dla_pobrania(): void
    {
        $shop = $this->shopWithCod(attributes: [
            'courier_cod_free_from' => 250,
        ]);

        $this->assertSame(19.99, $shop->deliveryCostFor(DeliveryMethod::CourierCod, 249.99));
        $this->assertSame(0.0, $shop->deliveryCostFor(DeliveryMethod::CourierCod, 250));
        // Paczkomat pobraniowy progu nie ma — próg kuriera go nie dotyczy.
        $this->assertSame(16.99, $shop->deliveryCostFor(DeliveryMethod::ParcelLockerCod, 999));
    }

    public function test_sprzedawca_zapisuje_ustawienia_pobrania(): void
    {
        $shop = $this->shopWithCod(attributes: [
            'courier_cod_enabled' => false,
            'parcel_locker_cod_enabled' => false,
        ]);
        $user = User::factory()->consented()->create();
        $shop->forceFill(['owner_id' => $user->id])->save();

        $this->actingAs($user)
            ->from(route('seller.settings.edit'))
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'default_sale_unit' => 'piece',
                'courier_cod_enabled' => '1',
                'courier_cod_cost' => '19,99',
                'courier_cod_free_from' => '250',
                'parcel_locker_cod_enabled' => '1',
                'parcel_locker_cod_cost' => '16,99',
            ])
            ->assertSessionHasNoErrors();

        $shop->refresh();

        $this->assertTrue($shop->courier_cod_enabled);
        $this->assertSame('19.99', $shop->courier_cod_cost);
        $this->assertSame('250.00', $shop->courier_cod_free_from);
        $this->assertTrue($shop->parcel_locker_cod_enabled);
        $this->assertSame('16.99', $shop->parcel_locker_cod_cost);
    }

    public function test_wlaczone_pobranie_wymaga_podania_kosztu(): void
    {
        $shop = $this->shopWithCod();
        $user = User::factory()->consented()->create();
        $shop->forceFill(['owner_id' => $user->id])->save();

        $this->actingAs($user)
            ->from(route('seller.settings.edit'))
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'default_sale_unit' => 'piece',
                'courier_cod_enabled' => '1',
                'courier_cod_cost' => '',
            ])
            ->assertSessionHasErrors('courier_cod_cost');
    }
}
