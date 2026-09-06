<?php

namespace Tests\Feature\Seller;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Katalog w trzech niezależnych osiach (Etap 2, krok D).
 *
 * Rodzaj jest jednokrotny (linia produkcyjna), tematyka wielokrotna, geografia
 * wielokrotna i zagnieżdżona. Wszystkie trzy stoją w jednej tabeli i różnią się
 * wyłącznie configiem — testy pilnują, żeby ta różnica naprawdę działała.
 */
class CatalogAxesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sklep(): array
    {
        $owner = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $owner->id]);

        return [$owner, $shop];
    }

    private function wezel(Shop $shop, string $axis, string $name, ?Category $parent = null): Category
    {
        return $shop->categories()->create([
            'axis' => $axis,
            'name' => $name,
            'slug' => Str::slug($name),
            'parent_id' => $parent?->id,
        ]);
    }

    // --- Ekran osi ---------------------------------------------------------

    public function test_each_axis_has_its_own_screen(): void
    {
        [$owner, $shop] = $this->sklep();
        $this->wezel($shop, 'kind', 'Kamień');
        $this->wezel($shop, 'geo', 'Włochy');

        $this->actingAs($owner)
            ->get(route('seller.categories.index', 'rodzaj'))
            ->assertOk()
            ->assertSee('Kamień')
            ->assertDontSee('Włochy');

        $this->actingAs($owner)
            ->get(route('seller.categories.index', 'geografia'))
            ->assertOk()
            ->assertSee('Włochy');
    }

    public function test_unknown_axis_is_not_found(): void
    {
        [$owner] = $this->sklep();

        $this->actingAs($owner)
            ->get(route('seller.categories.index', 'kolor'))
            ->assertNotFound();
    }

    public function test_catalog_entry_redirects_to_the_first_axis(): void
    {
        [$owner] = $this->sklep();

        $this->actingAs($owner)
            ->get(route('seller.categories.home'))
            ->assertRedirect(route('seller.categories.index', 'rodzaj'));
    }

    // --- Zapis osi ---------------------------------------------------------

    public function test_a_node_is_added_from_the_empty_row(): void
    {
        [$owner, $shop] = $this->sklep();

        $this->actingAs($owner)
            ->from(route('seller.categories.index', 'rodzaj'))
            ->post(route('seller.categories.save', 'rodzaj'), [
                'items' => ['nowa' => ['name' => 'Metalowy Token', 'position' => '0']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'shop_id' => $shop->id,
            'axis' => 'kind',
            'name' => 'Metalowy Token',
            'slug' => 'metalowy-token',
        ]);
    }

    /**
     * Slug jest adresem. Poprawka literówki w nazwie nie może przenieść
     * kategorii pod inny adres, bo zabiłaby rozesłane linki i pozycję
     * w wyszukiwarce.
     */
    public function test_renaming_a_node_does_not_move_its_address(): void
    {
        [$owner, $shop] = $this->sklep();
        $node = $this->wezel($shop, 'kind', 'Kamien');

        $this->actingAs($owner)
            ->post(route('seller.categories.save', 'rodzaj'), [
                'items' => [$node->id => ['name' => 'Kamień', 'position' => '0']],
            ])
            ->assertSessionHasNoErrors();

        $node->refresh();
        $this->assertSame('Kamień', $node->name);
        $this->assertSame('kamien', $node->slug);
    }

    public function test_duplicate_names_get_distinct_addresses(): void
    {
        [$owner, $shop] = $this->sklep();
        $this->wezel($shop, 'geo', 'Włochy');

        $this->actingAs($owner)
            ->post(route('seller.categories.save', 'geografia'), [
                'items' => ['nowa' => ['name' => 'Włochy']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['wlochy', 'wlochy-2'], $shop->categories()->onAxis('geo')->pluck('slug')->sort()->values()->all());
    }

    /**
     * Ta sama nazwa na DWÓCH osiach to dwa różne węzły — „Włochy" bywają
     * i tematyką, i miejscem.
     */
    public function test_the_same_slug_may_exist_on_two_axes(): void
    {
        [, $shop] = $this->sklep();
        $this->wezel($shop, 'theme', 'Włochy');
        $this->wezel($shop, 'geo', 'Włochy');

        $this->assertSame(2, $shop->categories()->where('slug', 'wlochy')->count());
    }

    // --- Hierarchia --------------------------------------------------------

    public function test_a_node_can_sit_inside_another_on_a_hierarchical_axis(): void
    {
        [$owner, $shop] = $this->sklep();
        $wlochy = $this->wezel($shop, 'geo', 'Włochy');

        $this->actingAs($owner)
            ->post(route('seller.categories.save', 'geografia'), [
                'items' => ['nowa' => ['name' => 'Rzym', 'parent_id' => (string) $wlochy->id]],
            ])
            ->assertSessionHasNoErrors();

        $rzym = $shop->categories()->where('name', 'Rzym')->firstOrFail();
        $this->assertSame($wlochy->id, $rzym->parent_id);
        $this->assertSame(['Włochy', 'Rzym'], array_map(fn (Category $c): string => $c->name, $rzym->path()));
    }

    /**
     * Wyższy poziom obejmuje wszystko, co pod nim — inaczej „Włochy" byłyby
     * puste i wyglądałyby jak usterka.
     */
    public function test_a_parent_covers_its_whole_branch(): void
    {
        [, $shop] = $this->sklep();
        $wlochy = $this->wezel($shop, 'geo', 'Włochy');
        $rzym = $this->wezel($shop, 'geo', 'Rzym', $wlochy);
        $trastevere = $this->wezel($shop, 'geo', 'Zatybrze', $rzym);

        $this->assertEqualsCanonicalizing(
            [$wlochy->id, $rzym->id, $trastevere->id],
            $wlochy->branchIds(),
        );
        $this->assertSame([$rzym->id, $trastevere->id], $rzym->branchIds());
    }

    public function test_a_node_cannot_become_its_own_descendant(): void
    {
        [$owner, $shop] = $this->sklep();
        $wlochy = $this->wezel($shop, 'geo', 'Włochy');
        $rzym = $this->wezel($shop, 'geo', 'Rzym', $wlochy);

        // Włochy wewnątrz Rzymu = gałąź odrywa się od drzewa i znika z katalogu
        // razem z produktami, nie dając o sobie znać.
        $this->actingAs($owner)
            ->from(route('seller.categories.index', 'geografia'))
            ->post(route('seller.categories.save', 'geografia'), [
                'items' => [$wlochy->id => ['name' => 'Włochy', 'parent_id' => (string) $rzym->id]],
            ])
            ->assertSessionHas('error');

        $this->assertNull($wlochy->fresh()->parent_id);
    }

    public function test_nesting_stops_at_the_configured_depth(): void
    {
        [$owner, $shop] = $this->sklep();
        $a = $this->wezel($shop, 'geo', 'Europa');
        $b = $this->wezel($shop, 'geo', 'Włochy', $a);
        $c = $this->wezel($shop, 'geo', 'Rzym', $b);

        // config('catalog.max_depth') = 3, a Rzym stoi już na trzecim poziomie.
        $this->actingAs($owner)
            ->from(route('seller.categories.index', 'geografia'))
            ->post(route('seller.categories.save', 'geografia'), [
                'items' => ['nowa' => ['name' => 'Zatybrze', 'parent_id' => (string) $c->id]],
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, $shop->categories()->where('name', 'Zatybrze')->count());
    }

    /**
     * Kasowanie idzie w bazie kaskadą, więc węzeł z gałęzią zabrałby ją ze sobą
     * — cicho i nieodwracalnie.
     */
    public function test_a_node_with_children_is_not_deleted(): void
    {
        [$owner, $shop] = $this->sklep();
        $wlochy = $this->wezel($shop, 'geo', 'Włochy');
        $this->wezel($shop, 'geo', 'Rzym', $wlochy);

        $this->actingAs($owner)
            ->from(route('seller.categories.index', 'geografia'))
            ->post(route('seller.categories.save', 'geografia'), [
                'items' => [$wlochy->id => ['name' => 'Włochy', '_delete' => '1']],
            ])
            ->assertSessionHas('error');

        $this->assertSame(2, $shop->categories()->onAxis('geo')->count());
    }

    public function test_a_leaf_is_deleted_and_products_survive(): void
    {
        [$owner, $shop] = $this->sklep();
        $node = $this->wezel($shop, 'theme', 'Biegi');
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $product->categories()->attach($node->id);

        $this->actingAs($owner)
            ->post(route('seller.categories.save', 'tematyka'), [
                'items' => [$node->id => ['name' => 'Biegi', '_delete' => '1']],
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, $shop->categories()->onAxis('theme')->count());
        $this->assertNotNull($product->fresh());
    }

    // --- Produkt w katalogu ------------------------------------------------

    public function test_product_is_placed_on_several_axes_at_once(): void
    {
        [$owner, $shop] = $this->sklep();
        $kind = $this->wezel($shop, 'kind', 'Kamień');
        $theme = $this->wezel($shop, 'theme', 'Biegi');
        $unesco = $this->wezel($shop, 'theme', 'UNESCO');
        $geo = $this->wezel($shop, 'geo', 'Rzym');

        $this->actingAs($owner)
            ->post(route('seller.products.store'), $this->payload([
                'categories' => [
                    'kind' => (string) $kind->id,
                    'theme' => [(string) $theme->id, (string) $unesco->id],
                    'geo' => [(string) $geo->id],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $product = $shop->products()->firstOrFail();
        $this->assertSame($kind->id, $product->kind()?->id);
        $this->assertEqualsCanonicalizing([$theme->id, $unesco->id], $product->categoriesOn('theme')->pluck('id')->all());
        $this->assertSame([$geo->id], $product->categoriesOn('geo')->pluck('id')->all());
    }

    /**
     * Rodzaj to linia produkcyjna, nie cecha opisowa — dwa naraz nie mają sensu
     * i przy wstrzymaniu sprzedaży serii nie dałoby się powiedzieć, którą.
     */
    public function test_single_choice_axis_rejects_two_values(): void
    {
        [$owner, $shop] = $this->sklep();
        $a = $this->wezel($shop, 'kind', 'Kamień');
        $b = $this->wezel($shop, 'kind', 'Metal');

        $this->actingAs($owner)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->payload([
                'categories' => ['kind' => [(string) $a->id, (string) $b->id]],
            ]))
            ->assertSessionHasErrors('categories.kind');

        $this->assertSame(0, $shop->products()->count());
    }

    /**
     * `exists` widzi tylko, że węzeł należy do sklepu — nie widzi, że „Rzym"
     * podrzucono jako rodzaj. Bez osobnego sprawdzenia katalog mówiłby
     * nieprawdę, nie wywalając się.
     */
    public function test_category_from_the_wrong_axis_is_rejected(): void
    {
        [$owner, $shop] = $this->sklep();
        $geo = $this->wezel($shop, 'geo', 'Rzym');

        $this->actingAs($owner)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->payload([
                'categories' => ['kind' => (string) $geo->id],
            ]))
            ->assertSessionHasErrors('categories.kind');
    }

    public function test_category_from_another_shop_is_rejected(): void
    {
        [$owner, $shop] = $this->sklep();
        [, $obcy] = $this->sklep();
        $cudza = $this->wezel($obcy, 'theme', 'Cudza tematyka');

        $this->actingAs($owner)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->payload([
                'categories' => ['theme' => [(string) $cudza->id]],
            ]))
            ->assertSessionHasErrors('category_ids.0');

        $this->assertSame(0, $shop->products()->count());
    }

    /**
     * Pivot nie wie o osiach, więc zapis jednej osi nie może skasować
     * przypisań pozostałych.
     */
    public function test_saving_one_axis_keeps_the_others(): void
    {
        [$owner, $shop] = $this->sklep();
        $kind = $this->wezel($shop, 'kind', 'Kamień');
        $theme = $this->wezel($shop, 'theme', 'Biegi');

        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $product->categories()->attach([$kind->id, $theme->id]);

        $this->actingAs($owner)
            ->post(route('seller.products.update', $product), $this->payload([
                'categories' => ['kind' => (string) $kind->id, 'theme' => [(string) $theme->id]],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$kind->id, $theme->id],
            $product->fresh()->categories->pluck('id')->all(),
        );
    }

    public function test_unchecking_every_category_clears_the_product(): void
    {
        [$owner, $shop] = $this->sklep();
        $theme = $this->wezel($shop, 'theme', 'Biegi');
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $product->categories()->attach($theme->id);

        $this->actingAs($owner)
            ->post(route('seller.products.update', $product), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $product->fresh()->categories);
    }

    /**
     * Ekran produktu ma REALNIE pokazywać wybór — sam zapis przez POST
     * przechodziłby także wtedy, gdyby karty w formularzu w ogóle nie było.
     */
    public function test_product_form_renders_the_catalog_card(): void
    {
        [$owner, $shop] = $this->sklep();
        $this->wezel($shop, 'kind', 'Kamień');
        $wlochy = $this->wezel($shop, 'geo', 'Włochy');
        $this->wezel($shop, 'geo', 'Rzym', $wlochy);

        $this->actingAs($owner)
            ->get(route('seller.products.create'))
            ->assertOk()
            ->assertSee('name="categories[kind]"', false)
            ->assertSee('name="categories[geo][]"', false)
            ->assertSee('Kamień')
            ->assertSee('Rzym');
    }

    public function test_product_form_points_to_an_empty_axis(): void
    {
        [$owner] = $this->sklep();

        $this->actingAs($owner)
            ->get(route('seller.products.create'))
            ->assertOk()
            ->assertSee('Ten podział jest pusty');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Magnes Rzym',
            'price_gross' => '19,99',
            'vat_rate' => '23',
            'sale_unit' => 'piece',
            'track_stock' => '1',
            'stock' => '10',
            'is_active' => '1',
        ], $overrides);
    }
}
