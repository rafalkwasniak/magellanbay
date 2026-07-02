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
            ->assertSee('Strona 1 z 2');

        $this->get($this->host($shop).'/produkty?page=2')->assertOk();
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
