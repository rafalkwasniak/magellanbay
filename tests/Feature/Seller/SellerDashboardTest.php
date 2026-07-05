<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Models\Order;
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
            ->assertSee('0 / 6');
    }

    public function test_panel_renders_mobile_navigation(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('data-mobile-nav-open', false)
            ->assertSee('data-mobile-nav-panel', false);
    }

    public function test_orders_nav_is_a_working_link_not_dead(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Zamówienia')
            ->assertSee(route('seller.orders.index'), false)   // kafel Zamówień prowadzi do listy
            ->assertDontSee('href="#"', false);                // żadnych martwych linków
    }

    public function test_dashboard_shows_package_and_product_usage(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        Product::factory()->for($shop)->count(2)->create();

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Pakiet Kram')
            ->assertSee('2 / 24 produktów');
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
            'contact_email' => 'kontakt@sklep.test',
            'contact_phone' => '48600700800',
        ]);
        Product::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('6 / 6');
    }

    public function test_contact_step_needs_both_email_and_phone(): void
    {
        $seller = User::factory()->consented()->create();
        // E-mail jest (jak po backfillu z właściciela), telefonu brak — krok NIEzaliczony.
        $shop = Shop::factory()->create([
            'owner_id' => $seller->id,
            'contact_email' => 'kontakt@sklep.test',
            'contact_phone' => null,
        ]);

        $this->assertFalse($shop->contactComplete());

        $shop->update(['contact_phone' => '48600700800']);

        $this->assertTrue($shop->fresh()->contactComplete());
    }

    public function test_dashboard_shows_real_orders_and_revenue_for_last_30_days(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);

        // Dwa zamówienia w oknie 30 dni — liczą się do sztuk i przychodu.
        Order::factory()->for($shop)->create(['status' => OrderStatus::Paid, 'total_gross' => 120.50]);
        Order::factory()->for($shop)->create(['status' => OrderStatus::Completed, 'total_gross' => 79.00]);
        // Anulowane — pomijane.
        Order::factory()->for($shop)->create(['status' => OrderStatus::Cancelled, 'total_gross' => 999.00]);
        // Starsze niż 30 dni — poza oknem.
        Order::factory()->for($shop)->create(['status' => OrderStatus::Paid, 'total_gross' => 500.00, 'created_at' => now()->subDays(45)]);
        // Zamówienie innego sklepu — nie może wyciekać.
        Order::factory()->create(['status' => OrderStatus::Paid, 'total_gross' => 42.00]);

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('199,50 zł')   // 120,50 + 79,00
            ->assertSee('W ostatnich 30 dniach');
    }

    public function test_dashboard_without_orders_shows_zero_revenue_and_prompt(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('0,00 zł')
            ->assertSee('Pierwsza sprzedaż przed Tobą');
    }

    public function test_only_hidden_products_do_not_complete_the_product_step(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        Product::factory()->for($shop)->hidden()->count(2)->create();

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertDontSee('6 / 6')
            ->assertSee('wszystkie są ukryte', false);
    }
}
