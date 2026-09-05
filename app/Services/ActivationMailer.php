<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\User;
use App\Support\Mode;
use App\Support\Vocative;
use Illuminate\Support\Facades\Password;

/**
 * Kolejkuje mail aktywacyjny: token brokera `activation` (ważny 24 h) + link do
 * ustawienia hasła, wrzucone do outboxu z priorytetem High (wyprzedza newslettery).
 * Wysyłką zajmuje się cron.
 *
 * TREŚĆ ZALEŻY OD TRYBU, bo zależy od tego, CO SIĘ WŁAŚCIWIE STAŁO.
 *
 * W Kramio sprzedawca właśnie się zarejestrował: wpisał adres w formularzu, wybrał
 * nazwę sklepu i czeka na dokończenie. „Dziękujemy za rejestrację" jest prawdą.
 *
 * W sklepie dedykowanym NIKT SIĘ NIE REJESTROWAŁ. Konto założył seeder wdrożeniowy,
 * sklep już stoi, a właściciel dostaje po prostu klucze do rzeczy, którą zamówił
 * i za którą zapłacił. Mail winszujący mu „pierwszego kroku do sprzedaży w
 * internecie" i obiecujący sklep „w kilka minut" brzmiałby jak reklama cudzej
 * usługi wysłana pod zły adres.
 */
class ActivationMailer
{
    /**
     * Zwraca adres linku aktywacyjnego — ten SAM, który poszedł w mailu.
     *
     * Potrzebne przy wdrożeniu: `DeploymentSeeder` wypisuje go na konsolę, bo
     * chodzi zaraz po `migrate`, gdy SMTP bywa nieskonfigurowany, a crona (który
     * jako jedyny opróżnia outbox) jeszcze nie ma. Wygenerowanie drugiego tokenu
     * unieważniłoby pierwszy, więc link musi wyjść stąd, a nie powstać osobno.
     */
    public function send(User $user): string
    {
        $token = Password::broker('activation')->createToken($user);

        $url = route('activation.show', [
            'token' => $token,
            'email' => $user->email,
        ]);

        EmailMessage::create([
            'priority' => MailPriority::High,
            'to_email' => $user->email,
            'to_name' => trim($user->name.' '.$user->surname),
            'greeting' => Vocative::greeting($user->name),
            'outro_lines' => [
                'Link jest ważny przez 24 godziny.',
            ],
            ...(Mode::dedicated() ? $this->dedicatedCopy($url) : $this->saasCopy($url)),
        ]);

        return $url;
    }

    /**
     * Kramio: ktoś właśnie wypełnił formularz rejestracji i czeka.
     *
     * @return array<string, mixed>
     */
    private function saasCopy(string $url): array
    {
        $app = config('app.name');

        return [
            'subject' => 'Witaj w '.$app.' — dokończ zakładanie swojego sklepu',
            'preheader' => 'Ustaw hasło i otwórz swój sklep — to ostatni krok.',
            'heading' => 'Witaj w '.$app.'!',
            'intro_lines' => [
                'Dziękujemy za rejestrację i gratulujemy decyzji — właśnie zrobiłeś pierwszy krok do sprzedaży własnych produktów w internecie.',
                'W '.$app.' postawisz swój sklep w kilka minut, bez wiedzy technicznej: dostajesz własny adres, stronę gotową do sprzedaży i pełną kontrolę nad ofertą. O resztę — adres, koszyk, zamówienia — zadbamy my, żebyś Ty mógł skupić się na tym, co robisz najlepiej.',
                'Został ostatni krok: ustaw hasło i potwierdź swoje dane. Zaraz potem dodasz pierwszy produkt i otworzysz sklep dla klientów.',
            ],
            'action_text' => 'Dokończ zakładanie sklepu',
            'action_url' => $url,
        ];
    }

    /**
     * Sklep dedykowany: sklep już stoi, właściciel dostaje do niego klucze.
     *
     * Bez słowa o rejestracji, platformie i zakładaniu sklepu „w kilka minut".
     * Zamiast obietnicy — konkretna lista tego, co trzeba zrobić przed otwarciem,
     * bo to jest realne pytanie osoby, która pierwszy raz siada do panelu.
     *
     * @return array<string, mixed>
     */
    private function dedicatedCopy(string $url): array
    {
        $shop = config('app.name');

        return [
            'subject' => $shop.' — ustaw hasło do panelu sklepu',
            'preheader' => 'Twój sklep jest gotowy. Ustaw hasło i zaloguj się.',
            'heading' => 'Twój sklep jest gotowy',
            'intro_lines' => [
                'Sklep '.$shop.' jest zainstalowany i czeka na Ciebie. Zostało ustawić własne hasło do panelu — z tego linku.',
                'W panelu dodasz produkty, ustawisz sposoby dostawy i płatności, uzupełnisz regulamin oraz politykę prywatności, a gdy wszystko będzie gotowe — opublikujesz sklep jednym przyciskiem. Do tego czasu widzisz go tylko Ty.',
            ],
            'action_text' => 'Ustaw hasło',
            'action_url' => $url,
        ];
    }
}
