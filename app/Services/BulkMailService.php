<?php

namespace App\Services;

use App\Enums\ConsentChannel;
use App\Enums\MailPriority;
use App\Exceptions\BulkMailException;
use App\Models\BulkMailing;
use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Support\Vocative;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Korespondencja seryjna: wysyłka jednej wiadomości do klientów sklepu, którzy
 * udzielili zgody marketingowej.
 *
 * TRZY ZASADY, KTÓRE TRZYMAJĄ TEN MODUŁ W RYZACH:
 *
 * 1. **Tylko własni klienci ze zgodą.** Nie ma importu adresów ani budowania
 *    bazy — mailing sięga wyłącznie kont założonych w TYM sklepie, z aktywną
 *    zgodą na kanał e-mail. Zgoda jest per sklep, więc zgoda u jednego
 *    sprzedawcy nigdy nie otworzy skrzynki drugiemu.
 * 2. **Jedna wiadomość leci raz, potem karencja.** `sent_at` blokuje powtórkę,
 *    a kolejny mailing dopiero po `cooldown_days` liczonych KALENDARZOWO.
 * 3. **Próbki do siebie są darmowe.** Wysyłka testowa idzie na adres samego
 *    sprzedawcy, więc nie zużywa karencji i nie ma limitu — po to jest, żeby
 *    sprawdzić treść tyle razy, ile trzeba, ZANIM zobaczą ją klienci.
 *
 * Tempa nie pilnuje osobny throttler: rozkładamy wiadomości przez `scheduled_at`
 * w paczkach po `bulk_mail.per_minute`, a wypuszcza je istniejący cron outboxu.
 * Priorytet `Low` sprawia, że maile transakcyjne zawsze wyprzedzają mailing.
 */
class BulkMailService
{
    /**
     * Klienci, do których pójdzie mailing: konta tego sklepu z aktywną zgodą
     * na kanał e-mail. Zgoda cofnięta (`revoked_at`) odpada — to nie jest brak
     * wiersza, tylko świadomy wypis, i musi być respektowany natychmiast.
     *
     * @return Collection<int, Customer>
     */
    public function recipients(Shop $shop): Collection
    {
        return $shop->customers()
            ->whereHas('consents', fn ($query) => $query
                ->where('channel', ConsentChannel::Email->value)
                ->whereNotNull('granted_at')
                ->whereNull('revoked_at'))
            ->orderBy('id')
            ->get();
    }

    public function recipientsCount(Shop $shop): int
    {
        return $this->recipients($shop)->count();
    }

    /**
     * Najwcześniejsza chwila, w której sklep może wysłać kolejny mailing, albo
     * null, gdy może już teraz.
     *
     * Karencja jest KALENDARZOWA: liczymy od POCZĄTKU DNIA ostatniej wysyłki,
     * więc mailing z wtorku o 20:00 odblokowuje kolejny w następny wtorek o
     * 00:00, a nie o 20:01. Sprzedawca nie musi pilnować minuty — wysyłek
     * prawie nigdy nie robi się co do godziny.
     */
    public function nextAllowedAt(Shop $shop): ?CarbonInterface
    {
        $last = $shop->bulkMailings()->whereNotNull('sent_at')->max('sent_at');

        if ($last === null) {
            return null;
        }

        $next = Carbon::parse($last)
            ->startOfDay()
            ->addDays((int) config('bulk_mail.cooldown_days'));

        return $next->isFuture() ? $next : null;
    }

    /**
     * Wysyła próbkę na wskazany adres (własny adres sprzedawcy). Świadomie BEZ
     * karencji i bez limitu — to narzędzie do sprawdzania treści, a poszkodować
     * może najwyżej własną skrzynkę.
     *
     * Priorytet `Mid`, a nie `Low`: sprzedawca czeka na tę wiadomość, więc nie
     * może stać w kolejce za zaległym mailingiem do klientów.
     */
    public function sendTest(BulkMailing $mailing, string $email, ?string $name = null): void
    {
        $this->guardEntitlement($mailing->shop);

        EmailMessage::create($this->senderIdentity($mailing->shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $mailing->shop_id,
            'to_email' => $email,
            'to_name' => $name,
            // Wyraźny prefiks, żeby próbka nie pomyliła się z prawdziwą wysyłką
            // w skrzynce sprzedawcy — zwłaszcza gdy testuje kilka wersji.
            'subject' => '[PODGLĄD] '.$mailing->subject,
            'preheader' => 'Podgląd wiadomości przed wysyłką do klientów.',
            // Nagłówkiem jest POWITANIE, nie temat — temat robi swoje w linii
            // tematu, a powtórzony nad treścią tylko zabierałby miejsce.
            'heading' => Vocative::headline($name),
            'greeting' => null,
            'body_html' => $mailing->body,
            'outro_lines' => [
                'To jest podgląd — Twoi klienci jeszcze go nie dostali. Wyślij wiadomość z panelu, gdy treść będzie gotowa.',
                'W wersji dla klientów na dole znajdzie się link do wypisania się z wiadomości.',
            ],
        ]);

        $mailing->increment('test_sends');
    }

