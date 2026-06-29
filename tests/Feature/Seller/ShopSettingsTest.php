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
}
