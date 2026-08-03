<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mapa strony i robots.txt — osobne dla centrali i dla KAŻDEGO storefrontu.
 *
 * Sedno: mapa działa bez udziału sprzedawcy, bo robot sam pobiera `robots.txt`
 * i znajduje w nim wskazanie własnej mapy hosta.
 */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private function shopUrl(Shop $shop, string $path): string
    {
        return 'https://'.$shop->host().$path;
    }

    public function test_central_sitemap_lists_platform_pages(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(url('/'), false)
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false);
    }

    public function test_central_robots_points_at_the_central_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
            ->assertSee('Disallow: /panel', false);
    }

    public function test_storefront_sitemap_lists_only_its_own_shop(): void
    {
        $shop = Shop::factory()->create(['slug' => 'lemoniady']);
        $mine = Product::factory()->for($shop)->create(['is_active' => true]);

        $other = Shop::factory()->create(['slug' => 'bukiety']);
        $theirs = Product::factory()->for($other)->create(['is_active' => true]);

        $shop->refreshVisibility();

        $response = $this->get($this->shopUrl($shop, '/sitemap.xml'));

        $response->assertOk()
            ->assertSee($this->shopUrl($shop, $mine->storefrontPath()), false)
            ->assertSee($this->shopUrl($shop, '/produkty'), false)
            // Cudzy sklep nie ma prawa wypłynąć w tej mapie.
            ->assertDontSee($theirs->storefrontPath(), false)
            ->assertDontSee('bukiety.', false);
    }

    public function test_storefront_sitemap_skips_inactive_products(): void
    {
        $shop = Shop::factory()->create(['slug' => 'lemoniady']);
        Product::factory()->for($shop)->create(['is_active' => true]);
        $hidden = Product::factory()->for($shop)->create(['is_active' => false]);

        $shop->refreshVisibility();

        $this->get($this->shopUrl($shop, '/sitemap.xml'))
            ->assertOk()
            ->assertDontSee($hidden->storefrontPath(), false);
    }

    public function test_storefront_sitemap_includes_published_information_pages(): void
    {
        $shop = Shop::factory()->create(['slug' => 'lemoniady']);
        Product::factory()->for($shop)->create(['is_active' => true]);
        $shop->refreshVisibility();

        $page = Page::factory()->for($shop)->create(['published' => true]);

        $this->get($this->shopUrl($shop, '/sitemap.xml'))
            ->assertOk()
            ->assertSee($this->shopUrl($shop, $page->storefrontPath()), false);
    }

    public function test_transactional_pages_never_reach_the_sitemap(): void
    {
        // Koszyk, kasa, konto i zwroty mają `noindex` po audycie SEO —
        // wpisanie ich do mapy byłoby sprzecznością z samym sobą.
        $shop = Shop::factory()->create(['slug' => 'lemoniady']);
        Product::factory()->for($shop)->create(['is_active' => true]);
        $shop->refreshVisibility();

        $xml = $this->get($this->shopUrl($shop, '/sitemap.xml'))->assertOk()->getContent();

        foreach (['/koszyk', '/kasa', '/moje-konto', '/platnosc', '/zwrot', '/wypisz-sie'] as $path) {
            $this->assertStringNotContainsString('<loc>https://'.$shop->host().$path, $xml);
        }
    }

    public function test_draft_shop_has_no_sitemap_and_no_pointer_to_one(): void
    {
        // Sklep bez aktywnych produktów pokazuje „już wkrótce". Zapraszanie
        // Google do indeksowania pustej strony psuje ocenę, zanim sklep wystartuje.
        $shop = Shop::factory()->create(['slug' => 'szkic']);
        $shop->refreshVisibility();

        $this->assertFalse($shop->fresh()->isVisible());

        $this->get($this->shopUrl($shop, '/sitemap.xml'))->assertNotFound();

        $this->get($this->shopUrl($shop, '/robots.txt'))
            ->assertOk()
            ->assertDontSee('Sitemap:', false);
    }

    public function test_storefront_robots_points_at_its_own_sitemap_not_the_central_one(): void
    {
        // To pilnuje KOLEJNOŚCI TRAS: trasy centrali nie są przypięte do hosta,
        // więc gdyby stały przed grupą subdomen, przechwyciłyby storefronty
        // i każdy sklep dostałby mapę centrali. Usterka byłaby cicha.
        $shop = Shop::factory()->create(['slug' => 'lemoniady']);
        Product::factory()->for($shop)->create(['is_active' => true]);
        $shop->refreshVisibility();

        // Adres centrali składamy z configu, a NIE przez url() — po żądaniu na
        // subdomenie url() zwraca właśnie tę subdomenę i asercja sprawdzałaby
        // sama siebie.
        $centralSitemap = 'https://'.config('tenancy.central_domain').'/sitemap.xml';

        $this->get($this->shopUrl($shop, '/robots.txt'))
            ->assertOk()
            ->assertSee('Sitemap: '.$this->shopUrl($shop, '/sitemap.xml'), false)
            ->assertDontSee('Sitemap: '.$centralSitemap, false)
            ->assertSee('Disallow: /koszyk', false);
    }

    public function test_every_blocked_path_matches_a_real_route(): void
    {
        // Reguła `Disallow` wskazująca nieistniejącą ścieżkę nie daje żadnego
        // objawu — plik wygląda poprawnie i nic nie blokuje. Tak wszedł
        // `Disallow: /konto` przy trasie `/moje-konto`.
        $shop = Shop::factory()->create(['slug' => 'lemoniady']);
        Product::factory()->for($shop)->create(['is_active' => true]);
        $shop->refreshVisibility();

        $robots = $this->get($this->shopUrl($shop, '/robots.txt'))->assertOk()->getContent();

        preg_match_all('/^Disallow: (.+)$/m', $robots, $matches);
        $this->assertNotEmpty($matches[1], 'robots.txt storefrontu nie blokuje niczego.');

        $uris = collect(app('router')->getRoutes())->map(fn ($route) => '/'.$route->uri());

        foreach ($matches[1] as $blocked) {
            $this->assertTrue(
                $uris->contains(fn (string $uri) => str_starts_with($uri, $blocked)),
                "robots.txt blokuje `{$blocked}`, ale żadna trasa się od tego nie zaczyna."
            );
        }
    }

    public function test_static_robots_file_must_not_exist(): void
    {
        // `.htaccess` oddaje istniejące pliki z pominięciem Laravela, więc
        // odtworzenie `public/robots.txt` uciszyłoby trasę bez żadnego objawu
        // poza tym, że „przestało działać".
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }
}
