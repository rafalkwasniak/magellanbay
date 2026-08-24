<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Models\User;
use App\Support\Vocative;

/**
 * Wiadomość serwisowa od platformy do JEDNEGO sprzedawcy — awaria, sprawa
 * konta, odpowiedź na zgłoszenie. Pisana z konsoli admina, z karty sklepu.
 *
 * DLACZEGO OSOBNA ŚCIEŻKA, A NIE {@see PlatformMailService}:
 *
 * Tamten serwis obsługuje treści HANDLOWE i dlatego bramkuje odbiorców zgodą
 * marketingową (`User::activeMarketingConsent()`) oraz dokleja stopkę wypisu.
 * Ten serwis robi coś przeciwnego i robi to CELOWO:
 *
 * 1. **Zgody NIE sprawdzamy.** Wiadomość niezbędna do wykonania umowy — awaria,
 *    zmiana regulaminu, sprawa płatności — należy się każdemu sprzedawcy
 *    niezależnie od tego, czy zgodził się na oferty. Zgoda marketingowa nie ma
 *    prawa blokować informacji o usterce w cudzym sklepie. Gdyby ktoś kiedyś
 *    „naprawiał" to, dokładając tu bramkę zgody — to nie jest luka, to jest
 *    sens tej klasy.
 * 2. **Bez stopki wypisu.** Nie da się wypisać z informacji o awariach, więc
 *    link sugerujący, że się da, byłby wprowadzeniem w błąd.
 *
 * Granica jest w treści, nie w kodzie: tędy NIE wolno wysyłać nowości, ofert
 * ani kodów rabatowych — od tego jest dział „Wiadomości" i zgoda.
 */
class SellerNoticeService
{
    /**
     * Kolejkuje wiadomość do właściciela sklepu. Wysyłką zajmuje się cron
     * outboxu (`email:dispatch`), jak przy każdym innym mailu w projekcie.
     *
     * @param  User|null  $from  administrator, na którego adres pójdzie odpowiedź
     */
    public function send(Shop $shop, string $subject, string $body, ?User $from = null): EmailMessage
    {
        $owner = $shop->owner;

        return EmailMessage::create([
            // Mid, nie Low: sprawa serwisowa nie może stać w kolejce za
            // zaległym mailingiem handlowym. Niżej niż faktura i aktywacja.
            'priority' => MailPriority::Mid,
            // Pisze PLATFORMA, nie sklep — puste `shop_id` daje tożsamość
            // i szatę Kramio. Mail od „sklepu" do jego własnego właściciela
            // byłby bez sensu.
            'shop_id' => null,
            'to_email' => $owner->email,
            'to_name' => $owner->name,
            // Odpowiedź ma trafić do człowieka, który pisał, a nie w próżnię
            // adresu systemowego — mail serwisowy zaprasza do odpisania.
            'reply_to' => $from?->email,
            'subject' => $subject,
            'heading' => Vocative::headline($owner->name),
            'greeting' => null,
            'body_html' => $body,
        ]);
    }
}
