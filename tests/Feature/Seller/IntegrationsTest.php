<?php

namespace Tests\Feature\Seller;

use App\Enums\IntegrationType;
use App\Models\Shop;
use App\Models\ShopIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integracje sklepu: konfiguracja usług (na razie Google Analytics / Tag
 * Manager) na stronie „Integracje" oraz włącznik na „Ustawieniach". Podział:
 * config = Integracje, enabled = Ustawienia; jeden wiersz shop_integrations.
 */
class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(array $shopAttributes = []): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(array_merge(['owner_id' => $seller->id], $shopAttributes));

        return [$seller, $shop];
    }

    public function test_seller_can_view_integrations_page(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertSee('Google Analytics')
            ->assertSee('Identyfikator śledzenia');
    }

    public function test_seller_can_configure_ga4_id_and_it_is_enabled_by_default(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => 'G-ABC123XYZ'])
            ->assertRedirect(route('seller.integrations.edit'))
            ->assertSessionHas('success');

        $integration = $shop->integrations()->where('type', IntegrationType::GoogleAnalytics)->first();

        $this->assertNotNull($integration);
        $this->assertTrue($integration->enabled);
        $this->assertSame('G-ABC123XYZ', $integration->config['tracking_id']);
    }

    public function test_gtm_id_is_accepted(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => 'GTM-ABCD12'])
            ->assertSessionHas('success');

        $this->assertSame('GTM-ABCD12', $shop->fresh()->googleAnalyticsId());
    }

    public function test_id_is_trimmed_and_uppercased(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => '  g-abc123  ']);

        $this->assertSame('G-ABC123', $shop->fresh()->googleAnalyticsId());
    }

    public function test_invalid_id_is_rejected(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => 'UA-12345-1'])
            ->assertSessionHasErrors('google_analytics_id');

        $this->assertNull($shop->fresh()->googleAnalyticsId());
    }

    public function test_clearing_id_removes_integration(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $shop->integrations()->create([
            'type' => IntegrationType::GoogleAnalytics,
            'enabled' => true,
            'config' => ['tracking_id' => 'G-ABC123'],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => '']);

        $this->assertSame(0, ShopIntegration::count());
    }

    public function test_reconfiguring_keeps_enabled_state(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $shop->integrations()->create([
            'type' => IntegrationType::GoogleAnalytics,
            'enabled' => false,                     // wcześniej wyłączona w Ustawieniach
            'config' => ['tracking_id' => 'G-OLD123'],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => 'G-NEW456']);

        $integration = $shop->integrations()->first();
        $this->assertSame('G-NEW456', $integration->config['tracking_id']);
        $this->assertFalse($integration->enabled, 'Zmiana ID nie powinna sama włączać usługi.');
    }

    public function test_settings_toggle_enables_and_disables_configured_ga(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_vat_rate' => '23']);
        $integration = $shop->integrations()->create([
            'type' => IntegrationType::GoogleAnalytics,
            'enabled' => true,
            'config' => ['tracking_id' => 'G-ABC123'],
        ]);

        // Wyłączenie: checkbox odznaczony = pole nieobecne w POST.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23'])
            ->assertSessionHas('success');

        $this->assertFalse($integration->fresh()->enabled);

        // Włączenie z powrotem.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'google_analytics_enabled' => '1',
            ]);

        $this->assertTrue($integration->fresh()->enabled);
    }

    public function test_settings_toggle_noop_without_configured_integration(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_vat_rate' => '23']);

        // Brak integracji: przesłanie włącznika niczego nie tworzy.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'google_analytics_enabled' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, ShopIntegration::count());
    }
}
