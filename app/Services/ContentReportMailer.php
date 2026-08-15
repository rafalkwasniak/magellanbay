<?php

namespace App\Services;

use App\Enums\ContentReportStatus;
use App\Enums\MailPriority;
use App\Models\ContentReport;
use App\Models\EmailMessage;
use App\Support\Vocative;
use Illuminate\Support\Str;

/**
 * Trzy maile obiegu zgłoszenia treści bezprawnej (art. 16 i 17 DSA).
 *
 * Wszystkie idą OD PLATFORMY — bez `shop_id` i brandingu sklepu. To nasza
 * korespondencja jako operatora, a przy zgłoszeniu przeciwko sklepowi wysłanie
 * pisma w jego szacie byłoby wprost mylące.
 *
 * `reply_to` na adres zgłoszeń, nie na ogólny kontakt: odpowiedź na decyzję ma
 * wrócić tam, gdzie leży sprawa.
 *
 * Wysyłka przez outbox (`EmailMessage` + cron `email:dispatch`), nigdy w locie —
 * publiczny formularz nie może czekać na SMTP, a nieudane wysłanie potwierdzenia
 * nie może wywrócić przyjęcia zgłoszenia.
 */
class ContentReportMailer
{
    /**
     * Potwierdzenie odbioru — art. 16 ust. 4 wymaga go „bez zbędnej zwłoki".
     *
     * Idempotentne: drugie wywołanie nic nie robi. `acknowledged_at` jest
     * znacznikiem, a nie flagą — przy sporze liczy się, KIEDY potwierdziliśmy.
     */
    public function acknowledge(ContentReport $report): void
    {
        if ($report->acknowledged_at !== null) {
            return;
        }

        EmailMessage::create([
            'priority' => MailPriority::High,
            'to_email' => $report->reporter_email,
            'to_name' => $report->reporter_name,
            'reply_to' => config('company.abuse_email'),
            // Numer sprawy W TEMACIE — po nim odnajdujemy wątek, gdy ktoś
            // odpisze za trzy tygodnie „w sprawie mojego zgłoszenia".
            'subject' => 'Zgłoszenie '.$report->reference().' — potwierdzenie odbioru',
            'preheader' => 'Zgłoszenie trafiło do rejestru. Napiszemy, gdy je rozpatrzymy.',
            'heading' => 'Zgłoszenie przyjęte',
            'greeting' => $this->greeting($report),
            'intro_lines' => [
                [
                    'Potwierdzamy, że Twoje zgłoszenie dotarło i trafiło do rejestru.',
                    'Numer sprawy: **'.$report->reference().'** — podaj go, jeśli będziesz do nas pisać.',
                ],
                [
                    'Zgłoszony adres: **'.$report->url.'**',
                    'Rodzaj zgłoszenia: **'.$report->category->label().'**',
                ],
                [
                    'Sprawdzimy je i napiszemy do Ciebie o rozstrzygnięciu wraz z uzasadnieniem.',
                ],
            ],
            'outro_lines' => [
                'Jeśli masz do dodania coś istotnego, odpowiedz na tę wiadomość.',
            ],
        ]);

        $report->forceFill(['acknowledged_at' => now()])->save();
    }

    /**
     * Rozstrzygnięcie do zgłaszającego — art. 16 ust. 5, razem z pouczeniem
     * o środkach odwoławczych.
     */
    public function decision(ContentReport $report): void
    {
        $uznane = $report->status === ContentReportStatus::Upheld;

        EmailMessage::create([
            'priority' => MailPriority::High,
            'to_email' => $report->reporter_email,
            'to_name' => $report->reporter_name,
            'reply_to' => config('company.abuse_email'),
            'subject' => 'Zgłoszenie '.$report->reference().' — rozstrzygnięcie',
            'preheader' => $uznane ? 'Uznaliśmy zgłoszenie.' : 'Nie znaleźliśmy podstaw do działania.',
            'heading' => 'Rozstrzygnięcie zgłoszenia',
            'greeting' => $this->greeting($report),
            'intro_lines' => [
                [
                    'Sprawa **'.$report->reference().'**',
                    'Rozpatrzyliśmy Twoje zgłoszenie dotyczące adresu **'.$report->url.'**.',
                    $uznane
                        ? 'Zgłoszenie zostało **uznane** — podjęliśmy działania wobec zgłoszonej treści.'
                        : 'Zgłoszenie zostało **odrzucone** — nie znaleźliśmy podstaw do ograniczenia tej treści.',
                ],
                [
                    '**Uzasadnienie:**',
                    $report->decision_reason ?? '—',
                ],
            ],
            'outro_lines' => [
                'Jeśli nie zgadzasz się z tym rozstrzygnięciem, odpowiedz na tę wiadomość — sprawę rozpatrzy człowiek, a nie automat.',
                'Niezależnie od tego możesz dochodzić swoich praw przed sądem, a w sprawach konsumenckich skorzystać z pomocy rzecznika konsumentów.',
            ],
        ]);
    }

    /**
     * Uzasadnienie ograniczenia dla SPRZEDAWCY — art. 17 DSA.
     *
     * Wysyłamy tylko przy zgłoszeniu uznanym: art. 17 dotyczy sytuacji, w której
     * faktycznie ograniczamy treść. Odrzucone zgłoszenie nic sprzedawcy nie robi,
     * więc zawiadamianie go o cudzych zarzutach, których nie podzieliliśmy, tylko
     * by go niepokoiło.
     */
    public function statementOfReasons(ContentReport $report): void
    {
        $owner = $report->shop?->owner;

        if ($owner === null || $report->status !== ContentReportStatus::Upheld) {
            return;
        }

        EmailMessage::create([
            'priority' => MailPriority::High,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'reply_to' => config('company.abuse_email'),
            'subject' => 'Zgłoszenie '.$report->reference().' — ograniczyliśmy treść w Twoim sklepie',
            'preheader' => 'Zgłoszenie dotyczące treści w Twoim sklepie zostało uznane.',
            'heading' => 'Ograniczenie treści w sklepie',
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => [
                [
                    'Otrzymaliśmy zgłoszenie dotyczące treści w Twoim sklepie i po jego rozpatrzeniu **uznaliśmy je za zasadne**.',
                    'Numer sprawy: **'.$report->reference().'**',
                    'Adres, którego dotyczy: **'.$report->url.'**',
                    'Rodzaj zarzutu: **'.$report->category->label().'**',
                ],
                [
                    '**Uzasadnienie naszej decyzji:**',
                    $report->decision_reason ?? '—',
                ],
                [
                    'Podstawą jest §10 Regulaminu (zakaz publikowania treści bezprawnych) oraz przepisy powszechnie obowiązujące.',
                    'Decyzję podjął człowiek — nie użyliśmy do niej automatycznego przetwarzania.',
                ],
            ],
            'outro_lines' => [
                'Jeśli uważasz, że decyzja jest błędna, odpowiedz na tę wiadomość i opisz dlaczego — rozpatrzymy sprawę ponownie.',
                'Niezależnie od tego przysługuje Ci droga sądowa.',
            ],
        ]);
    }

    /**
     * Zgłaszający nie musi podawać nazwiska (art. 16 wymaga danych kontaktowych,
     * nie tożsamości), więc powitanie musi działać także bez niego —
     * `Vocative::greeting(null)` zwraca wtedy „Dzień dobry,".
     */
    private function greeting(ContentReport $report): string
    {
        return Vocative::greeting(Str::before((string) $report->reporter_name, ' ') ?: null);
    }
}
