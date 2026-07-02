<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wykaz produktów storefrontu (/produkty): pełny katalog aktywnych produktów,
 * paginacja, bramka szkicu i link „Zobacz wszystkie" z głównej.
 */
class ProductListingTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    public function test_listing_shows_only_active_products(): void
    {
        $shop = Shop::factory()->active()->create();
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Produkt Widoczny']);
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => false, 'name' => 'Produkt Ukryty']);

        $this->get($this->host($shop).'/produkty')
            ->assertOk()
            ->assertSee('Produkt Widoczny')
            ->assertDontSee('Produkt Ukryty');
    }

    public function test_listing_paginates(): void
    {
        $shop = Shop::factory()->active()->create();
        Product::factory()->count(15)->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->get($this->host($shop).'/produkty')
            ->assertOk()
            ->assertSee('Wyświetlono')
            ->assertSee('>15<', false); // łączna liczba w podsumowaniu

        $this->get($this->host($shop).'/produkty?page=2')->assertOk();
    }

    public function test_products_per_page_comes_from_template(): void
    {
        // velvet_cloud → 9 na stronę: 12 produktów = 2 strony (pager widoczny).
        $airy = Shop::factory()->active()->create(['template' => 'velvet_cloud']);
        Product::factory()->count(12)->create(['shop_id' => $airy->id, 'is_active' => true]);
        $this->get($this->host($airy).'/produkty')->assertOk()->assertSee('Wyświetlono');

        // green_nook → 12 na stronę: 12 produktów = 1 strona (bez pagera).
        $dense = Shop::factory()->active()->create(['template' => 'green_nook']);
        Product::factory()->count(12)->create(['shop_id' => $dense->id, 'is_active' => true]);
        $this->get($this->host($dense).'/produkty')->assertOk()->assertDontSee('Wyświetlono');
    }

    public function test_draft_shop_listing_shows_coming_soon_to_guests(): void
    {
        $shop = Shop::factory()->create(['name' => 'Sklep W Budowie']); // szkic

        $this->get($this->host($shop).'/produkty')
            ->assertOk()
            ->assertSee('już wkrótce');
    }

    public function test_home_links_to_catalog_when_more_products_than_shown(): void
    {
        $shop = Shop::factory()->active()->create();
        Product::factory()->count(8)->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->get($this->host($shop).'/')
            ->assertOk()
            ->assertSee('Zobacz wszystkie produkty');
    }
}
