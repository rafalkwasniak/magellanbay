<?php

namespace Tests\Feature\Mail;

use App\Models\Shop;
use App\Support\MailBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_branding_is_flat_kramio(): void
    {
        $brand = MailBranding::for(null);

        $this->assertSame(config('app.name'), $brand['name']);
        $this->assertSame('◐', $brand['glyph']);
        $this->assertSame('#f59e0b', $brand['brand']);
        $this->assertNull($brand['logo_url']);
        // Gradient wycofany — paleta jest płaska.
        $this->assertArrayNotHasKey('gradient_from', $brand);
        $this->assertArrayNotHasKey('gradient_to', $brand);
    }

    public function test_shop_branding_maps_theme_name_and_no_glyph(): void
    {
        $shop = Shop::factory()->create(['name' => 'I like my bike']);
        $tokens = $shop->themeTokens();

        $brand = MailBranding::for($shop->id);

        $this->assertSame('I like my bike', $brand['name']);
        // Brak logo → sama nazwa (glyph null, żeby layout nie pokazał znaku Kramio).
        $this->assertNull($brand['glyph']);
        $this->assertNull($brand['logo_url']);
        // Kolory z motywu sklepu.
        $this->assertSame($tokens['brand'], $brand['brand']);
        $this->assertSame($tokens['brand'], $brand['accent']);
        $this->assertSame($tokens['brand_ink'], $brand['brand_ink']);
        $this->assertSame($tokens['ink'], $brand['text']);
        $this->assertSame($tokens['surface'], $brand['page_bg']);
    }

    public function test_shop_logo_becomes_absolute_url(): void
    {
        $shop = Shop::factory()->create(['logo_path' => 'shops/7/logo.png']);

        $brand = MailBranding::for($shop->id);

        $this->assertNotNull($brand['logo_url']);
        $this->assertStringStartsWith('http', $brand['logo_url']);
        $this->assertStringContainsString('shops/7/logo.png', $brand['logo_url']);
    }

    public function test_unknown_shop_falls_back_to_platform(): void
    {
        $brand = MailBranding::for(999999);

        $this->assertSame(config('app.name'), $brand['name']);
        $this->assertSame('◐', $brand['glyph']);
    }
}
