<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\Customer;
use App\Models\EmailMessage;
use App\Support\Vocative;
use Illuminate\Support\Facades\URL;

/**
 * Kolejkuje mail z linkiem do zmiany hasła konta KLIENTA — brandowany kolorami
 * i tożsamością sklepu, jak reszta korespondencji „od sklepu".
 *
 * Link jest PODPISANY i wygasający, tak samo jak przy aktywacji, a nie oparty
 * na tokenie brokera. Powód jest architektoniczny: konta klientów są per sklep,
 * więc ten sam adres e-mail może mieć konto u kilku sprzedawców. Broker szuka
 * użytkownika po samym adresie i trafiłby w niewłaściwe konto; podpisany link
 * niesie identyfikator klienta, który jest globalnie unikalny.
 */
class CustomerPasswordResetMailer
{
    public function send(Customer $customer): void
    {
        $customer->loadMissing('shop');
        $shop = $customer->shop;

        $minutes = (int) config('auth.passwords.users.expire', 60);

        $url = URL::temporarySignedRoute(
            'storefront.password.reset',
            now()->addMinutes($minutes),
            ['shop' => $shop->slug, 'customer' => $customer->id],
        );

        EmailMessage::create([
            'priority' => MailPriority::High,
            'shop_id' => $shop->id,
            'from_name' => $shop->name,
            'reply_to' => $shop->contact_email,
            'to_email' => $customer->email,
            'to_name' => trim($customer->name.' '.$customer->surname),
            'subject' => 'Zmiana hasła — '.$shop->name,
            'preheader' => 'Link do ustawienia nowego hasła.',
            'heading' => 'Ustaw nowe hasło',
            'greeting' => Vocative::greeting($customer->name),
            'intro_lines' => [
                'Ktoś poprosił o zmianę hasła do Twojego konta w **'.$shop->name.'**.',
                'Kliknij przycisk poniżej, żeby ustawić nowe hasło.',
            ],
            'action_text' => 'Ustaw nowe hasło',
            'action_url' => $url,
            'outro_lines' => [
                'Link jest ważny przez '.$minutes.' minut.',
                'Jeśli to nie Ty prosiłeś o zmianę, zignoruj tę wiadomość — Twoje hasło pozostanie bez zmian.',
            ],
        ]);
    }
}
