<?php

namespace Tests\Feature\Storefront;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Storefront na subdomenie {shop}.{central_domain}: rozwiązywanie najemcy,
 * bramka widoczności (szkic → „już wkrótce", podgląd dla właściciela/admina)
 * i wstrzyknięcie palety motywu jako zmiennych CSS.
 */
class HomeTest extends TestCase
{
    use RefreshDatabase;

    private function url(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain').'/';
    }

    public function test_active_shop_renders_themed_storefront(): void
    {
        $shop = Shop::factory()->active()->create(['name' => 'Kwiaciarnia Bukiet']);

        $this->get($this->url($shop))
            ->assertOk()
            ->assertSee('Kwiaciarnia Bukiet')
            ->assertSee('--brand', false)          // paleta wstrzyknięta w :root
            ->assertDontSee('Zapraszamy wkrótce');
    }

    public function test_unknown_subdomain_returns_404(): void
    {
        $this->get('http://nieistniejacy-sklep.'.config('tenancy.central_domain').'/')
            ->assertNotFound();
    }

    public function test_draft_shop_shows_coming_soon_to_guests(): void
    {
        // Fabryka domyślnie tworzy szkic (brak aktywnych produktów).
        $shop = Shop::factory()->create(['name' => 'Sklep W Budowie']);

        $this->get($this->url($shop))
            ->assertOk()
            ->assertSee('Zapraszamy wkrótce')
            ->assertSee('Sklep W Budowie');
    }

    public function test_owner_can_preview_draft_shop(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Seller]);
        $shop = Shop::factory()->create(['owner_id' => $owner->id, 'name' => 'Podgląd Właściciela']);

        $this->actingAs($owner)
            ->get($this->url($shop))
            ->assertOk()
            ->assertSee('Podgląd Właściciela')
            ->assertDontSee('Zapraszamy wkrótce');
    }

    public function test_admin_can_preview_draft_shop(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $shop = Shop::factory()->create(['name' => 'Sklep Innego']);

        $this->actingAs($admin)
            ->get($this->url($shop))
            ->assertOk()
            ->assertDontSee('Zapraszamy wkrótce');
    }

    public function test_other_seller_does_not_preview_foreign_draft(): void
    {
        $intruder = User::factory()->create(['role' => UserRole::Seller]);
        $shop = Shop::factory()->create(['name' => 'Cudzy Sklep']);

        $this->actingAs($intruder)
            ->get($this->url($shop))
            ->assertOk()
            ->assertSee('Zapraszamy wkrótce');
    }
}
