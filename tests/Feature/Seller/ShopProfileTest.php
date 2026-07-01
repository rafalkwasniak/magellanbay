<?php

namespace Tests\Feature\Seller;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopProfileTest extends TestCase
{
    use RefreshDatabase;

    private function sellerWithShop(array $shopAttributes = []): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(array_merge(['owner_id' => $seller->id], $shopAttributes));

        return [$seller, $shop];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nowa nazwa sklepu',
            'description' => 'Rękodzieło z pasją.',
            'company_name' => 'Pracownia Test sp. z o.o.',
            'nip' => '1234563218', // poprawna suma kontrolna
            'country' => 'Polska',
            'province' => 'mazowieckie',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'street' => 'Kwiatowa',
            'building_number' => '12A',
            'apartment_number' => '4',
        ], $overrides);
    }

    public function test_seller_can_view_shop_profile(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->get(route('seller.shop.edit'))
            ->assertOk()
            ->assertSee($shop->name);
    }

    public function test_seller_can_update_shop_profile(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.shop.update'), $this->validPayload())
            ->assertRedirect(route('seller.shop.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'name' => 'Nowa nazwa sklepu',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'building_number' => '12A',
            'company_name' => 'Pracownia Test sp. z o.o.',
            'nip' => '1234563218',
        ]);
    }

    public function test_company_data_is_optional(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.shop.update'), $this->validPayload(['company_name' => '', 'nip' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($shop->fresh()->nip);
    }

    public function test_invalid_nip_is_rejected(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.shop.update'), $this->validPayload(['nip' => '1234567890']))
            ->assertSessionHasErrors('nip');
    }

    public function test_nip_is_normalized_to_digits(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.shop.update'), $this->validPayload(['nip' => '123-456-32-18']));

        $this->assertSame('1234563218', $shop->fresh()->nip);
    }

    public function test_name_change_does_not_change_slug(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $originalSlug = $shop->slug;

        $this->actingAs($seller)
            ->post(route('seller.shop.update'), $this->validPayload(['name' => 'Zupełnie inna nazwa']));

        $this->assertSame($originalSlug, $shop->fresh()->slug);
    }

    public function test_address_fields_are_required(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.shop.update'), $this->validPayload([
                'city' => '',
                'street' => '',
                'postal_code' => '',
                'province' => '',
            ]))
            ->assertSessionHasErrors(['city', 'street', 'postal_code', 'province']);
    }

    public function test_postal_code_is_normalized_to_nn_nnn(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.shop.update'), $this->validPayload(['postal_code' => '00123']));

        $this->assertSame('00-123', $shop->fresh()->postal_code);
    }

    public function test_province_must_be_from_the_list(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.shop.update'), $this->validPayload(['province' => 'nieistniejące']))
            ->assertSessionHasErrors('province');
    }

    public function test_seller_can_save_bank_account_details(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.shop.update'), $this->validPayload([
                'bank_account_number' => 'PL 12 3456 7890 1234 5678 9012 3456',
                'bank_account_holder' => 'Kwiaciarnia Ania',
                'bank_name' => 'mBank',
            ]))
            ->assertRedirect(route('seller.shop.edit'))
            ->assertSessionHas('success');

        $fresh = $shop->fresh();
        // Prefiks PL i spacje usunięte — zostają same cyfry NRB.
        $this->assertSame('12345678901234567890123456', $fresh->bank_account_number);
        $this->assertSame('Kwiaciarnia Ania', $fresh->bank_account_holder);
        $this->assertSame('mBank', $fresh->bank_name);
    }

    public function test_bank_account_number_with_wrong_length_is_rejected(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.shop.update'), $this->validPayload([
                'bank_account_number' => '12 3456 7890',
            ]))
            ->assertSessionHasErrors('bank_account_number');

        $this->assertNull($shop->fresh()->bank_account_number);
    }

    public function test_bank_account_details_are_optional(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.shop.update'), $this->validPayload(['bank_account_number' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($shop->fresh()->bank_account_number);
    }

    public function test_bank_account_holder_falls_back_to_company_name(): void
    {
        [, $shop] = $this->sellerWithShop([
            'company_name' => 'Firma Ania',
            'bank_account_holder' => null,
        ]);

        $this->assertSame('Firma Ania', $shop->bankAccountHolderName());
    }
}
