<?php

namespace Tests\Feature\Storefront;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wirtualna strona „O sklepie" na storefroncie: treść z shop.description (nie z
 * tabeli pages). Istnieje zawsze przy niepustym opisie; pusty → 404. Bramka
 * widoczności jak reszta storefrontu. Próg długości rządzi tylko obecnością w
 * menu (Shop::aboutInMenu), nie istnieniem strony.
 */
class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    public function test_about_page_renders_from_shop_description(): void
    {
        $shop = Shop::factory()->active()->create([
            'description' => '<p>Robimy rowery z pasją od 2010 roku.</p>',
        ]);

        $this->get($this->host($shop).$shop->aboutPath())
            ->assertOk()
            ->assertSee(config('pages.about.title'))
            ->assertSee('Robimy rowery z pasją');
    }

    public function test_about_page_404_when_description_empty(): void
    {
        $shop = Shop::factory()->active()->create(['description' => null]);

        $this->get($this->host($shop).$shop->aboutPath())->assertNotFound();
    }

    public function test_about_page_exists_even_for_short_description(): void
    {
        // Nawet 2 zdania → strona istnieje (długość rządzi tylko menu, nie 404).
        $shop = Shop::factory()->active()->create(['description' => '<p>Krótko.</p>']);

        $this->get($this->host($shop).$shop->aboutPath())->assertOk();
        $this->assertFalse($shop->aboutInMenu());
    }

    public function test_about_page_of_draft_shop_is_hidden_from_guests(): void
    {
        $shop = Shop::factory()->create(['description' => '<p>Coś o sklepie.</p>']); // Draft

        $this->get($this->host($shop).$shop->aboutPath())->assertNotFound();
    }

    public function test_owner_can_preview_about_of_draft_shop(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Seller]);
        $shop = Shop::factory()->create([
            'owner_id' => $owner->id,
            'description' => '<p>Sklep w przygotowaniu, ale opis jest.</p>',
        ]);

        $this->actingAs($owner)
            ->get($this->host($shop).$shop->aboutPath())
            ->assertOk()
            ->assertSee('Sklep w przygotowaniu');
    }

    public function test_about_slug_is_not_swallowed_by_page_wildcard(): void
    {
        // /informacje/o-sklepie musi trafić do about(), nie do show() (które
        // rzutuje slug na int → find(0) → 404).
        $shop = Shop::factory()->active()->create(['description' => '<p>Treść.</p>']);

        $this->get($this->host($shop).'/informacje/o-sklepie')
            ->assertOk()
            ->assertSee(config('pages.about.title'));
    }

    public function test_menu_threshold_uses_plain_text_length(): void
    {
        $threshold = (int) config('pages.about.menu_threshold');

        // Długi opis czystego tekstu → w menu.
        $long = Shop::factory()->create(['description' => '<p>'.str_repeat('a', $threshold + 10).'</p>']);
        $this->assertTrue($long->aboutInMenu());

        // Krótka treść „napompowana" tagami/encjami → NIE w menu (liczymy tekst).
        $bloated = Shop::factory()->create([
            'description' => str_repeat('<br>', $threshold).'<p><strong>&nbsp;Krótko&nbsp;</strong></p>',
        ]);
        $this->assertFalse($bloated->aboutInMenu());
        $this->assertTrue($bloated->hasAbout());
    }
}
