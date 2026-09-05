<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);

        return [$seller, $shop];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kubek ceramiczny',
            'description' => 'Ręcznie robiony.',
            'price_gross' => '49,99',
            'vat_rate' => '23',
            'sale_unit' => 'piece',
            'track_stock' => '1',
            'stock' => '10',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_seller_can_view_products_list(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)->get(route('seller.products.index'))->assertOk();
    }

    public function test_products_list_shows_real_package_name_not_free(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->get(route('seller.products.index'))
            ->assertSee('pakiecie Kram')
            ->assertDontSee('pakiecie Free');
    }

    public function test_new_product_form_prefills_shop_default_vat(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id, 'default_vat_rate' => '8']);

        $this->actingAs($seller)
            ->get(route('seller.products.create'))
            ->assertOk()
            ->assertSee('value="8" selected', false);
    }

    public function test_new_product_form_prefills_shop_default_sale_unit(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id, 'default_sale_unit' => 'weight']);

        $this->actingAs($seller)
            ->get(route('seller.products.create'))
            ->assertOk()
            ->assertSee('value="weight" selected', false);
    }

    public function test_seller_can_create_weight_product_with_fractional_stock(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload(['sale_unit' => 'weight', 'stock' => '2,5']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->id,
            'sale_unit' => 'weight',
            'stock' => 2.5,
        ]);
    }

    public function test_piece_stock_is_rounded_to_integer(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload(['sale_unit' => 'piece', 'stock' => '7,6']))
            ->assertSessionHas('success');

        $this->assertSame('8.00', $shop->products()->firstOrFail()->stock);
    }

    public function test_invalid_sale_unit_is_rejected(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload(['sale_unit' => 'kilo']))
            ->assertSessionHasErrors('sale_unit');
    }

    public function test_seller_can_create_product_with_normalised_price(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $response = $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload());

        $product = $shop->products()->firstOrFail();
        $response->assertRedirect(route('seller.products.edit', $product))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->id,
            'name' => 'Kubek ceramiczny',
            'price_gross' => '49.99',
            'vat_rate' => '23',
            'stock' => 10,
            'is_active' => true,
        ]);
    }

    public function test_creates_product_with_images(): void
    {
        Storage::fake('public');
        [$seller, $shop] = $this->sellerWithShop();

        $response = $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload([
                'images' => [
                    UploadedFile::fake()->image('a.jpg', 800, 600),
                    UploadedFile::fake()->image('b.jpg', 800, 600),
                ],
            ]));

        $product = $shop->products()->first();
        $response->assertRedirect(route('seller.products.edit', $product));
        $this->assertSame(2, $product->images()->count());
        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_create_rejects_more_images_than_the_limit(): void
    {
        Storage::fake('public');
        [$seller] = $this->sellerWithShop();

        // Próg z konfiguracji, nie z liczby w kodzie — sklep dedykowany podnosi
        // go w `.env`, a test ma sprawdzać REGUŁĘ, nie akurat wpisaną wartość.
        config()->set('shop.product_images.max_per_product', 3);
        $limit = (int) config('shop.product_images.max_per_product');

        $images = [];
        for ($i = 0; $i <= $limit; $i++) {
            $images[] = UploadedFile::fake()->image("p{$i}.jpg", 400, 400);
        }

        $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload(['images' => $images]))
            ->assertSessionHasErrors('images');
    }

    public function test_product_description_html_is_sanitised(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)->post(route('seller.products.store'), $this->payload([
            'description' => '<strong>Solidny</strong><script>alert(1)</script>',
        ]));

        $this->assertSame('<strong>Solidny</strong>', $shop->products()->first()->description);
    }

    public function test_stock_is_nulled_when_tracking_disabled(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)->post(route('seller.products.store'), $this->payload([
            'track_stock' => null,
            'stock' => '5',
        ]));

        $this->assertDatabaseHas('products', ['shop_id' => $shop->id, 'track_stock' => false, 'stock' => null]);
    }

    public function test_package_limits_number_of_products(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $max = (int) $shop->entitlement('max_products');
        Product::factory()->count($max)->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload())
            ->assertSessionHas('error');

        $this->assertSame($max, $shop->products()->count());
    }

    public function test_seller_cannot_edit_another_shops_product(): void
    {
        [$seller] = $this->sellerWithShop();
        $otherProduct = Product::factory()->create();

        $this->actingAs($seller)->get(route('seller.products.edit', $otherProduct))->assertForbidden();
    }

    public function test_deleting_unordered_product_purges_it_completely(): void
    {
        Storage::fake('public');
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        // Zdjęcie z plikiem na dysku. Historia cen powstaje przez observer przy create.
        Storage::disk('public')->put('products/purge.webp', 'x');
        $image = $product->images()->create(['path' => 'products/purge.webp', 'position' => 0]);
        $this->assertDatabaseHas('product_price_history', ['product_id' => $product->id]);

        $this->actingAs($seller)
            ->post(route('seller.products.destroy', $product))
            ->assertRedirect(route('seller.products.index'));

        // Produkt niezamówiony znika CAŁKOWICIE — rekord, zdjęcia, historia cen i plik.
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        $this->assertDatabaseMissing('product_price_history', ['product_id' => $product->id]);
        Storage::disk('public')->assertMissing('products/purge.webp');
    }

    public function test_deleting_product_hides_the_shop_when_it_was_the_only_active_one(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true]);
        $shop->refresh();
        $this->assertTrue($shop->isVisible());

        $this->actingAs($seller)->post(route('seller.products.destroy', $product));

        // Auto-widoczność: 0 aktywnych produktów → sklep wraca do szkicu.
        $this->assertFalse($shop->fresh()->isVisible());
    }

    public function test_promoting_within_homepage_limit_succeeds(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Product::factory()->count(config('shop.homepage_promoted_limit') - 1)
            ->create(['shop_id' => $shop->id, 'show_on_homepage' => true]);

        $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload([
                'name' => 'Ostatni wyróżniony',
                'show_on_homepage' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            $shop->products()->where('name', 'Ostatni wyróżniony')->where('show_on_homepage', true)->exists(),
        );
    }

    public function test_cannot_promote_more_than_homepage_limit(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $limit = config('shop.homepage_promoted_limit');
        Product::factory()->count($limit)->create(['shop_id' => $shop->id, 'show_on_homepage' => true]);

        $this->actingAs($seller)
            ->post(route('seller.products.store'), $this->payload([
                'name' => 'Za dużo wyróżniony',
                'show_on_homepage' => '1',
            ]))
            ->assertSessionHasErrors('show_on_homepage');

        // Walidacja zablokowała zapis — liczba wyróżnionych bez zmian.
        $this->assertSame($limit, $shop->products()->where('show_on_homepage', true)->count());
    }

    public function test_already_promoted_product_can_be_saved_when_at_limit(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $limit = config('shop.homepage_promoted_limit');
        $target = Product::factory()->count($limit)
            ->create(['shop_id' => $shop->id, 'show_on_homepage' => true])
            ->first();

        // Ponowny zapis już-wyróżnionego przy pełnym limicie — pomija sam siebie.
        $this->actingAs($seller)
            ->post(route('seller.products.update', $target), $this->payload([
                'name' => $target->name,
                'show_on_homepage' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->show_on_homepage);
    }

    public function test_list_filters_by_price_range(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Tani kubek', 'price_gross' => '10.00']);
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Sredni kubek', 'price_gross' => '50.00']);
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Drogi kubek', 'price_gross' => '200.00']);

        $this->actingAs($seller)
            ->get(route('seller.products.index', ['cena_od' => '20', 'cena_do' => '100']))
            ->assertOk()
            ->assertSee('Sredni kubek')
            ->assertDontSee('Tani kubek')
            ->assertDontSee('Drogi kubek');
    }

    public function test_list_filters_by_search_in_name_and_description(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Bukiet ruzowy', 'description' => 'Nic']);
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Wazon', 'description' => 'Do bukietu']);
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Doniczka', 'description' => 'Ceramika']);

        $this->actingAs($seller)
            ->get(route('seller.products.index', ['szukaj' => 'bukiet']))
            ->assertOk()
            ->assertSee('Bukiet ruzowy')
            ->assertSee('Wazon')
            ->assertDontSee('Doniczka');
    }

    public function test_list_filters_by_tag(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $tagged = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Z tagiem']);
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Bez tagu']);
        $tagged->tags()->attach($shop->tags()->create(['name' => 'promocja', 'slug' => 'promocja'])->id);

        $this->actingAs($seller)
            ->get(route('seller.products.index', ['tag' => 'promocja']))
            ->assertOk()
            ->assertSee('Z tagiem')
            ->assertDontSee('Bez tagu');
    }

    public function test_list_sorts_by_name(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Zebra']);
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Antylopa']);

        $response = $this->actingAs($seller)
            ->get(route('seller.products.index', ['sortowanie' => 'nazwa']))
            ->assertOk();

        $this->assertLessThan(
            strpos($response->getContent(), 'Zebra'),
            strpos($response->getContent(), 'Antylopa'),
            'Sortowanie po nazwie powinno ustawić Antylopę przed Zebrą.'
        );
    }

    public function test_active_filter_resets_pagination_to_first_page(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Product::factory()->count(15)->create(['shop_id' => $shop->id, 'price_gross' => '10.00']);

        // Strona 2 istnieje bez filtra (15 > 12 na stronę)...
        $this->actingAs($seller)
            ->get(route('seller.products.index', ['page' => 2]))
            ->assertOk();

        // ...ale wąski filtr cenowy daje 1 wynik — formularz GET nie niesie `page`,
        // więc lądujemy na stronie 1 i widzimy wynik, nie pustkę „poza zakresem".
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Jedyny drogi', 'price_gross' => '500.00']);

        $this->actingAs($seller)
            ->get(route('seller.products.index', ['cena_od' => '400']))
            ->assertOk()
            ->assertSee('Jedyny drogi');
    }

    public function test_filtered_empty_result_shows_hint_not_true_empty_state(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Product::factory()->create(['shop_id' => $shop->id, 'price_gross' => '10.00']);

        $this->actingAs($seller)
            ->get(route('seller.products.index', ['szukaj' => 'czegos-czego-nie-ma-xyz']))
            ->assertOk()
            ->assertSee('Brak produktów pasujących do filtrów')
            ->assertDontSee('Nie masz jeszcze produktów');
    }

    public function test_edit_form_carries_list_filter_context(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        // Wejście w edycję z kontekstu przefiltrowanej listy — formularz (akcja zapisu
        // i „Wróć do listy") musi ten kontekst nieść dalej.
        $this->actingAs($seller)
            ->get(route('seller.products.edit', [
                'product' => $product,
                'szukaj' => 'kubek',
                'sortowanie' => 'nazwa',
                'page' => 2,
            ]))
            ->assertOk()
            ->assertSee('szukaj=kubek', false)
            ->assertSee('sortowanie=nazwa', false)
            ->assertSee('page=2', false);
    }

    public function test_update_redirects_back_to_filtered_list_context(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)
            ->post(
                route('seller.products.update', [
                    'product' => $product,
                    'szukaj' => 'kubek',
                    'page' => 3,
                ]),
                $this->payload(['name' => $product->name])
            )
            ->assertRedirect(route('seller.products.edit', [
                'product' => $product,
                'szukaj' => 'kubek',
                'page' => 3,
            ]));
    }
}
