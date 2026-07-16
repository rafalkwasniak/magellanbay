<?php

namespace Tests\Feature\Seller;

use App\Enums\IntegrationType;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gardy faktury na modelu Order: czy FV już jest (idempotencja), publiczny link
 * do PDF oraz pełna bramka „czy można wystawić" (uprawnienie + włączona i
 * skonfigurowana Fakturownia + brak wcześniejszej FV). Bez progu statusu —
 * sprzedawca decyduje sam.
 */
class OrderInvoicingTest extends TestCase
{
    use RefreshDatabase;

    private function shopWithFakturownia(bool $enabled = true, array $shopAttributes = []): Shop
    {
        $shop = Shop::factory()->create($shopAttributes);
        $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => $enabled,
            'config' => ['account_url' => 'https://sklep.fakturownia.pl', 'api_token' => 'SECRET'],
        ]);

        return $shop->fresh();
    }

    public function test_has_invoice_reflects_invoice_id(): void
    {
        $order = Order::factory()->create();
        $this->assertFalse($order->hasInvoice());

        $order->forceFill(['invoice_id' => 987])->save();
        $this->assertTrue($order->fresh()->hasInvoice());
    }

    public function test_invoice_pdf_url_is_built_from_token_and_account(): void
    {
        $shop = $this->shopWithFakturownia();
        $order = Order::factory()->for($shop)->create();
        $order->forceFill(['invoice_id' => 987, 'invoice_token' => 'pub-token'])->save();

        $this->assertSame(
            'https://sklep.fakturownia.pl/invoice/pub-token.pdf',
            $order->fresh()->invoicePdfUrl(),
        );
    }

    public function test_invoice_pdf_url_is_null_without_token(): void
    {
        $shop = $this->shopWithFakturownia();
        $order = Order::factory()->for($shop)->create();

        $this->assertNull($order->invoicePdfUrl());
    }

    public function test_can_be_invoiced_when_enabled_configured_and_not_yet_invoiced(): void
    {
        $shop = $this->shopWithFakturownia();
        $order = Order::factory()->for($shop)->create();

        $this->assertTrue($order->canBeInvoiced());
    }

    public function test_cannot_be_invoiced_when_already_invoiced(): void
    {
        $shop = $this->shopWithFakturownia();
        $order = Order::factory()->for($shop)->create();
        $order->forceFill(['invoice_id' => 987])->save();

        $this->assertFalse($order->fresh()->canBeInvoiced());
    }

    public function test_cannot_be_invoiced_when_integration_disabled(): void
    {
        $shop = $this->shopWithFakturownia(enabled: false);
        $order = Order::factory()->for($shop)->create();

        $this->assertFalse($order->canBeInvoiced());
    }

    public function test_cannot_be_invoiced_without_entitlement(): void
    {
        $shop = $this->shopWithFakturownia(shopAttributes: ['entitlements' => ['invoices' => false]]);
        $order = Order::factory()->for($shop)->create();

        $this->assertFalse($order->canBeInvoiced());
    }
}
