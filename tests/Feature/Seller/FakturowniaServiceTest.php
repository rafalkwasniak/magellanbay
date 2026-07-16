<?php

namespace Tests\Feature\Seller;

use App\Enums\DeliveryMethod;
use App\Enums\IntegrationType;
use App\Enums\SaleUnit;
use App\Enums\VatRate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Services\FakturowniaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Budowa payloadu FV (mapowanie pozycji/VAT/nabywcy) oraz wystawianie faktury
 * na sfałszowanym HTTP — całość „na sucho", bez dotykania realnej Fakturowni.
 * Kwoty pozycji to zapisany line_total_gross, więc suma FV = suma zamówienia.
 */
class FakturowniaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function shopWithFakturownia(): Shop
    {
        $shop = Shop::factory()->create();
        $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://sklep.fakturownia.pl', 'api_token' => 'SECRET-TOKEN'],
        ]);

        return $shop->fresh();
    }

    private function orderWithItems(Shop $shop, array $attributes = []): Order
    {
        $order = Order::factory()->for($shop)->create(array_merge([
            'buyer_name' => 'Anna',
            'buyer_surname' => 'Kowalska',
            'buyer_email' => 'anna@example.com',
            'delivery_method' => DeliveryMethod::Pickup,
            'delivery_cost' => 0,
        ], $attributes));

        OrderItem::factory()->for($order)->create([
            'name' => 'Bukiet róż',
            'unit_price_gross' => 100.00,
            'vat_rate' => VatRate::R23,
            'quantity' => 2,
            'sale_unit' => SaleUnit::Piece,
            'line_total_gross' => 200.00,
        ]);

        return $order->fresh();
    }

    public function test_payload_maps_positions_with_per_line_vat(): void
    {
        $shop = $this->shopWithFakturownia();
        $order = $this->orderWithItems($shop);

        // Druga pozycja ze stawką zwolnioną — sprawdzamy mapowanie „zw".
        OrderItem::factory()->for($order)->create([
            'name' => 'Usługa zwolniona',
            'unit_price_gross' => 50.00,
            'vat_rate' => VatRate::Zw,
            'quantity' => 1,
            'sale_unit' => SaleUnit::Piece,
            'line_total_gross' => 50.00,
        ]);

        $payload = app(FakturowniaService::class)->buildInvoicePayload($order->fresh());
        $invoice = $payload['invoice'];

        $this->assertSame('vat', $invoice['kind']);
        $this->assertSame('paid', $invoice['status']);
        $this->assertSame('transfer', $invoice['payment_type']);

        $this->assertCount(2, $invoice['positions']);
        $this->assertSame(['name' => 'Bukiet róż', 'tax' => 23, 'quantity' => 2.0, 'total_price_gross' => 200.0], $invoice['positions'][0]);
        $this->assertSame(['name' => 'Usługa zwolniona', 'tax' => 'zw', 'quantity' => 1.0, 'total_price_gross' => 50.0], $invoice['positions'][1]);
    }

    public function test_delivery_cost_becomes_its_own_position(): void
    {
        $shop = $this->shopWithFakturownia();
        $order = $this->orderWithItems($shop, [
            'delivery_method' => DeliveryMethod::Courier,
            'delivery_cost' => 15.00,
        ]);

        $positions = app(FakturowniaService::class)->buildInvoicePayload($order)['invoice']['positions'];

        $this->assertCount(2, $positions);
        $delivery = $positions[1];
        $this->assertSame('Dostawa: '.DeliveryMethod::Courier->label(), $delivery['name']);
        $this->assertSame(23, $delivery['tax']);
        $this->assertSame(15.0, $delivery['total_price_gross']);
    }

    public function test_free_delivery_adds_no_position(): void
    {
        $shop = $this->shopWithFakturownia();
        $order = $this->orderWithItems($shop, ['delivery_cost' => 0]);

        $positions = app(FakturowniaService::class)->buildInvoicePayload($order)['invoice']['positions'];

        $this->assertCount(1, $positions);
    }

    public function test_company_buyer_carries_nip_and_billing_address(): void
    {
        $shop = $this->shopWithFakturownia();
        $order = $this->orderWithItems($shop, [
            'is_company' => true,
            'company_name' => 'Kwiaciarnia Sp. z o.o.',
            'company_nip' => '5252445429',
            'company_street' => 'Okrzei',
            'company_building_number' => '73',
            'company_apartment_number' => '5',
            'company_postal_code' => '42-582',
            'company_city' => 'Rogoźnik',
        ]);

        $invoice = app(FakturowniaService::class)->buildInvoicePayload($order)['invoice'];

        $this->assertSame('Kwiaciarnia Sp. z o.o.', $invoice['buyer_name']);
        $this->assertSame('5252445429', $invoice['buyer_tax_no']);
        $this->assertSame('Okrzei 73/5', $invoice['buyer_street']);
        $this->assertSame('42-582', $invoice['buyer_post_code']);
        $this->assertSame('Rogoźnik', $invoice['buyer_city']);
    }

    public function test_individual_buyer_has_full_name_and_no_tax_no(): void
    {
        $shop = $this->shopWithFakturownia();
        $order = $this->orderWithItems($shop);

        $invoice = app(FakturowniaService::class)->buildInvoicePayload($order)['invoice'];

        $this->assertSame('Anna Kowalska', $invoice['buyer_name']);
        $this->assertArrayNotHasKey('buyer_tax_no', $invoice);
    }

    public function test_create_invoice_posts_to_account_and_returns_trace(): void
    {
        Http::fake([
            '*/invoices.json' => Http::response([
                'id' => 987,
                'number' => '5/2026',
                'token' => 'pub-token-xyz',
                'view_url' => 'https://sklep.fakturownia.pl/invoice/pub-token-xyz',
            ], 201),
        ]);

        $shop = $this->shopWithFakturownia();
        $order = $this->orderWithItems($shop);

        $trace = app(FakturowniaService::class)->createInvoice($order);

        $this->assertSame(987, $trace['id']);
        $this->assertSame('5/2026', $trace['number']);
        $this->assertSame('pub-token-xyz', $trace['token']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sklep.fakturownia.pl/invoices.json'
                && $request['api_token'] === 'SECRET-TOKEN'
                && $request['invoice']['kind'] === 'vat';
        });
    }

    public function test_create_invoice_returns_null_without_configuration(): void
    {
        // Sklep bez integracji Fakturowni.
        $shop = Shop::factory()->create();
        $order = $this->orderWithItems($shop);

        Http::fake();

        $this->assertNull(app(FakturowniaService::class)->createInvoice($order));
        Http::assertNothingSent();
    }

    public function test_create_invoice_returns_null_on_api_error(): void
    {
        Http::fake(['*/invoices.json' => Http::response(['error' => 'nope'], 422)]);

        $shop = $this->shopWithFakturownia();
        $order = $this->orderWithItems($shop);

        $this->assertNull(app(FakturowniaService::class)->createInvoice($order));
    }
}
