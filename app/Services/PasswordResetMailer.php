<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\User;
use App\Support\Vocative;
use Illuminate\Support\Facades\Password;

/**
 * Kolejkuje mail z linkiem do ustawienia nowego hasła dla konta w centrali
 * (sprzedawca, administrator). Token brokera `users` — ważny GODZINĘ, czyli
 * krócej niż 24 h przy aktywacji: aktywację odkłada się na wieczór, a hasło
 * odzyskuje się od razu, więc krótszy link to mniejsze okno dla kogoś, kto
 * zajrzy do cudzej skrzynki.
 *
 * Priorytet High, bo to wiadomość blokująca — użytkownik stoi przed ekranem
 * logowania i czeka.
 */
class PasswordResetMailer
{
    public function send(User $user): void
    {
        $token = Password::broker('users')->createToken($user);

        $url = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        $minutes = (int) config('auth.passwords.users.expire', 60);

        EmailMessage::create([
            'priority' => MailPriority::High,
            'to_email' => $user->email,
            'to_name' => trim($user->name.' '.$user->surname),
            'subject' => 'Zmiana hasła w '.config('app.name'),
            'preheader' => 'Link do ustawienia nowego hasła.',
            'heading' => 'Ustaw nowe hasło',
            'greeting' => Vocative::greeting($user->name),
            'intro_lines' => [
                'Ktoś poprosił o zmianę hasła do Twojego konta w '.config('app.name').'.',
                'Kliknij przycisk poniżej, żeby ustawić nowe hasło.',
            ],
            'action_text' => 'Ustaw nowe hasło',
            'action_url' => $url,
            'outro_lines' => [
                'Link jest ważny przez '.$minutes.' minut.',
                // Bez tego zdania mail brzmi jak alarm. Samo wysłanie prośby
                // niczego nie zmienia — stare hasło działa aż do ustawienia nowego.
                'Jeśli to nie Ty prosiłeś o zmianę, zignoruj tę wiadomość — Twoje hasło pozostanie bez zmian.',
            ],
        ]);
    }
}
