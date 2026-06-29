<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_shop_shows_no_completed_setup_steps(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('0 / 5');
    }

    public function test_completed_shop_shows_full_setup_progress(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create([
            'owner_id' => $seller->id,
            'street' => 'Kwiatowa',
            'building_number' => '1',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'province' => 'mazowieckie',
            'description' => 'Rękodzieło z pasją.',
            'logo_path' => 'shops/1/logo.png',
            'nip' => '1234563218',
        ]);
        Product::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('5 / 5');
    }
}
