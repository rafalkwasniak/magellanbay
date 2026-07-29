<?php

namespace Tests\Feature\Mail;

use App\Enums\ConsentChannel;
use App\Enums\MailPriority;
use App\Exceptions\BulkMailException;
use App\Models\BulkMailing;
use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Services\BulkMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Korespondencja seryjna — reguły wysyłki: kto dostaje (tylko własni klienci
 * sklepu z aktywną zgodą), ile razy (raz na wiadomość, potem karencja liczona
 * kalendarzowo) i w jakim tempie (paczki po `per_minute` przez `scheduled_at`,
 * najniższy priorytet, żeby nigdy nie wyprzedzić maili transakcyjnych).
 */
class BulkMailSendingTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BulkMailService
    {
        return app(BulkMailService::class);
    }

    private function shopWithBulkMail(): Shop
    {
        return Shop::factory()->withBulkMail()->create();
    }

    private function consentingCustomer(Shop $shop, ?string $email = null): Customer
    {
        $customer = Customer::factory()->create([
            'shop_id' => $shop->id,
            'email' => $email ?? fake()->unique()->safeEmail(),
        ]);

        $customer->setConsent(ConsentChannel::Email, true, '127.0.0.1');

        return $customer->fresh();
    }

    private function draftFor(Shop $shop): BulkMailing
    {
        return $shop->bulkMailings()->create([
            'subject' => 'Nowa książka w sprzedaży',
            'body' => "Mamy dla Ciebie nowość.\n\nZajrzyj do sklepu.",
        ]);
    }

    public function test_mailing_reaches_only_customers_with_active_consent(): void
    {
        $shop = $this->shopWithBulkMail();
        $willing = $this->consentingCustomer($shop, 'zgoda@example.test');

        // Bez zgody — brak wiersza zgody to NIE to samo co zgoda.
        Customer::factory()->create(['shop_id' => $shop->id, 'email' => 'bez-zgody@example.test']);

        // Zgoda wycofana — wypis musi być respektowany natychmiast.
        $this->consentingCustomer($shop, 'wypisany@example.test')
            ->setConsent(ConsentChannel::Email, false);

        EmailMessage::query()->delete();

        $count = $this->service()->send($this->draftFor($shop));

        $this->assertSame(1, $count);
        $this->assertSame([$willing->email], EmailMessage::pluck('to_email')->all());
    }

    public function test_consent_in_one_shop_does_not_open_another_shops_mailing(): void
    {
        $shop = $this->shopWithBulkMail();
        $other = $this->shopWithBulkMail();
        $this->consentingCustomer($other, 'obcy@example.test');

        $this->expectException(BulkMailException::class);
        $this->service()->send($this->draftFor($shop));
    }

    public function test_messages_go_out_with_lowest_priority_and_spread_over_time(): void
    {
        $shop = $this->shopWithBulkMail();
        config(['bulk_mail.per_minute' => 2]);

        foreach (range(1, 5) as $i) {
            $this->consentingCustomer($shop, "klient{$i}@example.test");
        }

        EmailMessage::query()->delete();
        Carbon::setTestNow(Carbon::parse('2026-07-29 20:00:00'));

        $this->service()->send($this->draftFor($shop));

        $messages = EmailMessage::orderBy('id')->get();
        $this->assertCount(5, $messages);
        $this->assertTrue($messages->every(fn (EmailMessage $m): bool => $m->priority === MailPriority::Low));

        // Paczki po 2 na minutę: 20:00, 20:00, 20:01, 20:01, 20:02.
        $this->assertSame(
            ['20:00', '20:00', '20:01', '20:01', '20:02'],
            $messages->map(fn (EmailMessage $m): string => $m->scheduled_at->format('H:i'))->all(),
        );

        Carbon::setTestNow();
    }

    public function test_the_same_mailing_cannot_be_sent_twice(): void
    {
        $shop = $this->shopWithBulkMail();
        $this->consentingCustomer($shop);
        $mailing = $this->draftFor($shop);

        $this->service()->send($mailing);

        $this->assertTrue($mailing->fresh()->isSent());
        $this->assertSame(1, $mailing->fresh()->recipients_count);

        $this->expectException(BulkMailException::class);
        $this->service()->send($mailing->fresh());
    }

    public function test_cooldown_is_calendar_based_not_minute_based(): void
    {
        $shop = $this->shopWithBulkMail();
        $this->consentingCustomer($shop);

        // Wysyłka we wtorek o 20:00...
        Carbon::setTestNow(Carbon::parse('2026-07-28 20:00:00'));
        $this->service()->send($this->draftFor($shop));

        // ...nie odblokowuje się dopiero o 20:01 tydzień później, tylko o północy.
        $this->assertSame('2026-08-04 00:00', $this->service()->nextAllowedAt($shop->fresh())->format('Y-m-d H:i'));

        // Dzień wcześniej wciąż karencja.
        Carbon::setTestNow(Carbon::parse('2026-08-03 23:59:00'));
        $this->assertNotNull($this->service()->nextAllowedAt($shop->fresh()));

        // O północy siódmego dnia — wolno, choć od wysyłki nie minęło 7×24 h.
        Carbon::setTestNow(Carbon::parse('2026-08-04 00:00:01'));
        $this->assertNull($this->service()->nextAllowedAt($shop->fresh()));

        Carbon::setTestNow();
    }

    public function test_second_mailing_during_cooldown_is_rejected_with_a_date(): void
    {
        $shop = $this->shopWithBulkMail();
        $this->consentingCustomer($shop);

        Carbon::setTestNow(Carbon::parse('2026-07-28 20:00:00'));
        $this->service()->send($this->draftFor($shop));

        try {
            $this->service()->send($this->draftFor($shop->fresh()));
            $this->fail('Druga wysyłka w karencji powinna zostać odrzucona.');
        } catch (BulkMailException $e) {
            $this->assertStringContainsString('04.08.2026', $e->getMessage());
        }

        Carbon::setTestNow();
    }

    public function test_sending_without_a_single_consenting_customer_is_refused(): void
    {
        $shop = $this->shopWithBulkMail();
        Customer::factory()->create(['shop_id' => $shop->id]);

        try {
            $this->service()->send($this->draftFor($shop));
            $this->fail('Wysyłka bez odbiorców powinna zostać odrzucona.');
        } catch (BulkMailException $e) {
            $this->assertStringContainsString('Nikt z Twoich klientów', $e->getMessage());
        }
    }

    public function test_shop_without_the_entitlement_cannot_send(): void
    {
        $shop = Shop::factory()->create();   // pakiet domyślny: Kram
        $this->consentingCustomer($shop);

        try {
            $this->service()->send($this->draftFor($shop));
            $this->fail('Sklep bez uprawnienia nie powinien wysłać mailingu.');
        } catch (BulkMailException $e) {
            $this->assertStringContainsString('Pawilon', $e->getMessage());
        }
    }

    public function test_test_send_is_unlimited_and_does_not_consume_the_cooldown(): void
    {
        $shop = $this->shopWithBulkMail();
        $this->consentingCustomer($shop);
        $mailing = $this->draftFor($shop);

        EmailMessage::query()->delete();

        // Próbki do siebie — ile trzeba, zanim treść pójdzie do klientów.
        $this->service()->sendTest($mailing, 'sprzedawca@example.test', 'Rafał');
        $this->service()->sendTest($mailing, 'sprzedawca@example.test', 'Rafał');
        $this->service()->sendTest($mailing, 'sprzedawca@example.test', 'Rafał');

        $this->assertSame(3, $mailing->fresh()->test_sends);
        $this->assertSame(3, EmailMessage::where('to_email', 'sprzedawca@example.test')->count());
        $this->assertStringStartsWith('[PODGLĄD]', EmailMessage::first()->subject);

        // Mailing wciąż jest szkicem, karencja nietknięta — wysyłka działa.
        $this->assertTrue($mailing->fresh()->isDraft());
        $this->assertNull($this->service()->nextAllowedAt($shop->fresh()));
        $this->assertSame(1, $this->service()->send($mailing->fresh()));
    }

    public function test_heading_greets_by_name_instead_of_repeating_the_subject(): void
    {
        $shop = $this->shopWithBulkMail();
        $customer = Customer::factory()->create(['shop_id' => $shop->id, 'name' => 'Rafał']);
        $customer->setConsent(ConsentChannel::Email, true, '127.0.0.1');

        EmailMessage::query()->delete();
        $this->service()->send($this->draftFor($shop));

        $message = EmailMessage::firstOrFail();

        // Temat robi swoje w skrzynce; nad treścią stoi powitanie po imieniu.
        $this->assertSame('Nowa książka w sprzedaży', $message->subject);
        $this->assertSame('Cześć Rafale', $message->heading);
        $this->assertNull($message->greeting);
        $this->assertStringNotContainsString('Nowa książka w sprzedaży', (new \App\Mail\OutboxMailable($message))->render());
    }

    public function test_customer_without_a_first_name_gets_a_neutral_heading(): void
    {
        $shop = $this->shopWithBulkMail();
        $customer = Customer::factory()->create(['shop_id' => $shop->id, 'name' => 'KWIACIARNIA SP. Z O.O.']);
        $customer->setConsent(ConsentChannel::Email, true, '127.0.0.1');

        EmailMessage::query()->delete();
        $this->service()->send($this->draftFor($shop));

        $this->assertSame('Dzień dobry', EmailMessage::firstOrFail()->heading);
    }

    public function test_formatting_from_the_editor_survives_into_the_mail(): void
    {
        $shop = $this->shopWithBulkMail();
        $this->consentingCustomer($shop);

        $mailing = $shop->bulkMailings()->create([
            'subject' => 'Nowa książka',
            'body' => '<div><strong>Nowość</strong> w sklepie</div><div><ul><li>oprawa twarda</li></ul></div>',
        ]);

        EmailMessage::query()->delete();
        $this->service()->send($mailing);

        // Treść z edytora idzie jako HTML (`body_html`), a nie jako escapowane
        // bloki — inaczej klient dostałby dosłowne znaczniki zamiast pogrubień.
        $message = EmailMessage::firstOrFail();
        $this->assertStringContainsString('<strong>Nowość</strong>', $message->body_html);
        $this->assertStringContainsString('<li>oprawa twarda</li>', $message->body_html);

        // I faktycznie renderuje się w wysyłanym mailu.
        $rendered = (new \App\Mail\OutboxMailable($message))->render();
        $this->assertStringContainsString('<strong>Nowość</strong>', $rendered);
        $this->assertStringContainsString('oprawa twarda', $rendered);
    }

    public function test_editor_html_is_sanitized_on_save(): void
    {
        $seller = \App\Models\User::factory()->consented()->create();
        $shop = Shop::factory()->withBulkMail()->create(['owner_id' => $seller->id]);

        // Skrypt w treści nie ma prawa dojechać do skrzynki klienta.
        $this->actingAs($seller)->post(route('seller.mailings.store'), [
            'subject' => 'Test',
            'body' => '<div>Treść<script>alert(1)</script></div>',
        ])->assertRedirect();

        $body = $shop->bulkMailings()->firstOrFail()->body;
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('Treść', $body);
    }
}
