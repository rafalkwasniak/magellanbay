<?php

namespace Tests\Feature\Seller;

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
        $shop = Shop::factory()->create(array_merge(['owner_id' => $seller->id], $shopAttributes));

        return [$seller, $shop];
    }

    public function test_seller_can_view_settings_page(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->get(route('seller.settings.edit'))
            ->assertOk()
            ->assertSee('Domyślna stawka VAT');
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
}
