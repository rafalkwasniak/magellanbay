<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Przeglądanie katalogu w sklepie (Etap 2, krok D — storefront).
 *
 * Strona kategorii i wykaz produktów dzielą JEDEN mechanizm filtrowania.
 * Testy pilnują, żeby zawężanie działało tak samo z obu stron — dwa osobne
 * filtrowania rozjechałyby się przy pierwszej poprawce, a kupujący widziałby
 * inną liczbę produktów w dwóch miejscach tego samego sklepu.
 */
class CatalogBrowsingTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->sellable()->create();
    }

    private function wezel(string $axis, string $name, ?Category $parent = null): Category
    {
        return $this->shop->categories()->create([
            'axis' => $axis,
            'name' => $name,
            'slug' => Str::slug($name),
            'parent_id' => $parent?->id,
        ]);
    }

    private function produkt(string $name, array $categories = []): Product
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'name' => $name,
            'is_active' => true,
        ]);

        $product->categories()->attach(collect($categories)->pluck('id')->all());

        return $product;
    }

    private function odwiedz(string $path)
    {
        return $this->get('https://'.$this->shop->host().$path);
    }

    // --- Strona kategorii --------------------------------------------------

    public function test_category_page_shows_only_its_products(): void
    {
        $kamien = $this->wezel('kind', 'Kamień');
        $metal = $this->wezel('kind', 'Metal');

        $this->produkt('Magnes kamienny', [$kamien]);
        $this->produkt('Token metalowy', [$metal]);

        $this->odwiedz('/rodzaj/kamien')
            ->assertOk()
            ->assertSee('Magnes kamienny')
            ->assertDontSee('Token metalowy');
    }

    /**
     * Wyższy poziom obejmuje całą gałąź — inaczej „Włochy" byłyby puste,
     * mając pod sobą pełen Rzym, i wyglądałyby jak usterka.
     */
    public function test_a_parent_category_shows_products_from_below(): void
    {
        $wlochy = $this->wezel('geo', 'Włochy');
        $rzym = $this->wezel('geo', 'Rzym', $wlochy);

        $this->produkt('Magnes z Rzymu', [$rzym]);

        $this->odwiedz('/geografia/wlochy')
            ->assertOk()
            ->assertSee('Magnes z Rzymu');
    }

    public function test_category_page_shows_its_children_to_go_deeper(): void
    {
        $wlochy = $this->wezel('geo', 'Włochy');
        $this->wezel('geo', 'Rzym', $wlochy);
        $this->produkt('Cokolwiek', [$wlochy]);

        $this->odwiedz('/geografia/wlochy')
            ->assertOk()
            ->assertSee('/geografia/rzym', false);
    }

    public function test_category_description_is_shown(): void
    {
        $node = $this->wezel('theme', 'Biegi');
        $node->update(['description' => 'Magnesy z maratonów i biegów ulicznych.']);
        $this->produkt('Magnes biegowy', [$node]);

        $this->odwiedz('/tematyka/biegi')
            ->assertOk()
            ->assertSee('Magnesy z maratonów i biegów ulicznych.');
    }

    public function test_unknown_category_is_not_found(): void
    {
        $this->odwiedz('/geografia/atlantyda')->assertNotFound();
    }

    /**
     * Ten sam slug na dwóch osiach to dwa różne działy — adres musi je
     * rozróżniać, a nie brać pierwszy z brzegu.
     */
    public function test_the_same_slug_on_two_axes_leads_to_two_pages(): void
    {
        $temat = $this->wezel('theme', 'Włochy');
        $miejsce = $this->wezel('geo', 'Włochy');

        $this->produkt('Album o Włoszech', [$temat]);
        $this->produkt('Magnes z Wenecji', [$miejsce]);

        $this->odwiedz('/tematyka/wlochy')
            ->assertOk()
            ->assertSee('Album o Włoszech')
            ->assertDontSee('Magnes z Wenecji');

        $this->odwiedz('/geografia/wlochy')
            ->assertOk()
            ->assertSee('Magnes z Wenecji')
            ->assertDontSee('Album o Włoszech');
    }

    public function test_inactive_product_stays_out_of_the_category(): void
    {
        $node = $this->wezel('kind', 'Kamień');
        $product = $this->produkt('Wycofany magnes', [$node]);
        $product->update(['is_active' => false]);

        $this->odwiedz('/rodzaj/kamien')
            ->assertOk()
            ->assertDontSee('Wycofany magnes');
    }

    // --- Filtry na wykazie -------------------------------------------------

    public function test_listing_filters_by_category_from_the_query(): void
    {
        $kamien = $this->wezel('kind', 'Kamień');
        $metal = $this->wezel('kind', 'Metal');

        $this->produkt('Magnes kamienny', [$kamien]);
        $this->produkt('Token metalowy', [$metal]);

        $this->odwiedz('/produkty?rodzaj=kamien')
            ->assertOk()
            ->assertSee('Magnes kamienny')
            ->assertDontSee('Token metalowy');
    }

    /**
     * Osie są niezależne, ale ich iloczyn ma sens: „kamienne ORAZ z Włoch".
     */
    public function test_filters_from_two_axes_are_combined(): void
    {
        $kamien = $this->wezel('kind', 'Kamień');
        $metal = $this->wezel('kind', 'Metal');
        $wlochy = $this->wezel('geo', 'Włochy');

        $this->produkt('Kamień z Włoch', [$kamien, $wlochy]);
        $this->produkt('Metal z Włoch', [$metal, $wlochy]);
        $this->produkt('Kamień znikąd', [$kamien]);

        $this->odwiedz('/produkty?rodzaj=kamien&geografia=wlochy')
            ->assertOk()
            ->assertSee('Kamień z Włoch')
            ->assertDontSee('Metal z Włoch')
            ->assertDontSee('Kamień znikąd');
    }

    /**
     * Adres z literówką ma pokazać szerszy zbiór, a nie pustkę ani błąd.
     */
    public function test_unknown_filter_slug_is_ignored(): void
    {
        $kamien = $this->wezel('kind', 'Kamień');
        $this->produkt('Magnes kamienny', [$kamien]);

        $this->odwiedz('/produkty?rodzaj=nie-ma-takiego')
            ->assertOk()
            ->assertSee('Magnes kamienny');
    }

    /**
     * Zawężanie na stronie kategorii ma zostać w tej kategorii, a nie
     * wyrzucać kupującego z powrotem na pełny wykaz.
     */
    public function test_filter_links_on_a_category_page_keep_the_category(): void
    {
        $kamien = $this->wezel('kind', 'Kamień');
        $wlochy = $this->wezel('geo', 'Włochy');
        $this->produkt('Kamień z Włoch', [$kamien, $wlochy]);

        $this->odwiedz('/rodzaj/kamien')
            ->assertOk()
            ->assertSee('/rodzaj/kamien?geografia=wlochy', false);
    }

    /**
     * Każdy odnośnik w panelu opisuje SWÓJ filtr, a nie sumę wszystkich
     * wypisanych wyżej.
     *
     * Pierwsza wersja używała `push`, który mutuje kolekcję w miejscu — więc
     * link do „Polski" prowadził na `?geografia=europa,polska`, choć nikt nie
     * wybrał Europy. Testy tego nie widziały, bo sprawdzały wynik filtrowania,
     * a nie treść odnośników; złapał to dopiero podgląd żywej strony.
     */
    public function test_filter_links_do_not_accumulate_earlier_items(): void
    {
        $europa = $this->wezel('geo', 'Europa');
        $polska = $this->wezel('geo', 'Polska', $europa);
        $this->produkt('Magnes z Polski', [$polska]);

        $this->odwiedz('/produkty')
            ->assertOk()
            ->assertSee('/produkty?geografia=polska', false)
            ->assertDontSee('geografia=europa%2Cpolska', false);
    }

    /**
     * Filtr prowadzący do pustej strony jest gorszy niż jego brak.
     */
    public function test_dead_filters_are_not_offered(): void
    {
        $kamien = $this->wezel('kind', 'Kamień');
        $this->wezel('geo', 'Antarktyda');
        $this->produkt('Magnes kamienny', [$kamien]);

        $this->odwiedz('/produkty')
            ->assertOk()
            ->assertDontSee('Antarktyda');
    }

    // --- Karta produktu i mapa strony --------------------------------------

    public function test_product_page_links_back_to_its_categories(): void
    {
        $kamien = $this->wezel('kind', 'Kamień');
        $wlochy = $this->wezel('geo', 'Włochy');
        $product = $this->produkt('Magnes kamienny', [$kamien, $wlochy]);

        $this->odwiedz($product->storefrontPath())
            ->assertOk()
            ->assertSee('/rodzaj/kamien', false)
            ->assertSee('/geografia/wlochy', false);
    }

    public function test_sitemap_lists_categories_that_have_products(): void
    {
        $kamien = $this->wezel('kind', 'Kamień');
        $this->wezel('theme', 'Pusta tematyka');
        $this->produkt('Magnes kamienny', [$kamien]);

        $this->odwiedz('/sitemap.xml')
            ->assertOk()
            ->assertSee('/rodzaj/kamien', false)
            ->assertDontSee('/tematyka/pusta-tematyka', false);
    }

    /**
     * „Włochy" bez własnych produktów, ale z pełnym Rzymem pod spodem, są
     * stroną z treścią — mapa liczy po gałęzi, tak jak sama strona.
     */
    public function test_sitemap_counts_the_whole_branch(): void
    {
        $wlochy = $this->wezel('geo', 'Włochy');
        $rzym = $this->wezel('geo', 'Rzym', $wlochy);
        $this->produkt('Magnes z Rzymu', [$rzym]);

        $this->odwiedz('/sitemap.xml')
            ->assertOk()
            ->assertSee('/geografia/wlochy', false);
    }
}
