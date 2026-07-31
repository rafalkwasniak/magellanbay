<?php

namespace App\Listeners;

use App\Services\DiscordErrorReporter;
use Illuminate\Auth\Events\Lockout;

/**
 * Zablokowane logowanie → wiadomość na kanał alertów.
 *
 * Sens jest w widoczności, nie w obronie — blokada zadziałała już wcześniej.
 * Bez tego nie da się odróżnić ciszy „nikt nas nie próbuje" od ciszy „ktoś
 * dobiera się do panelu od tygodnia, a my o tym nie wiemy". Pojedynczy alert to
 * zwykle ktoś, kto zapomniał hasła; seria alertów na różne adresy to atak.
 *
 * Nie logujemy podanego hasła ANI pełnego adresu e-mail — kanał Discorda widzi
 * więcej osób niż serwer, a wyciek stamtąd byłby gorszy niż sama próba włamania.
 */
class ReportLockout
{
    public function __construct(private readonly DiscordErrorReporter $reporter) {}

    public function handle(Lockout $event): void
    {
        $request = $event->request;

        $this->reporter->alert(
            'Zablokowane logowanie',
            'Wyczerpany limit nieudanych prób logowania. Konto jest chwilowo zamknięte.',
            [
                'Konto' => $this->maskEmail((string) $request->input('email')),
                'IP' => (string) ($request->ip() ?? 'nieznane'),
                'Adres' => $request->method().' '.$request->fullUrl(),
            ],
        );
    }

    /**
     * `jan.kowalski@example.com` → `ja***@example.com`. Zostaje tyle, by
     * rozpoznać własne konto i zobaczyć, czy próby idą na wiele różnych adresów.
     */
    private function maskEmail(string $email): string
    {
        if ($email === '' || ! str_contains($email, '@')) {
            return 'brak';
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 2).'***@'.$domain;
    }
}
