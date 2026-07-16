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

    public function test_card_text_stays_dark_regardless_of_theme(): void
    {
        // Karta maila jest zawsze biała, więc powitanie/treść na niej muszą być
        // ciemne NAWET gdy motyw sklepu jest ciemny (jego `ink` jest wtedy jasny i
        // znikał na bieli — to była zgłoszona usterka). `text` to osobne miejsce:
        // nazwa w nagłówku leży na tle motywu, więc dalej bierze tusz motywu.
        $shop = Shop::factory()->create();

        $brand = MailBranding::for($shop->id);

        $this->assertSame('#1c1917', $brand['ink_card']);
        $this->assertSame($shop->themeTokens()['ink'], $brand['text']);
    }

    public function test_heading_uses_brand_colour_darkened_to_stay_legible(): void
    {
        // Jasny „kolor przewodni" sklepu na białej karcie → przyciemniony do
        // czytelności (próg WCAG AA), zamiast zniknąć.
        $shop = Shop::factory()->create(['theme' => ['palette' => 'custom', 'brand_color' => '#FCE7A2']]);

        $brand = MailBranding::for($shop->id);

        $this->assertGreaterThanOrEqual(4.5, $this->contrastOnWhite($brand['heading']));
    }

    private function contrastOnWhite(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = array_map(static function (string $pair): float {
            $v = hexdec($pair) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, [substr($hex, 0, 2), substr($hex, 2, 2), substr($hex, 4, 2)]);

        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return (1.0 + 0.05) / ($luminance + 0.05);   // biel = jasność 1.0
    }
}
