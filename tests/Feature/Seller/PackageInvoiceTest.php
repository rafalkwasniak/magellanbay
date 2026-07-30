<?php

namespace Tests\Feature\Seller;

use App\Jobs\GeneratePackageInvoice;
use App\Models\EmailMessage;
use App\Models\PackagePayment;
use App\Models\Shop;
use App\Models\User;
use App\Services\FakturowniaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Faktura KRAMIO za pakiet (my → sprzedawca), z konta PLATFORMY. Nabywcą jest
 * sprzedawca: z NIP-em faktura firmowa, bez niego imienna. Wystawiana raz
 * (garda `invoice_id`), w tle, z mailem zawierającym link do PDF.
 */
class PackageInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fakturownia.url' => 'https://kramio.fakturownia.pl',
            'services.fakturownia.token' => 'platform-token',
        ]);
    }

    /**
     * Pola `invoice_*` i `applied_at` NIE są mass-assignable (ustawia je job
     * i webhook), więc trafiają przez `forceFill` — inaczej `create()` cicho je
     * pomija i test sprawdza coś innego, niż zamierzał.
     */
    private function paymentFor(Shop $shop, array $attributes = []): PackagePayment
    {
        $mass = ['target_package', 'amount', 'credit', 'new_ends_at', 'status', 'payment_id'];

        $payment = $shop->packagePayments()->create(array_intersect_key([
            'target_package' => 'booth',
            'amount' => 750,
            'credit' => 0,
            'new_ends_at' => now()->addYear(),
            'status' => PackagePayment::STATUS_PAID,
            'payment_id' => 'PAY-1',
            ...$attributes,
        ], array_flip($mass)));

        $forced = array_diff_key($attributes, array_flip($mass));

        if ($forced !== []) {
            $payment->forceFill($forced)->save();
        }

        return $payment;
    }

    private function fakeFakturownia(): void
    {
        Http::fake(['*/invoices.json' => Http::response([
            'id' => 9001,
            'number' => 'FV 7/2026',
            'token' => 'tok123',
        ])]);
    }

    public function test_company_invoice_uses_shop_company_data(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->withInvoiceData()->create(['owner_id' => $seller->id]);

        $payload = app(FakturowniaService::class)->buildPackageInvoicePayload($this->paymentFor($shop))['invoice'];

        $this->assertSame('Kwiaciarnia Anna Kowalska', $payload['buyer_name']);
        $this->assertSame('1234563218', $payload['buyer_tax_no']);
        $this->assertSame('Polna 7', $payload['buyer_street']);
        $this->assertSame('00-001', $payload['buyer_post_code']);
        $this->assertSame('Warszawa', $payload['buyer_city']);
        // Jedna pozycja usługowa, kwota brutto z migawki, VAT 23%.
        $this->assertSame(750.0, $payload['positions'][0]['total_price_gross']);
        $this->assertSame(23, $payload['positions'][0]['tax']);
        $this->assertStringContainsString('pakiet Stragan', $payload['positions'][0]['name']);
    }

    public function test_personal_invoice_when_there_is_no_company(): void
    {
        $seller = User::factory()->consented()->create(['name' => 'Anna', 'surname' => 'Kowalska']);
        $shop = Shop::factory()->withPersonalInvoiceData()->create(['owner_id' => $seller->id]);

        $payload = app(FakturowniaService::class)->buildPackageInvoicePayload($this->paymentFor($shop))['invoice'];

        // Bez NIP-u: faktura imienna na właściciela konta, pole podatkowe odpada.
        $this->assertSame('Anna Kowalska', $payload['buyer_name']);
        $this->assertArrayNotHasKey('buyer_tax_no', $payload);
    }

    public function test_discount_is_noted_on_the_invoice(): void
    {
        $shop = Shop::factory()->withInvoiceData()->create(['owner_id' => User::factory()->consented()->create()->id]);
        $payment = $this->paymentFor($shop, ['amount' => 1370, 'credit' => 129.45, 'target_package' => 'pavilion']);

        $payload = app(FakturowniaService::class)->buildPackageInvoicePayload($payment)['invoice'];

        // Bez adnotacji nie dałoby się odtworzyć, skąd kwota niższa od cennikowej.
        $this->assertStringContainsString('129,45', $payload['description']);
    }

    public function test_job_saves_the_invoice_trace_and_mails_the_pdf(): void
    {
        $this->fakeFakturownia();
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->withInvoiceData()->create(['owner_id' => $seller->id]);
        $payment = $this->paymentFor($shop);

        EmailMessage::query()->delete();
        (new GeneratePackageInvoice($payment))->handle(app(FakturowniaService::class), app(\App\Services\PackagePaymentService::class));

        $payment->refresh();
        $this->assertSame('9001', $payment->invoice_id);
        $this->assertSame('FV 7/2026', $payment->invoice_number);
        $this->assertSame('https://kramio.fakturownia.pl/invoice/tok123.pdf', $payment->invoicePdfUrl());

        $mail = EmailMessage::where('to_email', $seller->email)->firstOrFail();
        $this->assertStringContainsString('Faktura nr FV 7/2026', $mail->subject);
        $this->assertSame('https://kramio.fakturownia.pl/invoice/tok123.pdf', $mail->action_url);
    }

    public function test_invoice_is_issued_only_once(): void
    {
        $this->fakeFakturownia();
        $shop = Shop::factory()->withInvoiceData()->create(['owner_id' => User::factory()->consented()->create()->id]);
        $payment = $this->paymentFor($shop, ['invoice_id' => '9001']);

        (new GeneratePackageInvoice($payment))->handle(app(FakturowniaService::class), app(\App\Services\PackagePaymentService::class));

        // Garda `invoice_id`: żadnego drugiego dokumentu (realne FV idą do KSeF).
        Http::assertNothingSent();
    }

    public function test_missing_platform_config_skips_quietly(): void
    {
        config(['services.fakturownia.token' => null]);
        $shop = Shop::factory()->withInvoiceData()->create(['owner_id' => User::factory()->consented()->create()->id]);
        $payment = $this->paymentFor($shop);

        $this->assertNull(app(FakturowniaService::class)->createPackageInvoice($payment));
    }

    public function test_history_links_to_the_invoice_pdf(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->withInvoiceData()->create([
            'owner_id' => $seller->id,
            'package' => 'booth',
            'subscription_ends_at' => now()->addYear(),
        ]);
        $payment = $this->paymentFor($shop, [
            'invoice_id' => '9001',
            'invoice_number' => 'FV 7/2026',
            'invoice_token' => 'tok123',
            'invoiced_at' => now(),
            'applied_at' => now(),
        ]);

        // Historia czyta LOG ZMIAN pakietu, nie same płatności — więc test musi
        // odwzorować produkcję i zapisać wpis powiązany z opłatą.
        $shop->recordPackageChange(\App\Models\PackageChange::SOURCE_PAYMENT, $payment);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('faktura FV 7/2026')
            ->assertSee('invoice/tok123.pdf', false);
    }
}
