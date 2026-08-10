<?php

namespace Tests\Feature\Mail;

use App\Enums\ConsentChannel;
use App\Enums\MailPriority;
use App\Exceptions\BulkMailException;
use App\Models\EmailMessage;
use App\Models\PlatformMailing;
use App\Models\User;
use App\Services\PlatformMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wiadomości platformy do sprzedawców — reguły wysyłki: kto dostaje (zaznaczeni
 * PRZECIĘCI z aktualną zgodą), ile razy (raz na wiadomość, ale BEZ karencji
 * między wiadomościami) i w jakim tempie (paczki przez `scheduled_at`,
 * najniższy priorytet, żeby nigdy nie wyprzedzić maili transakcyjnych).
 */
class PlatformMailSendingTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PlatformMailService
    {
        return app(PlatformMailService::class);
    }

    private function consentingSeller(?string $email = null, ?string $surname = null): User
    {
        $seller = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'surname' => $surname ?? fake()->lastName(),
        ]);

        $seller->setMarketingConsent(ConsentChannel::Email, true, '127.0.0.1');

        return $seller;
    }

    public function test_sends_to_every_consenting_seller_when_nothing_was_selected(): void
    {
        // Świeży szkic nie ma wyboru — domyślnie idzie do wszystkich, bo tak
        // wygląda najczęstszy przypadek („napisz do sprzedawców").
        $this->consentingSeller('a@example.com');
        $this->consentingSeller('b@example.com');

        $mailing = PlatformMailing::factory()->create();

        $this->assertSame(2, $this->service()->send($mailing));
        $this->assertSame(2, EmailMessage::whereNotNull('platform_mailing_id')->count());
    }

    public function test_selection_narrows_the_audience(): void
    {
        $chosen = $this->consentingSeller('chosen@example.com');
        $this->consentingSeller('skipped@example.com');

        $mailing = PlatformMailing::factory()->create();
        $mailing->forceFill(['recipient_ids' => [$chosen->id]])->save();

        $this->assertSame(1, $this->service()->send($mailing));
        $this->assertSame('chosen@example.com', EmailMessage::whereNotNull('platform_mailing_id')->sole()->to_email);
    }

    public function test_selecting_someone_without_consent_does_not_reach_them(): void
    {
        // Najważniejsza reguła modułu: zaznaczenie ZAWĘŻA pulę uprawnionych,
        // ale nigdy jej nie poszerza. Inaczej jedno kliknięcie w panelu
        // omijałoby czyjś wypis.
        $consenting = $this->consentingSeller('ok@example.com');
        $never = User::factory()->create(['email' => 'never@example.com']);

        $revoked = $this->consentingSeller('revoked@example.com');
        $revoked->setMarketingConsent(ConsentChannel::Email, false);

        $mailing = PlatformMailing::factory()->create();
        $mailing->forceFill(['recipient_ids' => [$consenting->id, $never->id, $revoked->id]])->save();

        $this->assertSame(1, $this->service()->send($mailing));

        $addresses = EmailMessage::whereNotNull('platform_mailing_id')->pluck('to_email')->all();
        $this->assertSame(['ok@example.com'], $addresses);
    }

    public function test_admins_are_never_recipients(): void
    {
        // Narzędzie nazywa się „do sprzedawców". Konto właściciela ze zgodą
        // (backfill migracji nadał ją założycielom) nie może wpaść do wysyłki.
        $admin = User::factory()->admin()->create();
        $admin->setMarketingConsent(ConsentChannel::Email, true, '127.0.0.1');

        $this->consentingSeller('seller@example.com');

        $mailing = PlatformMailing::factory()->create();

        $this->assertSame(1, $this->service()->send($mailing));
        $this->assertSame('seller@example.com', EmailMessage::whereNotNull('platform_mailing_id')->sole()->to_email);
    }

    public function test_empty_selection_refuses_to_send(): void
    {
        $this->consentingSeller();

        $mailing = PlatformMailing::factory()->create();
        $mailing->forceFill(['recipient_ids' => []])->save();

        $this->expectException(BulkMailException::class);
        $this->expectExceptionMessage('Nie zaznaczyłeś ani jednego odbiorcy');

        $this->service()->send($mailing);
    }

    public function test_no_consenting_sellers_refuses_with_its_own_message(): void
    {
        User::factory()->create();

        $mailing = PlatformMailing::factory()->create();

        $this->expectException(BulkMailException::class);
        $this->expectExceptionMessage('Żaden sprzedawca nie zgodził się');

        $this->service()->send($mailing);
    }

    public function test_the_same_message_cannot_go_out_twice(): void
    {
        $this->consentingSeller();
        $mailing = PlatformMailing::factory()->sent()->create();

        $this->expectException(BulkMailException::class);

        $this->service()->send($mailing);
    }

    public function test_there_is_no_cooldown_between_messages(): void
    {
        // Świadoma różnica wobec korespondencji sklepu: tam karencja chroni
        // klientów, tu adresatami są ludzie, z którymi mamy umowę.
        $this->consentingSeller();

        $this->service()->send(PlatformMailing::factory()->create());
        $second = PlatformMailing::factory()->create();

        $this->assertSame(1, $this->service()->send($second));
        $this->assertTrue($second->fresh()->isSent());
    }

    public function test_messages_go_out_with_lowest_priority_and_spread_in_time(): void
    {
        config(['platform_mail.per_minute' => 2]);

        foreach (range(1, 5) as $i) {
            $this->consentingSeller('s'.$i.'@example.com');
        }

        $mailing = PlatformMailing::factory()->create();
        $this->service()->send($mailing);

        $messages = EmailMessage::whereNotNull('platform_mailing_id')->orderBy('id')->get();

        $this->assertCount(5, $messages);
        $this->assertTrue($messages->every(fn (EmailMessage $m) => $m->priority === MailPriority::Low));

        // Paczki po 2 na minutę: piąty mail wychodzi w trzeciej minucie.
        $first = $messages->first()->scheduled_at;
        $this->assertSame(0, (int) $first->diffInMinutes($messages[1]->scheduled_at, absolute: true));
        $this->assertSame(2, (int) $first->diffInMinutes($messages[4]->scheduled_at, absolute: true));
    }

    public function test_sent_message_carries_an_unsubscribe_link_and_no_shop(): void
    {
        $this->consentingSeller();

        $this->service()->send(PlatformMailing::factory()->create());

        $message = EmailMessage::whereNotNull('platform_mailing_id')->sole();

        $this->assertNotNull($message->unsubscribe_url);
        $this->assertStringContainsString('/wypisz-sie/', $message->unsubscribe_url);
        // Mail platformy, nie sklepu — inaczej wziąłby cudzą szatę i nadawcę.
        $this->assertNull($message->shop_id);
    }

    public function test_recipients_count_is_frozen_at_send_time(): void
    {
        $one = $this->consentingSeller();
        $this->consentingSeller();

        $mailing = PlatformMailing::factory()->create();
        $this->service()->send($mailing);

        // Wypis PO wysyłce nie może przepisać historii.
        $one->setMarketingConsent(ConsentChannel::Email, false);

        $this->assertSame(2, (int) $mailing->fresh()->recipients_count);
    }

    public function test_test_sample_goes_nowhere_near_the_campaign(): void
    {
        $this->consentingSeller();
        $mailing = PlatformMailing::factory()->create();

        $this->service()->sendTest($mailing, 'admin@example.com', 'Rafał');

        $sample = EmailMessage::latest('id')->first();

        $this->assertSame('admin@example.com', $sample->to_email);
        $this->assertStringStartsWith('[PODGLĄD]', $sample->subject);
        // Próbka nie należy do kampanii — inaczej zafałszowałaby postęp wysyłki.
        $this->assertNull($sample->platform_mailing_id);
        $this->assertSame(1, (int) $mailing->fresh()->test_sends);
        $this->assertFalse($mailing->fresh()->isSent());
    }
}
