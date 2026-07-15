<?php

namespace Tests\Feature\Storefront;

use App\Models\Page;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hierarchia nagłówków na storefroncie: dokument zaczyna się od h1 i nigdy nie
 * przeskakuje poziomu (h1 → h3 to błąd dostępności — czytnik ekranu gubi wtedy
 * strukturę strony).
 *
 * Ten test istnieje, bo poziom nagłówka jest u nas NIEWIDOCZNY: preflight
 * Tailwinda resetuje h1–h6 do `font-size: inherit`, a stopień pisma niosą klasy.
 * Można więc wpisać dowolne `h3` i nic się nie zmieni na ekranie — błąd nie ma
 * jak się ujawnić inaczej niż tędy.
 */
class HeadingHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    /**
     * @return list<int>
     */
    private function headingLevels(string $html): array
    {
        preg_match_all('/<h([1-6])[\s>]/', $html, $matches);

        return array_map('intval', $matches[1]);
    }

    private function assertHeadingsFormAnOutline(string $html, string $where): void
    {
        $levels = $this->headingLevels($html);

        $this->assertNotEmpty($levels, $where.': strona nie ma żadnego nagłówka');
        $this->assertSame(1, $levels[0], $where.': pierwszy nagłówek musi być h1, jest h'.$levels[0]);

        $previous = $levels[0];
        foreach ($levels as $index => $level) {
            $this->assertLessThanOrEqual(
                $previous + 1,
                $level,
                $where.': przeskok z h'.$previous.' do h'.$level.' (nagłówek nr '.($index + 1).')',
            );
            $previous = $level;
        }
    }

    /** Sklep z pełnym kompletem: produkty w siatce, „O sklepie", wyróżniona strona, stopka z kontaktem. */
    private function fullShop(): Shop
    {
        $shop = Shop::factory()->active()->create([
            'description' => '<p>Robimy rowery z pasją.</p>',
            'contact_email' => 'sklep@example.com',
        ]);

        Product::factory()->count(2)->create([
            'shop_id' => $shop->id,
            'is_active' => true,
            'show_on_homepage' => true,
        ]);

        Page::factory()->for($shop)->create([
            'title' => 'Nasz zespół',
            'show_on_homepage' => true,
        ]);

        return $shop;
    }

    public function test_homepage_headings_form_an_outline(): void
    {
        $shop = $this->fullShop();

        $this->assertHeadingsFormAnOutline(
            $this->get($this->host($shop).'/')->assertOk()->getContent(),
            'Strona główna',
        );
    }

    /** Układ solo (1 produkt) idzie inną gałęzią widoku niż siatka — też musi się zgadzać. */
    public function test_homepage_with_a_single_product_forms_an_outline(): void
    {
        $shop = Shop::factory()->active()->create(['description' => '<p>Robimy rowery.</p>']);
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'show_on_homepage' => true]);

        $this->assertHeadingsFormAnOutline(
            $this->get($this->host($shop).'/')->assertOk()->getContent(),
            'Strona główna (1 produkt)',
        );
    }

    public function test_product_listing_headings_form_an_outline(): void
    {
        $shop = $this->fullShop();

        $this->assertHeadingsFormAnOutline(
            $this->get($this->host($shop).'/produkty')->assertOk()->getContent(),
            'Wykaz produktów',
        );
    }

    public function test_product_page_headings_form_an_outline(): void
    {
        $shop = $this->fullShop();
        $product = $shop->products()->firstOrFail();

        $this->assertHeadingsFormAnOutline(
            $this->get($this->host($shop).$product->storefrontPath())->assertOk()->getContent(),
            'Karta produktu',
        );
    }

    public function test_information_page_headings_form_an_outline(): void
    {
        $shop = $this->fullShop();
        $page = $shop->pages()->where('is_system', true)->firstOrFail();

        $this->assertHeadingsFormAnOutline(
            $this->get($this->host($shop).$page->storefrontPath())->assertOk()->getContent(),
            'Strona informacyjna',
        );
    }

    /**
     * Koszyk to najuboższa strona storefrontu: poza h1 nie ma nic własnego, więc
     * kolejnymi nagłówkami są dopiero kolumny STOPKI. Dlatego to właśnie tutaj
     * ujawnia się ich poziom — na stronach z własnymi sekcjami stopka schodzi po
     * jakimś h2 i błędne h3 udaje poprawne zejście o jeden.
     */
    public function test_cart_headings_form_an_outline(): void
    {
        $shop = $this->fullShop();

        $this->assertHeadingsFormAnOutline(
            $this->get($this->host($shop).'/koszyk')->assertOk()->getContent(),
            'Koszyk',
        );
    }
}
