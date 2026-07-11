<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wykaz produktów storefrontu (/produkty): pełny katalog aktywnych produktów,
 * paginacja, bramka szkicu i link do katalogu z głównej (przez menu).
 */
class ProductListingTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    /**
     * Tworzy tag w sklepie i podpina do wskazanych produktów.
     *
     * @param  array<int, Product>  $products
     */
    private function tagProducts(Shop $shop, string $name, array $products): void
    {
        $tag = $shop->tags()->create(['name' => $name, 'slug' => Str::slug($name)]);

        foreach ($products as $product) {
            $product->tags()->attach($tag->id);
        }
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

    public function test_listing_density_scales_columns_with_catalog_size(): void
    {
        // Mały katalog (≤27 aktywnych) → 3 kolumny (duże kafle).
        $small = Shop::factory()->active()->create();
        Product::factory()->count(10)->create(['shop_id' => $small->id, 'is_active' => true]);
        $this->get($this->host($small).'/produkty')
            ->assertOk()
            ->assertSee('lg:grid-cols-3', false)
            ->assertDontSee('lg:grid-cols-4', false);

        // Duży katalog (>45 aktywnych) → 4 kolumny (gęściej).
        $large = Shop::factory()->active()->create();
        Product::factory()->count(50)->create(['shop_id' => $large->id, 'is_active' => true]);
        $this->get($this->host($large).'/produkty')
            ->assertOk()
            ->assertSee('lg:grid-cols-4', false);
    }

    public function test_listing_sorts_by_price_ascending(): void
    {
        $shop = Shop::factory()->active()->create();
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Drogi', 'price_gross' => 300]);
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Tani', 'price_gross' => 10]);

        $content = $this->get($this->host($shop).'/produkty?sortowanie=cena-rosnaco')
            ->assertOk()->getContent();

        $this->assertTrue(
            strpos($content, 'Tani') < strpos($content, 'Drogi'),
            'Tańszy produkt powinien być wyżej przy sortowaniu rosnącym.'
        );
    }

    public function test_listing_sorts_by_name(): void
    {
        $shop = Shop::factory()->active()->create();
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Zebra']);
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Antylopa']);

        $content = $this->get($this->host($shop).'/produkty?sortowanie=nazwa')
            ->assertOk()->getContent();

        $this->assertTrue(strpos($content, 'Antylopa') < strpos($content, 'Zebra'));
    }

    public function test_unknown_sort_falls_back_to_newest(): void
    {
        $shop = Shop::factory()->active()->create();
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Starszy', 'created_at' => now()->subDay()]);
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Nowszy', 'created_at' => now()]);

        // Nieznana wartość nie wywraca strony — spada na „najnowsze" (nowszy wyżej).
        $content = $this->get($this->host($shop).'/produkty?sortowanie=wynalazek')
            ->assertOk()->getContent();

        $this->assertTrue(strpos($content, 'Nowszy') < strpos($content, 'Starszy'));
    }

    public function test_sort_links_reset_page_and_survive_pagination(): void
    {
        $shop = Shop::factory()->active()->create();
        Product::factory()->count(20)->create(['shop_id' => $shop->id, 'is_active' => true]);

        // Link sortowania nie niesie `page` (zmiana sortu → strona 1)...
        $this->get($this->host($shop).'/produkty?page=2')
            ->assertOk()
            ->assertSee('/produkty?sortowanie=cena-rosnaco', false)
            ->assertDontSee('sortowanie=cena-rosnaco&amp;page', false);

        // ...a paginacja zachowuje aktywny sort (query string w linkach pagera).
        $this->get($this->host($shop).'/produkty?sortowanie=cena-rosnaco')
            ->assertOk()
            ->assertSee('sortowanie=cena-rosnaco', false);
    }

    public function test_filter_by_single_tag_shows_only_matching(): void
    {
        $shop = Shop::factory()->active()->create();
        $srebrny = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Pierścionek']);
        $zloty = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Bransoletka']);
        $this->tagProducts($shop, 'srebro', [$srebrny]);

        $this->get($this->host($shop).'/produkty?tagi=srebro')
            ->assertOk()
            ->assertSee('Pierścionek')
            ->assertDontSee('Bransoletka');
    }

    public function test_multiple_tags_filter_with_and(): void
    {
        $shop = Shop::factory()->active()->create();
        $both = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Komplet']);
        $onlyOne = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Sam Srebrny']);
        $this->tagProducts($shop, 'srebro', [$both, $onlyOne]);
        $this->tagProducts($shop, 'prezent', [$both]);

        // AND: tylko produkt mający OBA tagi.
        $this->get($this->host($shop).'/produkty?tagi=srebro,prezent')
            ->assertOk()
            ->assertSee('Komplet')
            ->assertDontSee('Sam Srebrny');
    }

    public function test_tag_cloud_orders_by_popularity(): void
    {
        $shop = Shop::factory()->active()->create();
        $products = Product::factory()->count(3)->create(['shop_id' => $shop->id, 'is_active' => true]);
        $this->tagProducts($shop, 'Popularny', $products->all());
        $this->tagProducts($shop, 'Rzadki', [$products->first()]);

        $content = $this->get($this->host($shop).'/produkty')->assertOk()->getContent();

        $this->assertTrue(
            strpos($content, 'Popularny') < strpos($content, 'Rzadki'),
            'Tag z większą liczbą produktów powinien być wcześniej w chmurze.'
        );
    }

    public function test_tag_with_no_active_products_is_hidden_from_cloud(): void
    {
        $shop = Shop::factory()->active()->create();
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Aktywny']);
        $hidden = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => false, 'name' => 'Ukryty']);
        $this->tagProducts($shop, 'martwytag', [$hidden]);

        $this->get($this->host($shop).'/produkty')
            ->assertOk()
            ->assertDontSee('martwytag');
    }

    public function test_unknown_tag_is_ignored(): void
    {
        $shop = Shop::factory()->active()->create();
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Cokolwiek']);

        // Nieistniejący tag nie filtruje ani nie włącza „Wyczyść".
        $this->get($this->host($shop).'/produkty?tagi=nieistnieje')
            ->assertOk()
            ->assertSee('Cokolwiek')
            ->assertDontSee('Wyczyść');
    }

    public function test_tag_filter_resets_page_and_survives_pagination(): void
    {
        $shop = Shop::factory()->active()->create(['template' => 'velvet_cloud']); // 9/stronę
        $products = Product::factory()->count(20)->create(['shop_id' => $shop->id, 'is_active' => true]);
        $this->tagProducts($shop, 'wspolny', $products->all());

        // Pigułka tagu nie niesie `page` (klik filtra → strona 1)...
        $this->get($this->host($shop).'/produkty?page=2')
            ->assertOk()
            ->assertSee('/produkty?tagi=wspolny', false)
            ->assertDontSee('tagi=wspolny&amp;page', false);

        // ...a paginacja przefiltrowanej listy zachowuje tag.
        $this->get($this->host($shop).'/produkty?tagi=wspolny')
            ->assertOk()
            ->assertSee('tagi=wspolny', false)
            ->assertSee('page=2', false);
    }

    public function test_tag_cloud_narrows_to_cooccurring_tags(): void
    {
        $shop = Shop::factory()->active()->create();
        $alpha = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Alpha']);
        $beta = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Beta']);
        $gamma = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Gamma']);

        $this->tagProducts($shop, 'trek', [$alpha, $beta]);
        $this->tagProducts($shop, 'slx', [$alpha]);   // współwystępuje z trek
        $this->tagProducts($shop, 'sly', [$beta]);    // współwystępuje z trek
        $this->tagProducts($shop, 'rower', [$gamma]);  // NIE współwystępuje z trek

        // Bez filtra — wszystkie tagi w chmurze.
        $this->get($this->host($shop).'/produkty')
            ->assertOk()->assertSee('rower');

        // Po kliknięciu „trek" — tylko tagi realnie z nim występujące; „rower" znika.
        $this->get($this->host($shop).'/produkty?tagi=trek')
            ->assertOk()
            ->assertSee('slx')
            ->assertSee('sly')
            ->assertDontSee('rower');
    }

    public function test_cards_carry_return_url_with_current_filters(): void
    {
        $shop = Shop::factory()->active()->create();
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true]);

        // Kafel linkuje do produktu z ?powrot= zawierającym bieżący URL listy.
        $this->get($this->host($shop).'/produkty?sortowanie=nazwa')
            ->assertOk()
            ->assertSee('powrot='.urlencode('/produkty?sortowanie=nazwa'), false);
    }

    public function test_draft_shop_listing_shows_coming_soon_to_guests(): void
    {
        $shop = Shop::factory()->create(['name' => 'Sklep W Budowie']); // szkic

        $this->get($this->host($shop).'/produkty')
            ->assertOk()
            ->assertSee('już wkrótce');
    }

    public function test_home_links_to_catalog_via_menu(): void
    {
        $shop = Shop::factory()->active()->create();
        Product::factory()->count(8)->create(['shop_id' => $shop->id, 'is_active' => true]);

        // Dostęp do pełnego katalogu jest przez menu „Produkty" — CTA „Zobacz
        // wszystkie produkty" usunięte ze strony głównej (wyglądał jak zwykły
        // przycisk, a menu i tak niesie „Produkty").
        $this->get($this->host($shop).'/')
            ->assertOk()
            ->assertSee('href="/produkty"', false);
    }

    public function test_listing_renders_breadcrumbs(): void
    {
        $shop = Shop::factory()->active()->create(['name' => 'Złoty Kram']);

        $this->get($this->host($shop).'/produkty')
            ->assertOk()
            ->assertSee('aria-label="Ścieżka nawigacji"', false)
            ->assertSee('BreadcrumbList', false)
            ->assertSee('"name":"Złoty Kram"', false)
            ->assertSee('"name":"Produkty"', false);
    }
}
