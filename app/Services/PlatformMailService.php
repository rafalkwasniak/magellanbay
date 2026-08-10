<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Enums\UserRole;
use App\Exceptions\BulkMailException;
use App\Models\EmailMessage;
use App\Models\PlatformMailing;
use App\Models\User;
use App\Support\Vocative;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Wiadomości platformy do sprzedawców. Bliźniak {@see BulkMailService}, ale
 * osobną ścieżką — bez sklepu, bez pakietu i bez karencji.
 *
 * TRZY ZASADY, KTÓRE TRZYMAJĄ TO W RYZACH:
 *
 * 1. **Zgoda jest bramką, zaznaczenie tylko zawężeniem.** Administrator wybiera
 *    checkboxami, DO KOGO z uprawnionych chce napisać. Zaznaczenie kogoś, kto
 *    zgody nie ma albo ją wycofał, nie wyśle mu nic — lista adresatów zawsze
 *    przechodzi przez `User::activeMarketingConsent()`, ten sam warunek, którym
 *    liczy odbiorców panel. Rozjazd tych dwóch miejsc oznaczałby wysyłkę do
 *    kogoś wypisanego, więc definicja ma jedno źródło.
 * 2. **Jedna wiadomość leci raz.** `sent_at` blokuje powtórkę. Karencji NIE ma:
 *    kolejną wiadomość można napisać i wysłać od ręki.
 * 3. **Próbki do siebie są darmowe.** Idą na adres zalogowanego administratora,
 *    bez limitu — po to są, żeby sprawdzić treść, zanim zobaczą ją sprzedawcy.
 *
 * Granica prawna: to narzędzie służy WYŁĄCZNIE treściom handlowym (nowości,
 * oferty, kody). Maile niezbędne do wykonania umowy — faktura, wygaśnięcie
 * pakietu, awaria, zmiana regulaminu — idą własnymi ścieżkami do wszystkich
 * i nigdy nie wolno ich blokować zgodą marketingową.
 */
class PlatformMailService
{
    /**
     * Sprzedawcy, którzy mogą dostać treści handlowe: rola `seller` z aktywną
     * zgodą na kanał e-mail. To pełna pula, z której administrator wybiera.
     *
     * @return Collection<int, User>
     */
    public function eligible(): Collection
    {
        return User::query()
            ->where('role', UserRole::Seller)
            ->whereHas('marketingConsents', User::activeMarketingConsent())
            // Sklep dociągany od razu: lista wyboru pokazuje jego nazwę przy
            // każdym wierszu, więc bez tego byłoby zapytanie na sprzedawcę.
            ->with('shop')
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
    }

    public function eligibleCount(): int
    {
        return User::query()
            ->where('role', UserRole::Seller)
            ->whereHas('marketingConsents', User::activeMarketingConsent())
            ->count();
    }

    /**
     * Faktyczni adresaci tej wiadomości: przecięcie zaznaczenia administratora
     * z aktualną pulą uprawnionych.
     *
     * Świeży szkic (`recipient_ids = null`) nie ma jeszcze wyboru i domyślnie
     * obejmuje wszystkich uprawnionych — inaczej „napisz i wyślij" wymagałoby
     * kliknięcia w listę, choć w większości wypadków pisze się do wszystkich.
     *
     * @return Collection<int, User>
     */
    public function recipients(PlatformMailing $mailing): Collection
    {
        $eligible = $this->eligible();

        if (! $mailing->hasRecipientSelection()) {
            return $eligible;
        }

        $selected = $mailing->recipientIds();

        return $eligible->filter(fn (User $user) => in_array($user->getKey(), $selected, true))->values();
    }

