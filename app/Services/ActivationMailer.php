<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Kolejkuje mail aktywacyjny dla świeżo zarejestrowanego konta: token brokera
 * `activation` (ważny 24 h) + link do ustawienia hasła, wrzucone do outboxu z
 * priorytetem High (wyprzedza newslettery). Wysyłką zajmuje się cron.
 */
class ActivationMailer
{
    public function send(User $user): void
    {
        $token = Password::broker('activation')->createToken($user);

        $url = route('activation.show', [
            'token' => $token,
            'email' => $user->email,
        ]);

        $app = config('app.name');

        EmailMessage::create([
            'priority' => MailPriority::High,
            'to_email' => $user->email,
            'to_name' => trim($user->name.' '.$user->surname),
            'subject' => 'Witaj w '.$app.' — dokończ zakładanie swojego sklepu',
            'preheader' => 'Ustaw hasło i otwórz swój sklep — to ostatni krok.',
            'heading' => 'Witaj w '.$app.'!',
            'greeting' => 'Cześć '.$user->name.',',
            'intro_lines' => [
                'Dziękujemy za rejestrację i gratulujemy decyzji — właśnie zrobiłeś pierwszy krok do sprzedaży własnych produktów w internecie.',
                'W '.$app.' postawisz swój sklep w kilka minut, bez wiedzy technicznej: dostajesz własny adres, stronę gotową do sprzedaży i pełną kontrolę nad ofertą. O resztę — adres, koszyk, zamówienia — zadbamy my, żebyś Ty mógł skupić się na tym, co robisz najlepiej.',
                'Został ostatni krok: ustaw hasło i potwierdź swoje dane. Zaraz potem dodasz pierwszy produkt i otworzysz sklep dla klientów.',
            ],
            'action_text' => 'Dokończ zakładanie sklepu',
            'action_url' => $url,
            'outro_lines' => [
                'Link jest ważny przez 24 godziny.',
            ],
        ]);
    }
}
