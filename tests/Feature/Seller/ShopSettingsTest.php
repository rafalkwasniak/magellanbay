<?php

namespace Tests\Feature\Seller;

use App\Enums\SaleUnit;
use App\Enums\VatRate;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function sellerWithShop(array $shopAttributes = []): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->withCourierShipping()->create(array_merge(['owner_id' => $seller->id], $shopAttributes));

        return [$seller, $shop];
    }

    public function test_seller_can_view_settings_page(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->get(route('seller.settings.edit'))
            ->assertOk()
            ->assertSee('Domyślna stawka VAT')
            ->assertSee('Dostawa kurierem');
    }

    public function test_seller_can_change_default_vat_rate(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_vat_rate' => '23']);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '8'])
            ->assertRedirect(route('seller.settings.edit'))
            ->assertSessionHas('success');

        $this->assertSame(VatRate::R8, $shop->fresh()->default_vat_rate);
    }

    public function test_seller_can_change_default_sale_unit(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_sale_unit' => 'piece']);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23', 'default_sale_unit' => 'weight'])
            ->assertRedirect(route('seller.settings.edit'))
            ->assertSessionHas('success');

        $this->assertSame(SaleUnit::Weight, $shop->fresh()->default_sale_unit);
    }

    public function test_invalid_sale_unit_is_rejected(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_sale_unit' => 'piece']);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23', 'default_sale_unit' => 'ton'])
            ->assertSessionHasErrors('default_sale_unit');

        $this->assertSame(SaleUnit::Piece, $shop->fresh()->default_sale_unit);
    }

    public function test_invalid_vat_rate_is_rejected(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_vat_rate' => '23']);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '17'])
            ->assertSessionHasErrors('default_vat_rate');

        $this->assertSame(VatRate::R23, $shop->fresh()->default_vat_rate);
    }

    public function test_seller_can_disable_bank_transfer_method(): void
    {
        [$seller, $shop] = $this->sellerWithShop([
            'bank_account_number' => '12345678901234567890123456',
            'bank_transfer_enabled' => true,
        ]);

        // Checkbox odznaczony = brak klucza w POST; fiszka schodzi na false.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23'])
            ->assertRedirect(route('seller.settings.edit'))
            ->assertSessionHas('success');

        $fresh = $shop->fresh();
        $this->assertFalse($fresh->bank_transfer_enabled);
        $this->assertFalse($fresh->bankTransferAvailable());
    }

    public function test_seller_can_enable_bank_transfer_method(): void
    {
        [$seller, $shop] = $this->sellerWithShop([
            'bank_account_number' => '12345678901234567890123456',
            'bank_transfer_enabled' => false,
        ]);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'bank_transfer_enabled' => '1',
            ])
            ->assertSessionHas('success');

        $fresh = $shop->fresh();
        $this->assertTrue($fresh->bank_transfer_enabled);
        $this->assertTrue($fresh->bankTransferAvailable());
    }

    public function test_bank_transfer_not_available_without_account_number(): void
    {
        [, $shop] = $this->sellerWithShop([
            'bank_account_number' => null,
            'bank_transfer_enabled' => true,
        ]);

        // Fiszka włączona, ale bez numeru konta metoda nie jest realnie dostępna.
        $this->assertFalse($shop->bankTransferAvailable());
    }

    /**
     * @return array<string, string>
     */
    private function completeAddress(): array
    {
        return [
            'street' => 'Kwiatowa',
            'building_number' => '5',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'province' => 'mazowieckie',
        ];
    }

    public function test_pickup_available_with_complete_address(): void
    {
        [, $shop] = $this->sellerWithShop(array_merge($this->completeAddress(), ['pickup_enabled' => true]));

        $this->assertTrue($shop->pickupAvailable());
    }

    public function test_pickup_not_available_without_address(): void
    {
        // Fiszka włączona, ale bez adresu odbiór nie jest realnie dostępny.
        [, $shop] = $this->sellerWithShop(['pickup_enabled' => true, 'street' => null]);

        $this->assertFalse($shop->pickupAvailable());
    }

    public function test_seller_can_disable_pickup(): void
    {
        [$seller, $shop] = $this->sellerWithShop(array_merge($this->completeAddress(), ['pickup_enabled' => true]));

        // Checkbox odznaczony = brak klucza w POST; fiszka schodzi na false.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23'])
            ->assertSessionHas('success');

        $this->assertFalse($shop->fresh()->pickup_enabled);
    }

    public function test_seller_can_enable_pickup(): void
    {
        [$seller, $shop] = $this->sellerWithShop(array_merge($this->completeAddress(), ['pickup_enabled' => false]));

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'pickup_enabled' => '1',
            ])
            ->assertSessionHas('success');

        $fresh = $shop->fresh();
        $this->assertTrue($fresh->pickup_enabled);
        $this->assertTrue($fresh->pickupAvailable());
    }

    public function test_pay_on_pickup_available_only_with_pickup(): void
    {
        [, $withPickup] = $this->sellerWithShop(array_merge($this->completeAddress(), [
            'pickup_enabled' => true,
            'pay_on_pickup_enabled' => true,
        ]));
        $this->assertTrue($withPickup->payOnPickupAvailable());

        // Bez odbioru (brak adresu) płatność przy odbiorze jest niedostępna.
        [, $noPickup] = $this->sellerWithShop([
            'street' => null,
            'pickup_enabled' => true,
            'pay_on_pickup_enabled' => true,
        ]);
        $this->assertFalse($noPickup->payOnPickupAvailable());
    }

    public function test_seller_can_toggle_pay_on_pickup(): void
    {
        [$seller, $shop] = $this->sellerWithShop(array_merge($this->completeAddress(), [
            'pickup_enabled' => true,
            'pay_on_pickup_enabled' => false,
        ]));

        // Włączenie.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'pickup_enabled' => '1',
                'pay_on_pickup_enabled' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertTrue($shop->fresh()->payOnPickupAvailable());

        // Wyłączenie (checkbox odznaczony = brak klucza w POST).
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'pickup_enabled' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertFalse($shop->fresh()->pay_on_pickup_enabled);
    }

    public function test_seller_can_enable_courier_with_cost_and_threshold(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['courier_enabled' => false]);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'courier_enabled' => '1',
                'courier_cost' => '15,99',          // przecinek → kropka
                'courier_free_from' => '200',
            ])
            ->assertSessionHas('success');

        $fresh = $shop->fresh();
        $this->assertTrue($fresh->courier_enabled);
        $this->assertTrue($fresh->courierAvailable());
        $this->assertSame('15.99', $fresh->courier_cost);
        $this->assertSame('200.00', $fresh->courier_free_from);
    }

    public function test_courier_cost_is_required_when_enabled(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['courier_enabled' => false]);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'courier_enabled' => '1',
                'courier_cost' => '',
            ])
            ->assertSessionHasErrors('courier_cost');

        $this->assertFalse($shop->fresh()->courier_enabled);
    }

    public function test_seller_can_offer_free_courier_with_zero_cost(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['courier_enabled' => false]);

        // Koszt 0 jest dozwolony (kurier gratis), próg pusty = brak darmowej dostawy.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'courier_enabled' => '1',
                'courier_cost' => '0',
            ])
            ->assertSessionHas('success');

        $fresh = $shop->fresh();
        $this->assertTrue($fresh->courier_enabled);
        $this->assertSame('0.00', $fresh->courier_cost);
        $this->assertNull($fresh->courier_free_from);
    }

    public function test_seller_can_disable_courier(): void
    {
        [$seller, $shop] = $this->sellerWithShop([
            'courier_enabled' => true,
            'courier_cost' => 15.99,
        ]);

        // Checkbox odznaczony = brak klucza w POST; fiszka schodzi na false.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23'])
            ->assertSessionHas('success');

        $this->assertFalse($shop->fresh()->courier_enabled);
    }

    public function test_courier_cost_reflects_free_shipping_threshold(): void
    {
        [, $shop] = $this->sellerWithShop([
            'courier_enabled' => true,
            'courier_cost' => 15.99,
            'courier_free_from' => 200,
        ]);

        // Poniżej progu — pełny koszt; na/powyżej progu — gratis.
        $this->assertSame(15.99, $shop->courierCostFor(199.99));
        $this->assertSame(0.0, $shop->courierCostFor(200.0));
        $this->assertSame(0.0, $shop->courierCostFor(250.0));
    }

    public function test_courier_cost_ignores_threshold_when_not_set(): void
    {
        [, $shop] = $this->sellerWithShop([
            'courier_enabled' => true,
            'courier_cost' => 15.99,
            'courier_free_from' => null,
        ]);

        // Brak progu = darmowej dostawy nie ma, choćby koszyk był duży.
        $this->assertSame(15.99, $shop->courierCostFor(10_000.0));
    }

    public function test_seller_can_enable_parcel_locker_with_cost_and_threshold(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['parcel_locker_enabled' => false]);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'parcel_locker_enabled' => '1',
                'parcel_locker_cost' => '12,99',          // przecinek → kropka
                'parcel_locker_free_from' => '150',
            ])
            ->assertSessionHas('success');

        $fresh = $shop->fresh();
        $this->assertTrue($fresh->parcel_locker_enabled);
        $this->assertTrue($fresh->parcelLockerAvailable());
        $this->assertSame('12.99', $fresh->parcel_locker_cost);
        $this->assertSame('150.00', $fresh->parcel_locker_free_from);
    }

    public function test_parcel_locker_cost_is_required_when_enabled(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['parcel_locker_enabled' => false]);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'parcel_locker_enabled' => '1',
                'parcel_locker_cost' => '',
            ])
            ->assertSessionHasErrors('parcel_locker_cost');

        $this->assertFalse($shop->fresh()->parcel_locker_enabled);
    }

    public function test_seller_can_offer_free_parcel_locker_with_zero_cost(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['parcel_locker_enabled' => false]);

        // Koszt 0 jest dozwolony (paczkomat gratis), próg pusty = brak darmowej dostawy.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'parcel_locker_enabled' => '1',
                'parcel_locker_cost' => '0',
            ])
            ->assertSessionHas('success');

        $fresh = $shop->fresh();
        $this->assertTrue($fresh->parcel_locker_enabled);
        $this->assertSame('0.00', $fresh->parcel_locker_cost);
        $this->assertNull($fresh->parcel_locker_free_from);
    }

    public function test_seller_can_disable_parcel_locker(): void
    {
        [$seller, $shop] = $this->sellerWithShop([
            'parcel_locker_enabled' => true,
            'parcel_locker_cost' => 12.99,
        ]);

        // Checkbox odznaczony = brak klucza w POST; fiszka schodzi na false.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23'])
            ->assertSessionHas('success');

        $this->assertFalse($shop->fresh()->parcel_locker_enabled);
    }

    public function test_parcel_locker_cost_reflects_free_shipping_threshold(): void
    {
        [, $shop] = $this->sellerWithShop([
            'parcel_locker_enabled' => true,
            'parcel_locker_cost' => 12.99,
            'parcel_locker_free_from' => 150,
        ]);

        // Poniżej progu — pełny koszt; na/powyżej progu — gratis.
        $this->assertSame(12.99, $shop->parcelLockerCostFor(149.99));
        $this->assertSame(0.0, $shop->parcelLockerCostFor(150.0));
        $this->assertSame(0.0, $shop->parcelLockerCostFor(250.0));
    }

    public function test_parcel_locker_cost_ignores_threshold_when_not_set(): void
    {
        [, $shop] = $this->sellerWithShop([
            'parcel_locker_enabled' => true,
            'parcel_locker_cost' => 12.99,
            'parcel_locker_free_from' => null,
        ]);

        // Brak progu = darmowej dostawy nie ma, choćby koszyk był duży.
        $this->assertSame(12.99, $shop->parcelLockerCostFor(10_000.0));
    }

    public function test_courier_and_parcel_locker_are_configured_independently(): void
    {
        // Sedno bliźniaczości: to DWIE osobne metody, nie jeden przełącznik
        // „wysyłka". Sprzedawca może dać gratis w paczkomacie od 150 zł, a
        // kuriera liczyć zawsze — progi i koszty nie mogą się o siebie ocierać.
        [$seller, $shop] = $this->sellerWithShop([
            'courier_enabled' => false,
            'parcel_locker_enabled' => false,
        ]);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'courier_enabled' => '1',
                'courier_cost' => '19,99',
                'parcel_locker_enabled' => '1',
                'parcel_locker_cost' => '12,99',
                'parcel_locker_free_from' => '150',
            ])
            ->assertSessionHas('success');

        $fresh = $shop->fresh();
        $this->assertTrue($fresh->courierAvailable());
        $this->assertTrue($fresh->parcelLockerAvailable());

        // Duży koszyk: paczkomat gratis (próg 150), kurier dalej płatny (brak progu).
        $this->assertSame(0.0, $fresh->parcelLockerCostFor(200.0));
        $this->assertSame(19.99, $fresh->courierCostFor(200.0));

        // Wyłączenie paczkomatu nie rusza kuriera.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'courier_enabled' => '1',
                'courier_cost' => '19,99',
            ])
            ->assertSessionHas('success');

        $fresh = $shop->fresh();
        $this->assertFalse($fresh->parcelLockerAvailable());
        $this->assertTrue($fresh->courierAvailable());
    }

    public function test_delivery_courier_blocks_hidden_without_entitlement(): void
    {
        // Kram (bez `courier_shipping`) w Ustawieniach widzi tylko odbiór osobisty —
        // bloki kuriera i paczkomatu są schowane (funkcja płatna, Stragan+).
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]); // domyślny Kram

        $this->actingAs($seller)
            ->get(route('seller.settings.edit'))
            ->assertOk()
            ->assertSee('Odbiór osobisty')
            ->assertDontSee('Dostawa kurierem')
            ->assertDontSee('Paczkomat InPost');
    }
}
