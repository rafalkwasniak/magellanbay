<?php

namespace Tests\Feature\Storefront;

use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Shop;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Meta dane storefrontu: opis do wyników Google, adres kanoniczny, Open Graph
 * i `noindex` na stronach transakcyjnych.
 *
 * Audyt ursalogic (16.07.2026): 100% podstron BEZ meta opisu i BEZ canonicala —
 * najcięższy zarzut sekcji SEO. Znalezisko jest platformowe, więc naprawa działa
 * na wszystkie sklepy naraz i te testy pilnują, żeby nie zniknęła.
 */
class SeoMetaTest extends TestCase
{
    use RefreshDatabase;

    private function shop(array $attributes = []): Shop
    {
        return Shop::factory()->create($attributes + ['status' => 'active']);
    }

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    public function test_shop_home_uses_the_sellers_own_description(): void
    {
        $shop = $this->shop([
            'description' => '<p>Rowery szosowe i gravelowe z pasją dobierane od 2011 roku.</p>',
        ]);

        $this->get($this->host($shop))
            ->assertOk()
            ->assertSee('<meta name="description" content="Rowery szosowe i gravelowe z pasją dobierane od 2011 roku.">', escape: false)
            ->assertSee('<link rel="canonical"', escape: false);
    }

    public function test_shop_without_a_description_still_gets_one(): void
    {
        $shop = $this->shop(['name' => 'I like my bike', 'description' => null, 'city' => 'Kraków']);

        // Pusty meta opis to gwarantowana strata — zawsze mamy zdanie z faktów.
        $this->get($this->host($shop))
            ->assertOk()
            ->assertSee('I like my bike — sklep internetowy z Kraków. Zobacz ofertę i zamów online.', escape: false);
    }

    public function test_product_page_describes_the_product_and_shows_its_photo(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Trek Madone SL7',
            'description' => '<p>Karbonowa rama, grupa Ultegra Di2, koła 50 mm.</p>',
        ]);

        $response = $this->get($this->host($shop).$product->storefrontPath())->assertOk();

        $response->assertSee('content="Karbonowa rama, grupa Ultegra Di2, koła 50 mm."', escape: false);
        $response->assertSee('property="og:title" content="Trek Madone SL7"', escape: false);
    }

    public function test_product_without_a_description_falls_back_to_facts(): void
    {
        $shop = $this->shop(['name' => 'I like my bike']);
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Bidon',
            'description' => null,
            'price_gross' => 39.99,
        ]);

        // Fakty, nie obietnice: ani słowa o dostawie, bo jeden sklep wysyła,
        // a inny daje wyłącznie odbiór osobisty.
        $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertSee('Bidon — 39,99 zł. Kup online w sklepie I like my bike.', escape: false);
    }

    public function test_information_page_describes_its_own_content(): void
    {
        $shop = $this->shop();
        $page = Page::factory()->create([
            'shop_id' => $shop->id,
            'title' => 'Dostawa',
            'content' => '<p>Wysyłamy w 24 godziny, kurierem lub do paczkomatu.</p>',
            'published' => true,
        ]);

        $this->get($this->host($shop).$page->storefrontPath())
            ->assertOk()
            ->assertSee('Wysyłamy w 24 godziny, kurierem lub do paczkomatu.', escape: false);
    }

    public function test_transactional_pages_are_kept_out_of_google(): void
    {
        $shop = $this->shop();

        $this->get($this->host($shop).'/koszyk')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', escape: false);
    }

    public function test_payment_page_is_noindexed_because_its_url_carries_a_token(): void
    {
        $shop = $this->shop();
        $order = Order::factory()->for($shop)->create();

        $this->get($this->host($shop).'/platnosc/'.$order->paymentToken())
            ->assertOk()
            ->assertSee('noindex', escape: false);
    }

    public function test_shop_home_is_indexable(): void
    {
        $shop = $this->shop();

        $this->get($this->host($shop))
            ->assertOk()
            ->assertDontSee('noindex', escape: false);
    }

    public function test_description_is_clipped_on_a_word_boundary(): void
    {
        $long = str_repeat('Rowery szosowe i gravelowe dobierane z pasją. ', 10);

        $clipped = Seo::clip($long);

        $this->assertLessThanOrEqual(Seo::MAX_DESCRIPTION + 1, mb_strlen($clipped));
        // Nie tniemy w połowie wyrazu (konwencja projektu).
        $this->assertStringEndsWith('…', $clipped);
        $this->assertStringNotContainsString('  ', $clipped);
    }

    public function test_canonical_keeps_the_page_number_but_drops_other_noise(): void
    {
        $shop = $this->shop();

        $response = $this->get($this->host($shop).'/produkty?page=2&utm_source=fb')->assertOk();

        $response->assertSee('rel="canonical" href="'.$this->host($shop).'/produkty?page=2"', escape: false);
    }
}
