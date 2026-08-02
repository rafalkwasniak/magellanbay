<?php

namespace Tests\Feature\Storefront;

use App\Enums\IntegrationType;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wstrzyknięcie Google Analytics / Tag Managera w <head> storefrontu. Kod
 * pojawia się tylko, gdy integracja jest włączona (Ustawienia) i skonfigurowana
 * (Integracje) — efektywna bramka Shop::tracksWithGoogleAnalytics().
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function url(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain').'/';
    }

    private function activeShopWithGa(string $trackingId, bool $enabled = true): Shop
    {
        $shop = Shop::factory()->active()->withGaAnalytics()->create();
        $shop->integrations()->create([
            'type' => IntegrationType::GoogleAnalytics,
            'enabled' => $enabled,
            'config' => ['tracking_id' => $trackingId],
        ]);

        return $shop;
    }

    public function test_ga4_snippet_injected_when_enabled(): void
    {
        $shop = $this->activeShopWithGa('G-ABC123XYZ');

        $this->withUnencryptedCookie((string) config('cookies.consent.name'), 'granted')->get($this->url($shop))
            ->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id=G-ABC123XYZ', false)
            ->assertSee("gtag('config', 'G-ABC123XYZ')", false);
    }

    public function test_gtm_snippet_injected_for_tag_manager_id(): void
    {
        $shop = $this->activeShopWithGa('GTM-ABCD12');

        $this->withUnencryptedCookie((string) config('cookies.consent.name'), 'granted')->get($this->url($shop))
            ->assertOk()
            ->assertSee('googletagmanager.com/gtm.js', false)
            ->assertSee('googletagmanager.com/ns.html?id=GTM-ABCD12', false)   // noscript
            ->assertDontSee('gtag/js?id=', false);
    }

    public function test_no_snippet_when_integration_disabled(): void
    {
        $shop = $this->activeShopWithGa('G-ABC123XYZ', enabled: false);

        $this->get($this->url($shop))
            ->assertOk()
            ->assertDontSee('googletagmanager.com/gtag', false);
    }

    public function test_no_snippet_when_unconfigured(): void
    {
        $shop = Shop::factory()->active()->create();

        $this->get($this->url($shop))
            ->assertOk()
            ->assertDontSee('googletagmanager.com/gtag', false)
            ->assertDontSee('googletagmanager.com/gtm.js', false);
    }

    public function test_ga_not_injected_without_entitlement(): void
    {
        // Brama pakietu: sklep skonfigurował i włączył GA, ale bez uprawnienia
        // `ga_analytics` (Kram) skrypt NIE trafia na storefront.
        $shop = Shop::factory()->active()->create(); // domyślny Kram, brak ga_analytics
        $shop->integrations()->create([
            'type' => IntegrationType::GoogleAnalytics,
            'enabled' => true,
            'config' => ['tracking_id' => 'G-ABC123XYZ'],
        ]);

        $this->assertFalse($shop->fresh()->tracksWithGoogleAnalytics());

        $this->get($this->url($shop->fresh()))
            ->assertOk()
            ->assertDontSee('googletagmanager.com/gtag', false);
    }
}
