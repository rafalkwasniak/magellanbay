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
    private function sellerWithShop(array $shopAttributes = [], bool $invoicing = false, bool $onlinePayments = false, bool $gaAnalytics = false): array
    {
        $seller = User::factory()->consented()->create();
        $factory = Shop::factory();
        if ($invoicing) {
            $factory = $factory->withInvoicing();
        }
        if ($onlinePayments) {
            $factory = $factory->withOnlinePayments();
        }
        if ($gaAnalytics) {
            $factory = $factory->withGaAnalytics();
        }
        $shop = $factory->create(array_merge(['owner_id' => $seller->id], $shopAttributes));

        return [$seller, $shop];
    }

    public function test_seller_can_view_integrations_page(): void
    {
        [$seller] = $this->sellerWithShop(gaAnalytics: true);

        $this->actingAs($seller)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertSee('Google Analytics')
            ->assertSee('Identyfikator śledzenia');
    }

    public function test_seller_can_configure_ga4_id_and_it_is_enabled_by_default(): void
    {
        [$seller, $shop] = $this->sellerWithShop(gaAnalytics: true);

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
        [$seller, $shop] = $this->sellerWithShop(gaAnalytics: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => 'GTM-ABCD12'])
            ->assertSessionHas('success');

        $this->assertSame('GTM-ABCD12', $shop->fresh()->googleAnalyticsId());
    }

    public function test_id_is_trimmed_and_uppercased(): void
    {
        [$seller, $shop] = $this->sellerWithShop(gaAnalytics: true);

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
        [$seller, $shop] = $this->sellerWithShop(gaAnalytics: true);
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
        [$seller, $shop] = $this->sellerWithShop(gaAnalytics: true);
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

    public function test_integrations_page_shows_fakturownia_card(): void
    {
        [$seller] = $this->sellerWithShop(invoicing: true);

        $this->actingAs($seller)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertSee('Fakturownia')
            ->assertSee('Token API');
    }

    public function test_seller_can_configure_fakturownia_and_url_is_normalized(): void
    {
        [$seller, $shop] = $this->sellerWithShop(invoicing: true);

        // Bez schematu i z końcowym ukośnikiem — normalizujemy do https:// bez „/".
        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'fakturownia_url' => 'twojadomena.fakturownia.pl/',
                'fakturownia_token' => 'SECRET-TOKEN-123',
            ])
            ->assertRedirect(route('seller.integrations.edit'))
            ->assertSessionHas('success');

        $integration = $shop->integrations()->where('type', IntegrationType::Invoicing)->first();

        $this->assertNotNull($integration);
        $this->assertTrue($integration->enabled);
        $this->assertSame('https://twojadomena.fakturownia.pl', $integration->config['account_url']);
        $this->assertSame('SECRET-TOKEN-123', $integration->config['api_token']);
        $this->assertTrue($shop->fresh()->invoicingConfigured());
    }

    public function test_fakturownia_url_without_token_is_rejected_when_not_yet_configured(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'fakturownia_url' => 'https://twojadomena.fakturownia.pl',
                'fakturownia_token' => '',
            ])
            ->assertSessionHasErrors('fakturownia_token');

        $this->assertSame(0, ShopIntegration::where('type', IntegrationType::Invoicing)->count());
    }

    public function test_blank_token_on_resave_keeps_stored_token(): void
    {
        [$seller, $shop] = $this->sellerWithShop(invoicing: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://old.fakturownia.pl', 'api_token' => 'KEEP-ME'],
        ]);

        // Zmiana samego adresu; puste pole tokenu = „zostaw dotychczasowy".
        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'fakturownia_url' => 'https://new.fakturownia.pl',
                'fakturownia_token' => '',
            ])
            ->assertSessionHasNoErrors();

        $config = $shop->integrations()->where('type', IntegrationType::Invoicing)->first()->config;
        $this->assertSame('https://new.fakturownia.pl', $config['account_url']);
        $this->assertSame('KEEP-ME', $config['api_token']);
    }

    public function test_clearing_fakturownia_url_removes_integration(): void
    {
        [$seller, $shop] = $this->sellerWithShop(invoicing: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://x.fakturownia.pl', 'api_token' => 'T'],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['fakturownia_url' => '', 'fakturownia_token' => '']);

        $this->assertSame(0, ShopIntegration::where('type', IntegrationType::Invoicing)->count());
    }

    public function test_settings_toggle_enables_and_disables_configured_fakturownia(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_vat_rate' => '23']);
        $integration = $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://x.fakturownia.pl', 'api_token' => 'T'],
        ]);

        // Wyłączenie: checkbox odznaczony = pole nieobecne w POST.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23'])
            ->assertSessionHas('success');

        $this->assertFalse($integration->fresh()->enabled);
        $this->assertFalse($shop->fresh()->invoicingEnabled());

        // Włączenie z powrotem.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'fakturownia_enabled' => '1',
            ]);

        $this->assertTrue($integration->fresh()->enabled);
        $this->assertTrue($shop->fresh()->invoicingEnabled());
    }

    public function test_fakturownia_hidden_and_not_saved_without_entitlement(): void
    {
        // Sklep z pakietu bez uprawnienia do faktur (na wypadek przyszłej decyzji
        // o płatności) — karta nie renderuje się, a zapis jest ignorowany.
        [$seller, $shop] = $this->sellerWithShop(['entitlements' => ['invoices' => false]]);

        $this->actingAs($seller)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertDontSee('Token API');

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'fakturownia_url' => 'https://x.fakturownia.pl',
                'fakturownia_token' => 'SECRET',
            ]);

        $this->assertSame(0, ShopIntegration::where('type', IntegrationType::Invoicing)->count());
    }

    // --- Paynow (płatności online) — bliźniak Fakturowni, bramkowany `online_payments` ---

    public function test_paynow_card_is_gated_by_entitlement(): void
    {
        // Bez uprawnienia `online_payments` (Kram) — karta Paynow się nie renderuje.
        [$sellerFree] = $this->sellerWithShop();
        $this->actingAs($sellerFree)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertDontSee('Płatności online (Paynow)');

        // Z uprawnieniem (Stragan+) — karta widoczna z kluczami i adresem webhooka.
        [$sellerPaid] = $this->sellerWithShop(onlinePayments: true);
        $this->actingAs($sellerPaid)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertSee('Płatności online (Paynow)')
            ->assertSee('Klucz obliczania podpisu')
            ->assertSee('/platnosci/paynow/webhook')
            ->assertSee('Adres powiadomień (webhook)');
    }

    public function test_seller_can_configure_paynow_and_it_is_enabled_by_default(): void
    {
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'paynow_api_key' => '14d59738-4b18-4c83-86ea-131320c3d337',
                'paynow_signature_key' => '57b7a81d-4f0f-4c2a-bf44-0234fd916f8a',
                'paynow_sandbox' => '1',
            ])
            ->assertRedirect(route('seller.integrations.edit'))
            ->assertSessionHas('success');

        $integration = $shop->integrations()->where('type', IntegrationType::Payments)->first();
        $this->assertNotNull($integration);
        $this->assertTrue($integration->enabled);
        $this->assertSame('14d59738-4b18-4c83-86ea-131320c3d337', $integration->config['api_key']);
        $this->assertSame('57b7a81d-4f0f-4c2a-bf44-0234fd916f8a', $integration->config['signature_key']);
        $this->assertSame('sandbox', $integration->config['environment']);
        $this->assertTrue($shop->fresh()->onlinePaymentsEnabled());
    }

    public function test_auto_invoice_checkbox_lives_in_settings_not_integrations(): void
    {
        // Przenosiny: decyzja o auto-FV widnieje w Ustawieniach (pod Paynow), a nie
        // w Integracjach (gdzie są tylko dane połączenia z zewnętrznymi narzędziami).
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true, invoicing: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'API', 'signature_key' => 'SIGN', 'environment' => 'sandbox'],
        ]);

        $this->actingAs($seller)
            ->get(route('seller.settings.edit'))
            ->assertOk()
            ->assertSee('Wystaw fakturę VAT automatycznie po opłaceniu');

        $this->actingAs($seller)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertDontSee('Wystaw fakturę VAT automatycznie po opłaceniu');
    }

    public function test_paynow_auto_invoice_flag_is_saved_from_settings(): void
    {
        // Auto-FV to decyzja systemowa (nie dane połączenia) — mieszka w Ustawieniach,
        // wcięta pod włącznikiem Paynow. Zapisuje się do configu integracji płatności.
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true, invoicing: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'API', 'signature_key' => 'SIGN', 'environment' => 'sandbox'],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'paynow_enabled' => '1',
                'paynow_auto_invoice' => '1',
            ])
            ->assertRedirect(route('seller.settings.edit'))
            ->assertSessionHas('success');

        $config = $shop->integrations()->where('type', IntegrationType::Payments)->first()->config;
        $this->assertTrue($config['auto_invoice']);
        $this->assertTrue($shop->fresh()->autoInvoiceAfterPayment());
        // Klucze nietknięte — merge, nie nadpisanie.
        $this->assertSame('API', $config['api_key']);
        $this->assertSame('SIGN', $config['signature_key']);
    }

    public function test_saving_paynow_keys_preserves_existing_auto_invoice_flag(): void
    {
        // Flaga auto-FV żyje w Ustawieniach; ponowny zapis kluczy w Integracjach nie
        // może jej skasować (config przepisujemy 1:1 z zachowaniem `auto_invoice`).
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'OLD', 'signature_key' => 'SIGN', 'environment' => 'sandbox', 'auto_invoice' => true],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'paynow_api_key' => '14d59738-4b18-4c83-86ea-131320c3d337',
                'paynow_signature_key' => '57b7a81d-4f0f-4c2a-bf44-0234fd916f8a',
                'paynow_sandbox' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertTrue($shop->fresh()->autoInvoiceAfterPayment());
    }


    public function test_paynow_api_key_without_signature_is_rejected_when_not_yet_configured(): void
    {
        [$seller] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'paynow_api_key' => '14d59738-4b18-4c83-86ea-131320c3d337',
                'paynow_sandbox' => '1',
            ])
            ->assertSessionHasErrors('paynow_signature_key');

        $this->assertSame(0, ShopIntegration::where('type', IntegrationType::Payments)->count());
    }

    public function test_blank_signature_on_resave_keeps_stored_signature(): void
    {
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'OLD-KEY', 'signature_key' => 'KEEP-ME', 'environment' => 'sandbox'],
        ]);

        // Klucz API zmieniony, pole podpisu puste — sekret zostaje bez zmian.
        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'paynow_api_key' => 'NEW-KEY',
                'paynow_signature_key' => '',
                'paynow_sandbox' => '0',
            ]);

        $config = $shop->integrations()->where('type', IntegrationType::Payments)->first()->config;
        $this->assertSame('NEW-KEY', $config['api_key']);
        $this->assertSame('KEEP-ME', $config['signature_key']);
        $this->assertSame('production', $config['environment']);
    }

    public function test_clearing_paynow_api_key_removes_integration(): void
    {
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'K', 'signature_key' => 'S', 'environment' => 'sandbox'],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'paynow_api_key' => '',
                'paynow_signature_key' => '',
                'paynow_sandbox' => '1',
            ]);

        $this->assertSame(0, ShopIntegration::where('type', IntegrationType::Payments)->count());
    }

    public function test_settings_toggle_enables_and_disables_configured_paynow(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_vat_rate' => '23'], onlinePayments: true);
        $integration = $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'K', 'signature_key' => 'S', 'environment' => 'sandbox'],
        ]);

        // Wyłączenie: checkbox odznaczony = pole nieobecne w POST.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23'])
            ->assertSessionHas('success');

        $this->assertFalse($integration->fresh()->enabled);
        $this->assertFalse($shop->fresh()->onlinePaymentsEnabled());

        // Włączenie z powrotem.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'paynow_enabled' => '1',
            ]);

        $this->assertTrue($integration->fresh()->enabled);
        $this->assertTrue($shop->fresh()->onlinePaymentsEnabled());
    }

    public function test_integrations_page_shows_empty_state_for_free_package(): void
    {
        // Kram (bez płatnych integracji) — zamiast kart widzi upsell, bez przycisku zapisu.
        [$seller] = $this->sellerWithShop(); // domyślny Kram

        $this->actingAs($seller)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertSee('Integracje w wyższych pakietach')
            ->assertDontSee('Klucz obliczania podpisu')   // pole karty Paynow
            ->assertDontSee('Identyfikator śledzenia')     // pole karty GA
            ->assertDontSee('Token API')                   // pole karty Fakturowni
            ->assertDontSee('Zapisz integracje');
    }
}
