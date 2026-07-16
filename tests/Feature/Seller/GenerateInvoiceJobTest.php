<?php

namespace Tests\Feature\Seller;

use App\Enums\InvoiceStatus;
use App\Enums\IntegrationType;
use App\Jobs\GenerateInvoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Enums\SaleUnit;
use App\Enums\VatRate;
use App\Services\FakturowniaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Job wystawiający FV w tle: zapisuje ślad faktury po sukcesie, znaczy `failed`
 * po błędzie API, jest idempotentny (gdy FV już jest — nie strzela do API).
 * Wszystko na Http::fake() — realnej Fakturowni nie dotyka.
 */
class GenerateInvoiceJobTest extends TestCase
{
    use RefreshDatabase;

    private function orderReadyForInvoice(): Order
    {
        $shop = Shop::factory()->create();
        $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://sklep.fakturownia.pl', 'api_token' => 'SECRET'],
        ]);

        $order = Order::factory()->for($shop)->create(['invoice_status' => InvoiceStatus::Pending]);
        OrderItem::factory()->for($order)->create([
            'name' => 'Bukiet',
            'unit_price_gross' => 100,
            'vat_rate' => VatRate::R23,
            'quantity' => 1,
            'sale_unit' => SaleUnit::Piece,
            'line_total_gross' => 100,
        ]);

        return $order->fresh();
    }

    public function test_successful_generation_saves_invoice_trace_and_clears_status(): void
    {
        Http::fake([
            '*/invoices.json' => Http::response([
                'id' => 555, 'number' => '7/2026', 'token' => 'tok-abc',
                'view_url' => 'https://sklep.fakturownia.pl/invoice/tok-abc',
            ], 201),
        ]);

        $order = $this->orderReadyForInvoice();

        (new GenerateInvoice($order))->handle(app(FakturowniaService::class));

        $order->refresh();
        $this->assertSame(555, (int) $order->invoice_id);
        $this->assertSame('7/2026', $order->invoice_number);
        $this->assertSame('tok-abc', $order->invoice_token);
        $this->assertNotNull($order->invoiced_at);
        $this->assertNull($order->invoice_status);
        $this->assertTrue($order->hasInvoice());
    }

    public function test_api_error_marks_failed_without_invoice_id(): void
    {
        Http::fake(['*/invoices.json' => Http::response(['error' => 'nope'], 422)]);

        $order = $this->orderReadyForInvoice();

        (new GenerateInvoice($order))->handle(app(FakturowniaService::class));

        $order->refresh();
        $this->assertNull($order->invoice_id);
        $this->assertSame(InvoiceStatus::Failed, $order->invoice_status);
        $this->assertTrue($order->invoiceFailed());
    }

    public function test_job_is_idempotent_when_invoice_already_exists(): void
    {
        Http::fake();

        $order = $this->orderReadyForInvoice();
        $order->forceFill(['invoice_id' => 999, 'invoice_status' => null])->save();

        (new GenerateInvoice($order->fresh()))->handle(app(FakturowniaService::class));

        $this->assertSame(999, (int) $order->fresh()->invoice_id);
        Http::assertNothingSent();
    }
}
