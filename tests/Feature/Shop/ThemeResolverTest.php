<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resolver motywu sklepu (Shop::templateSlug/templateName/themePalette/themeTokens).
 * Motyw to referencja (kolumny template + theme JSON) rozwiązywana z config/themes.php,
 * z siatką bezpieczeństwa na wycofane szablony/palety.
 */
class ThemeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_shop_uses_default_template_and_palette(): void
    {
        $shop = Shop::factory()->create();

        $this->assertSame(config('themes.default_template'), $shop->templateSlug());
        $this->assertSame('velvet_cloud', $shop->templateSlug());
        $this->assertSame('sky', $shop->themePalette());
        $this->assertSame('Aksamitna chmurka', $shop->templateName());
    }

    public function test_default_palette_tokens_are_resolved(): void
    {
        $shop = Shop::factory()->create();

        $tokens = $shop->themeTokens();

        $this->assertSame(
            ['brand', 'brand_ink', 'surface', 'ink'],
            array_keys($tokens),
        );
        $this->assertSame('#3B82F6', $tokens['brand']);
    }

    public function test_chosen_template_and_palette_win(): void
    {
        $shop = Shop::factory()->create([
            'template' => 'green_nook',
            'theme' => ['palette' => 'moss'],
        ]);

        $this->assertSame('green_nook', $shop->templateSlug());
        $this->assertSame('Zielony zakątek', $shop->templateName());
        $this->assertSame('moss', $shop->themePalette());
        $this->assertSame('#6B8E4E', $shop->themeTokens()['brand']);
    }

    public function test_listing_density_scales_with_active_product_count(): void
    {
        $shop = Shop::factory()->create();
        $addActive = fn (int $n) => Product::factory()->count($n)->create(['shop_id' => $shop->id, 'is_active' => true]);

        // Mały katalog: 3 kolumny, 9 na stronę (duże, wyraziste kafle).
        $addActive(5); // 5
        $this->assertSame(['columns' => 3, 'per_page' => 9], $shop->listingDensity());

        $addActive(23); // 28 → 3×4 = 12 (żeby zmieścić w 3 podstronach)
        $this->assertSame(['columns' => 3, 'per_page' => 12], $shop->listingDensity());

        $addActive(18); // 46 → skok na 4 kolumny, 4×4 = 16
        $this->assertSame(['columns' => 4, 'per_page' => 16], $shop->listingDensity());

        $addActive(30); // 76 → sufit 4×6 = 24 (dalej rosną tylko podstrony)
        $this->assertSame(['columns' => 4, 'per_page' => 24], $shop->listingDensity());
    }

    public function test_listing_density_ignores_hidden_products(): void
    {
        $shop = Shop::factory()->create();
        Product::factory()->count(5)->create(['shop_id' => $shop->id, 'is_active' => true]);
        Product::factory()->count(60)->create(['shop_id' => $shop->id, 'is_active' => false]);

        // Liczą się tylko aktywne (5) — ukryte nie podbijają skali.
        $this->assertSame(['columns' => 3, 'per_page' => 9], $shop->listingDensity());
    }

    public function test_unknown_template_falls_back_to_default(): void
    {
        $shop = Shop::factory()->create(['template' => 'does_not_exist']);

        $this->assertSame('velvet_cloud', $shop->templateSlug());
        $this->assertSame('sky', $shop->themePalette());
    }

    public function test_palette_not_in_template_falls_back_to_template_default(): void
    {
        // 'moss' należy do green_nook, nie do velvet_cloud → spada na domyślną 'sky'.
        $shop = Shop::factory()->create([
            'template' => 'velvet_cloud',
            'theme' => ['palette' => 'moss'],
        ]);

        $this->assertSame('sky', $shop->themePalette());
    }
}