    /**
     * Próbka na wskazany adres (własny adres administratora). Świadomie bez
     * limitu — poszkodować może najwyżej własną skrzynkę.
     *
     * Priorytet `Mid`, a nie `Low`: nadawca czeka na tę wiadomość, więc nie może
     * stać w kolejce za zaległym mailingiem.
     */
    public function sendTest(PlatformMailing $mailing, string $email, ?string $name = null): void
    {
        EmailMessage::create([
            'priority' => MailPriority::Mid,
            // Mail platformy, nie sklepu — `shop_id` puste, więc szatę
            // i nadawcę bierze domyślna tożsamość Kramio.
            'shop_id' => null,
            'to_email' => $email,
            'to_name' => $name,
            'subject' => '[PODGLĄD] '.$mailing->subject,
            'preheader' => 'Podgląd wiadomości przed wysyłką do sprzedawców.',
            // Nagłówkiem jest POWITANIE, nie temat — ten robi swoje w linii
            // tematu, a powtórzony nad treścią tylko zabierałby miejsce.
            'heading' => Vocative::headline($name),
            'greeting' => null,
            'body_html' => $mailing->body,
            'outro_lines' => [
                'To jest podgląd — sprzedawcy jeszcze go nie dostali. Wyślij wiadomość z panelu, gdy treść będzie gotowa.',
                'W wersji dla sprzedawców na dole znajdzie się link do wypisania się z wiadomości.',
            ],
        ]);

        $mailing->increment('test_sends');
    }

    /**
     * Puszcza wiadomość do zaznaczonych sprzedawców ze zgodą. Zwraca liczbę
     * odbiorców.
     *
     * @throws BulkMailException
     */
    public function send(PlatformMailing $mailing): int
    {
        if ($mailing->isSent()) {
            throw new BulkMailException('Ta wiadomość została już wysłana. Aby napisać ponownie, utwórz nową — nie musisz na nic czekać.');
        }

        $recipients = $this->recipients($mailing);

        if ($recipients->isEmpty()) {
            throw new BulkMailException(
                $this->eligibleCount() === 0
                    ? 'Żaden sprzedawca nie zgodził się jeszcze na wiadomości handlowe od Kramio. Zgodę zaznaczają przy aktywacji konta albo w swoim profilu.'
                    : 'Nie zaznaczyłeś ani jednego odbiorcy. Wybierz sprzedawców na liście obok.',
            );
        }

        $perMinute = max(1, (int) config('platform_mail.per_minute'));
        $startedAt = Carbon::now();

        DB::transaction(function () use ($mailing, $recipients, $perMinute, $startedAt): void {
            foreach ($recipients->values() as $index => $user) {
                EmailMessage::create([
                    // Najniższy priorytet: wiadomość handlowa NIGDY nie może
                    // opóźnić faktury ani linku aktywacyjnego.
                    'priority' => MailPriority::Low,
                    'shop_id' => null,
                    'platform_mailing_id' => $mailing->getKey(),
                    'to_email' => $user->email,
                    'to_name' => $user->name,
                    'subject' => $mailing->subject,
                    'heading' => Vocative::headline($user->name),
                    'greeting' => null,
                    'body_html' => $mailing->body,
                    // Stopka wypisu — obowiązkowa w każdej wiadomości handlowej.
                    // Link BEZTERMINOWY: mail sprzed roku musi dać się
                    // odsubskrybować tak samo jak dzisiejszy.
                    'unsubscribe_url' => $this->unsubscribeUrl($user),
                    // Rozłożenie w czasie: paczka na minutę. Nie jest to nowy
                    // throttler — kolejkę i tak opróżnia istniejący cron outboxu.
                    'scheduled_at' => $startedAt->copy()->addMinutes(intdiv($index, $perMinute)),
                ]);
            }

            $mailing->forceFill([
                // Migawka: do ilu naprawdę poszło. Później nie do odtworzenia,
                // bo ludzie się wypisują.
                'recipients_count' => $recipients->count(),
                'sent_at' => Carbon::now(),
            ])->save();
        });

        return $recipients->count();
    }

    /**
     * Podpisany, bezterminowy adres wypisu dla konkretnego sprzedawcy.
     */
    public function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('platform.unsubscribe', ['user' => $user->getKey()]);
    }
}
