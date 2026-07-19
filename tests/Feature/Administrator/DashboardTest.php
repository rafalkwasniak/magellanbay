<?php

namespace Tests\Feature\Administrator;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pulpit admina: realne liczby i lista najnowszych sklepów (zamiast atrap „0").
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_recent_shops(): void
    {
        $admin = User::factory()->admin()->create();
        Shop::factory()->create(['name' => 'Kwiaciarnia Zosia']);

        $this->actingAs($admin)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('Sprzedane abonamenty')   // kafelki SaaS (placeholder 0)
            ->assertSee('Przychód — sumarycznie')
            ->assertSee('Najnowsze sklepy')
            ->assertSee('Kwiaciarnia Zosia')
            ->assertDontSee('Nie ma jeszcze żadnych sklepów');
    }

    public function test_dashboard_shows_empty_state_without_shops(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('Nie ma jeszcze żadnych sklepów');
    }
}
