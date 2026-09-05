<?php

namespace Tests\Feature\Mail;

use App\Models\Shop;
use App\Support\MailBranding;
use App\Support\Mode;
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
        // Logo platformy jako absolutny URL — maile nie widzą ścieżek względnych.
        $this->assertStringStartsWith('http', $brand['logo_url']);
        // Ścieżka z configu, nie wpisana tu ponownie — inaczej test przechodziłby
        // dalej po podmianie marki, opisując logo, którego już nie ma.
        $this->assertStringContainsString(config('brand.logo'), $brand['logo_url']);
        // Gradient wycofany — paleta jest płaska.
        $this->assertArrayNotHasKey('gradient_from', $brand);
        $this->assertArrayNotHasKey('gradient_to', $brand);
    }

    /**
     * W SKLEPIE DEDYKOWANYM NIE MA MAILI „PLATFORMY".
     *
     * `system()` opisuje tożsamość operatora — barwy Kramio i dane firmowe
     * z `config/company.php`. Tam gdzie operatora nie ma, właściciel dostawałby
     * listy w bursztynie i z naszym adresem w stopce, czyli od obcej firmy,
     * o której nigdy nie słyszał. Dotyczy WSZYSTKICH maili bez `shop_id`:
     * aktywacji konta, resetu hasła i sygnału o nowym zgłoszeniu.
     */
    public function test_platform_branding_becomes_the_shop_when_dedicated(): void
    {
        config()->set('shop.mode', Mode::DEDICATED);
        $shop = Shop::factory()->create(['name' => 'Magellan Bay']);

        $brand = MailBranding::for(null);

        $this->assertSame('Magellan Bay', $brand['name']);
        $this->assertNull($brand['glyph']);              // znak ◐ jest marką Kramio
        $this->assertSame($shop->themeTokens()['brand'], $brand['brand']);
        $this->assertNotSame(config('company.email'), $brand['contact_email']);
    }

    /**
     * Pusta baza tuż po migracji, przed seederem wdrożeniowym: nie ma sklepu,
     * z którego dałoby się wziąć tożsamość. Wysyłka ma wtedy zejść na wartości
     * zapasowe, a nie się wywrócić.
     */
    public function test_dedicated_branding_falls_back_when_there_is_no_shop_yet(): void
    {
        config()->set('shop.mode', Mode::DEDICATED);

        $brand = MailBranding::for(null);

        $this->assertSame(config('app.name'), $brand['name']);
        $this->assertSame('#f59e0b', $brand['brand']);
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

    public function test_heading_uses_raw_brand_colour_like_storefront(): void
    {
        // Tytuł maila = surowy „kolor przewodni" sklepu, dokładnie jak nazwy
        // produktów i nagłówki na storefroncie (`.st-brand { color: var(--brand) }`).
        // Spójność dekoru wybrana świadomie ponad przyciemnianie do WCAG na bieli.
        $shop = Shop::factory()->create(['theme' => ['palette' => 'custom', 'brand_color' => '#FCE7A2']]);

        $brand = MailBranding::for($shop->id);

        $this->assertSame($shop->themeTokens()['brand'], $brand['heading']);
    }
}
