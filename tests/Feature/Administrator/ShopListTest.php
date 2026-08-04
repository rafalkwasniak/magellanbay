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

    public function test_list_links_to_each_storefront_in_a_new_tab(): void
    {
        // Podgląd sklepu prosto z listy — adres bierzemy z `host()`, więc sklep
        // z własną domeną prowadzi tam, a nie na subdomenę centrali.
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create(['slug' => 'lemoniady']);

        $this->actingAs($admin)
            ->get(route('administrator.shops.index'))
            ->assertOk()
            ->assertSee('Zobacz')
            ->assertSee('href="https://'.$shop->host().'"', false)
            ->assertSee('target="_blank"', false);
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
            ->assertSee('gratis');
    }

    public function test_comped_shop_shows_free_price_and_no_subscription_date(): void
    {
        // Dostęp gratisowy: cennikowa kwota pakietu nie obowiązuje, a termin
        // nie istnieje — wiersz ma wyglądać jak Kram, bez osobnej plakietki.
        $admin = User::factory()->admin()->create();
        Shop::factory()->package('pavilion')->create([
            'name' => 'Sklep Fundacji',
            'comped' => true,
            'subscription_ends_at' => now()->addYear(),
        ]);

        $this->actingAs($admin)
            ->get(route('administrator.shops.index'))
            ->assertOk()
            ->assertSee('Pawilon')
            ->assertSee('gratis')
            ->assertDontSee('1 500 zł')
            ->assertDontSee('bezterminowo')
            ->assertDontSee(now()->addYear()->format('d.m.Y'));
    }

    public function test_shops_can_be_filtered_by_search_and_package(): void
    {
        $admin = User::factory()->admin()->create();
        Shop::factory()->package('booth')->create(['name' => 'Kwiaciarnia Zosia']);
        Shop::factory()->package('pavilion')->create(['name' => 'Rowery Krzysztof']);

        // Szukajka po nazwie.
        $this->actingAs($admin)
            ->get(route('administrator.shops.index', ['q' => 'Zosia']))
            ->assertOk()
            ->assertSee('Kwiaciarnia Zosia')
            ->assertDontSee('Rowery Krzysztof');

        // Filtr po pakiecie.
        $this->actingAs($admin)
            ->get(route('administrator.shops.index', ['package' => 'pavilion']))
            ->assertOk()
            ->assertSee('Rowery Krzysztof')
            ->assertDontSee('Kwiaciarnia Zosia');
    }

    public function test_filter_with_no_matches_shows_message(): void
    {
        $admin = User::factory()->admin()->create();
        Shop::factory()->create(['name' => 'Kwiaciarnia Zosia']);

        $this->actingAs($admin)
            ->get(route('administrator.shops.index', ['q' => 'nieistniejące']))
            ->assertOk()
            ->assertSee('Brak sklepów dla tych filtrów')
            ->assertDontSee('Kwiaciarnia Zosia');
    }
}