    /**
     * Puszcza mailing do wszystkich klientów ze zgodą. Zwraca liczbę odbiorców.
     *
     * Wiadomości trafiają do outboxu z rozłożonym `scheduled_at`, więc wysyłka
     * rozciąga się w czasie zamiast uderzyć jednym ciosem w limity hostingu.
     *
     * @throws BulkMailException
     */
    public function send(BulkMailing $mailing): int
    {
        $shop = $mailing->shop;

        $this->guardEntitlement($shop);

        if ($mailing->isSent()) {
            throw new BulkMailException('Ta wiadomość została już wysłana. Aby napisać do klientów ponownie, utwórz nową.');
        }

        $blockedUntil = $this->nextAllowedAt($shop);

        if ($blockedUntil !== null) {
            throw new BulkMailException(
                'Kolejną wiadomość możesz wysłać od '.$blockedUntil->format('d.m.Y').'. Klienci dostają od Ciebie najwyżej jedną wiadomość na '
                .config('bulk_mail.cooldown_days').' dni.',
            );
        }

        $recipients = $this->recipients($shop);

        if ($recipients->isEmpty()) {
            throw new BulkMailException('Nikt z Twoich klientów nie zgodził się jeszcze na otrzymywanie wiadomości. Zgodę zaznaczają przy zakładaniu konta lub w swoim profilu.');
        }

        $perMinute = max(1, (int) config('bulk_mail.per_minute'));
        $startedAt = Carbon::now();

        DB::transaction(function () use ($mailing, $shop, $recipients, $perMinute, $startedAt): void {
            foreach ($recipients->values() as $index => $customer) {
                EmailMessage::create($this->senderIdentity($shop) + [
                    // Najniższy priorytet: mailing NIGDY nie może opóźnić
                    // potwierdzenia zamówienia ani linku aktywacyjnego.
                    'priority' => MailPriority::Low,
                    'shop_id' => $shop->id,
                    'to_email' => $customer->email,
                    'to_name' => $customer->name,
                    'subject' => $mailing->subject,
                    // Nad treścią stoi powitanie po imieniu („Cześć Rafale"),
                    // a nie powtórzony temat — ten widać już w skrzynce.
                    'heading' => Vocative::headline($customer->name),
                    'greeting' => null,
                    'body_html' => $mailing->body,
                    // Stopka wypisu — obowiązkowa w każdej wiadomości
                    // marketingowej. Link jest BEZTERMINOWY: mail sprzed roku
                    // musi dać się odsubskrybować tak samo jak dzisiejszy.
                    'unsubscribe_url' => $this->unsubscribeUrl($shop, $customer),
                    // Rozłożenie w czasie: paczka na minutę. Kolejka i tak
                    // zabiera po `mail_outbox.batch_size` na tik, więc to tylko
                    // ustala tempo, nie wymusza żadnego nowego mechanizmu.
                    'scheduled_at' => $startedAt->copy()->addMinutes(intdiv($index, $perMinute)),
                ]);
            }

            $mailing->forceFill([
                // Migawka: ilu klientów miało zgodę W CHWILI wysyłki. Później
                // nie do odtworzenia, bo ludzie się wypisują.
                'recipients_count' => $recipients->count(),
                'sent_at' => Carbon::now(),
            ])->save();
        });

        return $recipients->count();
    }

    /**
     * Podpisany, bezterminowy adres wypisu dla konkretnego klienta. Podpis
     * wiąże klienta z subdomeną jego sklepu, więc link z maila jednego sprzedawcy
     * nie wypisze nikogo u drugiego.
     */
    private function unsubscribeUrl(Shop $shop, Customer $customer): string
    {
        return URL::signedRoute('storefront.unsubscribe', [
            'shop' => $shop->slug,
            'customer' => $customer->id,
        ]);
    }

    /**
     * Bramka pakietu — korespondencja seryjna należy do Pawilonu.
     *
     * @throws BulkMailException
     */
    private function guardEntitlement(Shop $shop): void
    {
        if ($shop->entitlement('bulk_mail') !== true) {
            throw new BulkMailException('Korespondencja seryjna jest dostępna w pakiecie Pawilon.');
        }
    }

    /**
     * Tożsamość nadawcy „od sklepu" — jak w mailach o zamówieniu: nazwa sklepu
     * w kopercie, odpowiedź leci na jego adres kontaktowy.
     *
     * @return array<string, string|null>
     */
    private function senderIdentity(Shop $shop): array
    {
        return [
            'from_name' => $shop->name,
            'reply_to' => $shop->contact_email,
        ];
    }

}
