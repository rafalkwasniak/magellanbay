<?php

namespace Tests\Feature\Storefront;

use App\Models\Page;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kafelki treści pod ofertą na stronie głównej: wirtualne „O sklepie" (zawsze
 * pierwsze, treść z shop.description) plus strony wyróżnione przez sprzedawcę
 * (`show_on_homepage`). Reguła układu jest jedna — policz kafelki, ułóż 1/2/3
 * w rzędzie; sufit (2 strony + „O sklepie") gwarantuje, że siatka się nie zawija.
 *
 * Kafelek ma dwa stany: treść, która się MIEŚCI, jedzie w całości z formatowaniem
 * i bez odnośnika (cel miałby to samo); treść UCIĘTA jedzie jako czysty wycinek
 * plus „Czytaj więcej".
 */
class HomeContentTilesTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    /**
     * Sam <main>, bez nagłówka i stopki. Tytuły opublikowanych stron pojawiają się
     * także w menu, a stopka ma własne klasy siatki — bez tego cięcia asercje
     * „nie ma kafelka" i „ile kolumn" łapałyby cudze trafienia.
     */
    private function mainSection(string $html): string
    {
        $start = (int) strpos($html, '<main');
        $end = (int) strpos($html, '</main>');

        return substr($html, $start, $end - $start);
    }

    private function homeMain(Shop $shop): string
    {
        return $this->mainSection(
            $this->get($this->host($shop).'/')->assertOk()->getContent(),
        );
    }

    /** Sklep bez opisu → brak kafelka „O sklepie"; liczy się tylko to, co wyróżnimy. */
    private function shopWithoutAbout(): Shop
    {
        return Shop::factory()->active()->create(['description' => null]);
    }

    /** Sklep z krótkim opisem → kafelek „O sklepie" jest, ale poza menu (poniżej progu). */
    private function shopWithAbout(): Shop
    {
        return Shop::factory()->active()->create(['description' => '<p>Robimy rowery.</p>']);
    }

    private function longContent(): string
    {
        return '<div>'.str_repeat('rower ', (int) config('pages.excerpt_length')).'</div>';
    }

    public function test_page_that_fits_renders_whole_and_formatted_without_a_link(): void
    {
        $shop = $this->shopWithoutAbout();
        $page = Page::factory()->for($shop)->create([
            'title' => 'Wywiady',
            'content' => '<div>Rozmowa dla <strong>Wydawcy</strong>.</div>',
            'show_on_homepage' => true,
        ]);

        $main = $this->homeMain($shop);

        $this->assertStringContainsString('Wywiady', $main);
        $this->assertStringContainsString('<strong>Wydawcy</strong>', $main); // formatowanie zachowane
        $this->assertStringNotContainsString('Czytaj więcej', $main);
        $this->assertStringNotContainsString('href="'.$page->storefrontPath().'"', $main);
    }

    /**
     * Krótka strona „Wywiady" nie potrzebuje „Czytaj więcej" mimo linków w treści
     * — skoro się mieści, są klikalne wprost w kafelku.
     */
    public function test_links_in_a_fitting_page_stay_clickable_in_the_tile(): void
    {
        $shop = $this->shopWithoutAbout();
        Page::factory()->for($shop)->create([
            'title' => 'Wywiady',
            'content' => '<div>Rozmowa: <a href="https://radio.example">Radio</a></div>',
            'show_on_homepage' => true,
        ]);

        $main = $this->homeMain($shop);

        $this->assertStringContainsString('href="https://radio.example"', $main);
        $this->assertStringNotContainsString('Czytaj więcej', $main);
    }

    public function test_long_page_shows_excerpt_with_a_link(): void
    {
        $shop = $this->shopWithoutAbout();
        $page = Page::factory()->for($shop)->create([
            'title' => 'Długa opowieść',
            'content' => $this->longContent(),
            'show_on_homepage' => true,
        ]);

        $main = $this->homeMain($shop);

        $this->assertStringContainsString('Czytaj więcej', $main);
        $this->assertStringContainsString('href="'.$page->storefrontPath().'"', $main);
    }

    public function test_unpromoted_published_page_has_no_tile(): void
    {
        $shop = $this->shopWithoutAbout();
        Page::factory()->for($shop)->create([
            'title' => 'Zwykła strona',
            'show_on_homepage' => false,
        ]);

        $this->assertStringNotContainsString('Zwykła strona', $this->homeMain($shop));
    }

    public function test_unpublished_promoted_page_has_no_tile(): void
    {
        $shop = $this->shopWithoutAbout();
        Page::factory()->for($shop)->unpublished()->create([
            'title' => 'Szkic wyróżniony',
            'show_on_homepage' => true,
        ]);

        $this->assertStringNotContainsString('Szkic wyróżniony', $this->homeMain($shop));
    }

    /** Pustej strony nie blokujemy przy zapisie, ale kafelka jej nie dajemy. */
    public function test_promoted_page_without_content_has_no_tile(): void
    {
        $shop = $this->shopWithoutAbout();
        Page::factory()->for($shop)->create([
            'title' => 'Pusta strona',
            'content' => '<div></div>',
            'show_on_homepage' => true,
        ]);

        $this->assertStringNotContainsString('Pusta strona', $this->homeMain($shop));
    }

    public function test_about_comes_first_then_pages_by_position(): void
    {
        $shop = $this->shopWithAbout();
        Page::factory()->for($shop)->create(['title' => 'Druga', 'position' => 5, 'show_on_homepage' => true]);
        Page::factory()->for($shop)->create(['title' => 'Pierwsza', 'position' => 1, 'show_on_homepage' => true]);

        $main = $this->homeMain($shop);

        $this->assertLessThan(strpos($main, 'Pierwsza'), strpos($main, (string) config('pages.about.title')));
        $this->assertLessThan(strpos($main, 'Druga'), strpos($main, 'Pierwsza'));
    }

    public function test_one_tile_spans_full_width(): void
    {
        $main = $this->homeMain($this->shopWithAbout());

        $this->assertStringNotContainsString('sm:grid-cols-2', $main);
        $this->assertStringNotContainsString('lg:grid-cols-3', $main);
    }

    public function test_two_tiles_sit_side_by_side(): void
    {
        $shop = $this->shopWithAbout();
        Page::factory()->for($shop)->create(['title' => 'Wywiady', 'show_on_homepage' => true]);

        $main = $this->homeMain($shop);

        $this->assertStringContainsString('sm:grid-cols-2', $main);
        $this->assertStringNotContainsString('lg:grid-cols-3', $main);
    }

    public function test_three_tiles_sit_three_across(): void
    {
        $shop = $this->shopWithAbout();
        Page::factory()->for($shop)->create(['title' => 'Wywiady', 'position' => 1, 'show_on_homepage' => true]);
        Page::factory()->for($shop)->create(['title' => 'Spotkania', 'position' => 2, 'show_on_homepage' => true]);

        $main = $this->homeMain($shop);

        $this->assertStringContainsString('sm:grid-cols-2', $main);
        $this->assertStringContainsString('lg:grid-cols-3', $main);
    }

    /** Bez „O sklepie" sufit stron sam w sobie daje najwyżej 2 kafelki. */
    public function test_pages_alone_max_out_at_two_tiles(): void
    {
        $shop = $this->shopWithoutAbout();
        Page::factory()->count(2)->for($shop)->create(['show_on_homepage' => true]);

        $main = $this->homeMain($shop);

        $this->assertStringContainsString('sm:grid-cols-2', $main);
        $this->assertStringNotContainsString('lg:grid-cols-3', $main);
    }

    /**
     * Siatka bezpieczeństwa przy odczycie: gdyby w bazie zostało więcej wyróżnień
     * niż dzisiejszy sufit (np. po obniżeniu limitu w configu), główna i tak
     * pokazuje tylko tyle, ile wolno — zamiast rozjechać układ.
     */
    public function test_more_promoted_pages_than_the_limit_are_capped(): void
    {
        $shop = $this->shopWithoutAbout();
        $limit = (int) config('pages.homepage_promoted_limit');
        Page::factory()->count($limit + 2)->for($shop)->create(['show_on_homepage' => true]);

        $main = $this->homeMain($shop);

        $this->assertSame($limit, substr_count($main, 'st-card st-border flex flex-col'));
    }
}
