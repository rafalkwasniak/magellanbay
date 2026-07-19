<?php

namespace Tests\Feature\Administrator;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsola admina — lista sklepów: dostęp tylko dla admina oraz obecność
 * kluczowych danych (nazwa, właściciel, pakiet, cena) na liście.
 */
class ShopListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_shops_list(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['name' => 'Zofia', 'surname' => 'Kruk']);
        Shop::factory()->package('booth')->create(['name' => 'Kwiaciarnia Zosia', 'owner_id' => $owner->id]);

        $this->actingAs($admin)
            ->get(route('administrator.shops.index'))
            ->assertOk()
            ->assertSee('Kwiaciarnia Zosia')
            ->assertSee('Zofia Kruk')
            ->assertSee('Stragan')     // naklejka pakietu
            ->assertSee('750 zł');     // cena roczna brutto
    }

    public function test_seller_cannot_view_shops_list(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)
            ->get(route('administrator.shops.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('administrator.shops.index'))
            ->assertRedirect(route('login'));
    }

    public function test_free_shop_shows_as_free(): void
    {
        $admin = User::factory()->admin()->create();
        Shop::factory()->package('stall')->create(['name' => 'Darmowy Kram']);

        $this->actingAs($admin)
            ->get(route('administrator.shops.index'))
            ->assertOk()
            ->assertSee('Darmowy Kram')
            ->assertSee('za darmo');
    }
}
