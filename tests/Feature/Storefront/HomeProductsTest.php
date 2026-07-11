<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Produkty na stronie głównej storefrontu: wyróżnione (`show_on_homepage`) do
 * sufitu 6, fallback do najnowszych aktywnych, i adaptacja układu do liczby
 * (1/2/3 mają dedykowane aranżacje, 4–6 wspólną siatkę).
 */
class HomeProductsTest extends TestCase
{
    use RefreshDatabase;

    private function url(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain').'/';
    }

    private function promote(Shop $shop, int $count): Collection
    {
        return Product::factory()->count($count)->create([
            'shop_id' => $shop->id,
            'is_active' => true,
            'show_on_homepage' => true,
        ]);
    }

    public function test_single_product_renders_hero(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->promote($shop, 1)->first();

        $this->get($this->url($shop))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee($product->storefrontPath(), false); // nazwa w apli jest linkiem do produktu (ścieżka do szczegółów)
    }

    public function test_two_and_three_products_render_all(): void
    {
        foreach ([2, 3] as $count) {
            $shop = Shop::factory()->active()->create();
            $products = $this->promote($shop, $count);

            $response = $this->get($this->url($shop))->assertOk();
            foreach ($products as $product) {
                $response->assertSee($product->name);
            }
        }
    }

    public function test_grid_renders_for_more_products(): void
    {
        $shop = Shop::factory()->active()->create();
        $products = $this->promote($shop, 5);

        $response = $this->get($this->url($shop))->assertOk();
        foreach ($products as $product) {
            $response->assertSee($product->name);
        }
    }

    public function test_price_is_formatted_polish(): void
    {
        // Cena widnieje na kaflach widoku wielo-produktowego; box z 1 produktem
        // celowo BEZ ceny (główna nie sprzedaje). Format sprawdzamy na kaflu.
        $shop = Shop::factory()->active()->create();
        $this->promote($shop, 2)->first()->update(['price_gross' => 49.99]);

        $this->get($this->url($shop))->assertSee('49,99 zł');
    }

    public function test_home_shows_at_most_the_limit(): void
    {
        $shop = Shop::factory()->active()->create();
        $limit = (int) config('shop.homepage_promoted_limit');

        // Fabryka omija walidację — tworzymy ponad limit i sprawdzamy przycięcie.
        foreach (range(1, $limit + 2) as $i) {
            Product::factory()->create([
                'shop_id' => $shop->id,
                'is_active' => true,
                'show_on_homepage' => true,
                'name' => sprintf('Wyroznik %02d', $i),
            ]);
        }

        $content = $this->get($this->url($shop))->assertOk()->getContent();
        $shown = collect(range(1, $limit + 2))
            ->filter(fn (int $i) => str_contains($content, sprintf('Wyroznik %02d', $i)))
            ->count();

        $this->assertSame($limit, $shown);
    }

    public function test_home_shows_tag_cloud_linking_to_listing(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->promote($shop, 1)->first();
        $tag = $shop->tags()->create(['name' => 'Komunia', 'slug' => 'komunia']);
        $product->tags()->attach($tag->id);

        $this->get($this->url($shop))
            ->assertOk()
            ->assertSee('Zobacz produkty') // nagłówek kafla tagów
            ->assertSee('Komunia')
            ->assertSee('/produkty?tagi=komunia', false);
    }

    public function test_home_tag_cloud_skips_tags_without_active_products(): void
    {
        $shop = Shop::factory()->active()->create();
        $this->promote($shop, 1); // aktywny produkt bez tagu — chmura pusta

        $dead = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => false]);
        $tag = $shop->tags()->create(['name' => 'martwy', 'slug' => 'martwy']);
        $dead->tags()->attach($tag->id);

        $this->get($this->url($shop))
            ->assertOk()
            ->assertDontSee('Przeglądaj po tagach')
            ->assertDontSee('martwy');
    }

    public function test_falls_back_to_active_products_when_none_promoted(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'is_active' => true,
            'show_on_homepage' => false, // nie wyróżniony
        ]);

        // Brak wyróżnionych → główna pokazuje najnowsze aktywne, nie jest pusta.
        $this->get($this->url($shop))
            ->assertOk()
            ->assertSee($product->name);
    }
}
