<?php

namespace Tests\Feature\Seller;

use App\Models\PackagePayment;
use App\Models\Shop;
use App\Models\User;
use App\Services\PaynowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Zakup pakietu online: klucze PLATFORMY (nie integracja sklepu), migawka
 * wyceny w `package_payments`, webhook z podpisem platformy stosuje zakup
 * idempotentnie, a uprawnienia są lepkie (ręczne nadania przeżywają).
 */
class PackagePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-30 12:00:00'));
        config(['services.paynow.platform' => [
            'api_key' => 'platform-api',
            'signature_key' => 'platform-sign',
            'environment' => 'sandbox',
        ]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerOn(string $package, array $attributes = []): array
    {
        $seller = User::factory()->consented()->create();
        // `withInvoiceData`: bez danych do faktury zakup jest ZABLOKOWANY, więc
        // każdy test płatności musi mieć sklep gotowy do fakturowania.
        $shop = Shop::factory()->withInvoiceData()->create([
            'owner_id' => $seller->id,
            'package' => $package,
            'entitlements' => config("shop.packages.{$package}.entitlements"),
            'price_yearly' => config("shop.packages.{$package}.price_yearly"),
            ...$attributes,
        ]);

        return [$seller, $shop];
    }

    private function fakePaynow(): void
    {
        Http::fake(['*/v1/payments' => Http::response([
            'paymentId' => 'PAY-123',
            'redirectUrl' => 'https://sandbox.paynow.pl/pay/PAY-123',
            'status' => 'NEW',
        ])]);
    }

    private function webhook(string $paymentId, string $status, ?string $key = 'platform-sign'): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['paymentId' => $paymentId, 'status' => $status], JSON_UNESCAPED_SLASHES);

        return $this->call('POST', '/platnosci/paynow/pakiety/webhook', [], [], [], [
            'HTTP_Signature' => $key !== null ? app(PaynowService::class)->sign($key, $body) : 'zly-podpis',
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_purchase_freezes_the_quote_and_redirects_to_the_gateway(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('booth', ['subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $response = $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'pavilion']));

        $response->assertRedirect('https://sandbox.paynow.pl/pay/PAY-123');

        $payment = $shop->packagePayments()->firstOrFail();
        $this->assertSame('pavilion', $payment->target_package);
        // Migawka wyceny: 1500 − (750 × 62/365 = 127,39...) → floor 1372.
        $this->assertSame('1372.00', $payment->amount);
        $this->assertSame(PackagePayment::STATUS_PENDING, $payment->status);
        $this->assertSame('PAY-123', $payment->payment_id);
        $this->assertSame('2027-07-30', $payment->new_ends_at->format('Y-m-d'));
    }

    public function test_confirmed_webhook_applies_the_package_from_the_snapshot(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));

        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $shop->refresh();
        $this->assertSame('booth', $shop->package);
        $this->assertSame(48, $shop->entitlement('max_products'));
        $this->assertTrue($shop->entitlement('online_payments'));
        $this->assertSame('2027-07-30', $shop->subscription_ends_at->format('Y-m-d'));
        $this->assertTrue($shop->packagePayments()->first()->isApplied());
    }

    public function test_manual_grants_survive_the_purchase(): void
    {
        $this->fakePaynow();
        // Kram z ręcznie nadanym mailingiem i podbitym limitem AI.
        [$seller, $shop] = $this->sellerOn('stall', [
            'entitlements' => array_merge(config('shop.packages.stall.entitlements'), ['bulk_mail' => true, 'ai_weekly_limit' => 900]),
        ]);

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));
        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $shop->refresh();
        // Stragan nie ma mailingu (false) i ma AI 400 — ręczne nadania wygrywają.
        $this->assertTrue($shop->entitlement('bulk_mail'));
        $this->assertSame(900, $shop->entitlement('ai_weekly_limit'));
    }

    public function test_webhook_with_a_bad_signature_changes_nothing(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));

        $this->webhook('PAY-123', 'CONFIRMED', null)->assertStatus(400);

        $this->assertSame('stall', $shop->fresh()->package);
    }

    public function test_confirmed_webhook_is_idempotent(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));

        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();
        $appliedAt = $shop->packagePayments()->first()->applied_at;

        // Ręczna zmiana terminu po zakupie NIE może zostać nadpisana dublem webhooka.
        $shop->fresh()->forceFill(['subscription_ends_at' => Carbon::parse('2028-01-01')])->save();
        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $this->assertSame('2028-01-01', $shop->fresh()->subscription_ends_at->format('Y-m-d'));
        $this->assertEquals($appliedAt, $shop->packagePayments()->first()->applied_at);
    }

    public function test_pending_or_rejected_status_does_not_apply(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));

        $this->webhook('PAY-123', 'PENDING')->assertOk();
        $this->webhook('PAY-123', 'REJECTED')->assertOk();

        $this->assertSame('stall', $shop->fresh()->package);
        // Odrzucenie jest terminalne: wiersz przestaje wisieć jako „pending".
        $this->assertSame('failed', $shop->packagePayments()->first()->status);
    }

    public function test_rejected_payment_invites_a_retry_on_the_screen(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));
        $this->webhook('PAY-123', 'REJECTED')->assertOk();

        // Baner mówi prawdę („nie doszła do skutku"), a przyciski Kup zostają.
        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('nie doszła do skutku')
            ->assertDontSee('czeka na potwierdzenie')
            ->assertSee('Kup Stragan');

        // Ponowienie tworzy NOWY wiersz, który przejmuje baner jako pending.
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));
        $this->assertSame(2, $shop->packagePayments()->count());

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('czeka na potwierdzenie')
            ->assertDontSee('nie doszła do skutku');
    }

    public function test_late_rejected_after_confirmed_cannot_undo_the_purchase(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));

        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();
        $this->webhook('PAY-123', 'REJECTED')->assertOk();

        // Spóźniony REJECTED po udanym zakupie: pakiet i status zostają.
        $this->assertSame('booth', $shop->fresh()->package);
        $this->assertSame(PackagePayment::STATUS_PAID, $shop->packagePayments()->first()->status);
    }

    public function test_downgrade_cannot_be_bought(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']))
            ->assertRedirect(route('seller.package.show'))
            ->assertSessionHas('error');

        $this->assertSame(0, $shop->packagePayments()->count());
        Http::assertNothingSent();
    }

    public function test_gateway_failure_marks_the_payment_failed(): void
    {
        Http::fake(['*/v1/payments' => Http::response(['error' => 'boom'], 500)]);
        [$seller, $shop] = $this->sellerOn('stall');

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']))
            ->assertSessionHas('error');

        $this->assertSame('failed', $shop->packagePayments()->first()->status);
    }

    public function test_screen_shows_buy_buttons_and_pending_banner(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Kup Stragan — 750,00 zł')
            ->assertSee('Kup Pawilon — 1 500,00 zł');

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('czeka na potwierdzenie');
    }

    public function test_start_mails_the_seller_a_payment_link(): void
    {
        $this->fakePaynow();
        [$seller] = $this->sellerOn('stall');
        \App\Models\EmailMessage::query()->delete();

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));

        $mail = \App\Models\EmailMessage::where('to_email', $seller->email)->firstOrFail();
        $this->assertStringContainsString('Zamówienie pakietu Stragan', $mail->subject);
        // Link ratunkowy: prowadzi z powrotem do bramki.
        $this->assertSame('https://sandbox.paynow.pl/pay/PAY-123', $mail->action_url);
        // Mail platformy — bez brandingu sklepu.
        $this->assertNull($mail->shop_id);
    }

    public function test_confirmation_mails_a_thank_you_with_the_feature_list_and_term(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));
        \App\Models\EmailMessage::query()->delete();

        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $mail = \App\Models\EmailMessage::where('to_email', $seller->email)->firstOrFail();
        $body = json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Pakiet Stragan jest aktywny', $mail->subject);
        $this->assertStringContainsString('750,00', $body);
        $this->assertStringContainsString('30.07.2027', $body);              // okres
        $this->assertStringContainsString('• Płatności online Paynow', $body); // lista funkcji
        $this->assertStringContainsString('• Do 48 produktów', $body);
    }

    public function test_payment_history_shows_status_and_validity(): void
    {
        // Dwie płatności = dwa różne paymentId, jak w realnym Paynow.
        Http::fakeSequence('*/v1/payments')
            ->push(['paymentId' => 'PAY-1', 'redirectUrl' => 'https://s/pay/1', 'status' => 'NEW'])
            ->push(['paymentId' => 'PAY-2', 'redirectUrl' => 'https://s/pay/2', 'status' => 'NEW']);
        [$seller, $shop] = $this->sellerOn('stall');

        // Nieudana próba, potem udany zakup — obie w historii.
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));
        $this->webhook('PAY-1', 'REJECTED')->assertOk();
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']));
        $this->webhook('PAY-2', 'CONFIRMED')->assertOk();

        // `fresh()`: actingAs trzyma jeden obiekt między requestami, więc bez
        // tego relacja shop niesie stan sprzed zakupu (w produkcji każdy request
        // ładuje użytkownika świeżo).
        $this->actingAs($seller->fresh())->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Historia pakietu')
            ->assertSee('płatność nieudana')
            ->assertSee('ważny do 30.07.2027')
            // Opłata, z której wynika bieżący pakiet — z plakietką.
            ->assertSee('obecny');
    }

    public function test_with_online_purchase_the_change_box_points_at_buy_buttons(): void
    {
        $this->fakePaynow();
        [$seller] = $this->sellerOn('stall');

        // Zakup działa online — box nie każe pisać maila, kieruje na przyciski.
        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('przyciski „Kup" znajdziesz przy pakietach powyżej', false)
            ->assertDontSee('Napisz do nas, a przestawimy');
    }

    public function test_shop_without_invoice_data_cannot_buy(): void
    {
        $this->fakePaynow();
        $seller = User::factory()->consented()->create();
        // Sklep bez adresu — faktury nie da się wystawić, więc nie bierzemy pieniędzy.
        $shop = Shop::factory()->create(['owner_id' => $seller->id, 'package' => 'stall']);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Uzupełnij nazwę i adres')
            ->assertDontSee('Kup Stragan');

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']))
            ->assertRedirect(route('seller.package.show'))
            ->assertSessionHas('error');

        $this->assertSame(0, $shop->packagePayments()->count());
        Http::assertNothingSent();
    }

    public function test_shop_without_company_data_buys_with_a_personal_invoice(): void
    {
        $this->fakePaynow();
        $seller = User::factory()->consented()->create(['name' => 'Anna', 'surname' => 'Kowalska']);
        // Osoba fizyczna (rękodzieło): brak NIP-u nie blokuje — faktura imienna.
        $shop = Shop::factory()->withPersonalInvoiceData()->create(['owner_id' => $seller->id, 'package' => 'stall']);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('fakturę imienną')
            ->assertSee('Anna Kowalska')
            ->assertSee('Kup Stragan');

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']))
            ->assertRedirect('https://sandbox.paynow.pl/pay/PAY-123');

        $this->assertSame(1, $shop->packagePayments()->count());
    }

    public function test_invoice_recipient_is_shown_before_paying(): void
    {
        $this->fakePaynow();
        [$seller] = $this->sellerOn('stall');

        // Sprzedawca widzi, na co pójdzie dokument, ZANIM zapłaci.
        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Dane do faktury')
            ->assertSee('Kwiaciarnia Anna Kowalska')
            ->assertSee('NIP 1234563218')
            ->assertSee('Polna 7')
            ->assertSee('00-001 Warszawa');
    }

    public function test_without_platform_keys_there_are_no_buy_buttons(): void
    {
        config(['services.paynow.platform.api_key' => null]);
        [$seller] = $this->sellerOn('stall');

        // Bez konfiguracji ekran nie może pokazywać martwego przycisku.
        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertDontSee('Kup Stragan');
    }
}
