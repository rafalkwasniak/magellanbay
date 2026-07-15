<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\Customer;
use App\Models\EmailMessage;
use App\Support\Vocative;
use Illuminate\Support\Facades\URL;

/**
 * Kolejkuje mail aktywacyjny konta KLIENTA (outbox → cron), brandowany kolorami i
 * tożsamością sklepu (jak maile zamówień). Link to podpisana, wygasająca trasa
 * `storefront.activation` na subdomenie sklepu — bezstanowo, per-klient (id
 * globalnie unikalne), więc bez kolizji tokenów przy tym samym e-mailu w wielu
 * sklepach. Odpowiednik `ActivationMailer` sprzedawcy, ale „od sklepu".
 */
class CustomerActivationMailer
{
    public function send(Customer $customer): void
    {
        $customer->loadMissing('shop');
        $shop = $customer->shop;

        $url = URL::temporarySignedRoute(
            'storefront.activation',
            now()->addMinutes((int) config('auth.passwords.activation.expire', 1440)),
            ['shop' => $shop->slug, 'customer' => $customer->id],
        );

        EmailMessage::create([
            'priority' => MailPriority::High,
            'shop_id' => $shop->id,
            'from_name' => $shop->name,
            'reply_to' => $shop->contact_email,
            'to_email' => $customer->email,
            'to_name' => trim($customer->name.' '.$customer->surname),
            'subject' => 'Aktywuj swoje konto — '.$shop->name,
            'preheader' => 'Ustaw hasło i dokończ zakładanie konta.',
            'heading' => 'Witaj w '.$shop->name.'!',
            'greeting' => Vocative::greeting($customer->name),
            'intro_lines' => [
                'Dziękujemy za założenie konta w **'.$shop->name.'**.',
                'Został ostatni krok: ustaw hasło do swojego konta. Zaraz potem zobaczysz historię swoich zamówień i szybciej złożysz kolejne.',
            ],
            'action_text' => 'Ustaw hasło i aktywuj konto',
            'action_url' => $url,
            'outro_lines' => [
                'Link jest ważny przez 24 godziny.',
                'Jeśli to nie Ty zakładałeś konto, po prostu zignoruj tę wiadomość.',
            ],
        ]);
    }
}
