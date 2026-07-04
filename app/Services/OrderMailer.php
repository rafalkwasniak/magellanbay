<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\MailPriority;
use App\Enums\PaymentMethod;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\Shop;
use App\Support\Money;

/**
 * Kolejkuje maile związane ze złożeniem zamówienia (outbox → cron): potwierdzenie
 * dla klienta i powiadomienie dla sprzedawcy. Priorytet Mid (potwierdzenie, nie
 * pilna sprawa jak reset hasła, ale nie newsletter). Treść budowana w blokach —
 * każda pozycja i sekcja w osobnej linii, dla czytelności. Maile o zmianie
 * statusu oraz „Realizuj zamówienie" dojdą w module statusów. `shop_id` niesie
 * branding (per-sklep do podłączenia — patrz pamięć).
 */
class OrderMailer
{
    public function __construct(private PhoneService $phone) {}

    public function confirmToCustomer(Order $order): void
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Potwierdzenie zamówienia #'.$order->number.' — '.$shop->name,
            'preheader' => 'Otrzymaliśmy Twoje zamówienie. Dziękujemy!',
            'heading' => 'Dziękujemy za zamówienie!',
            'greeting' => 'Cześć '.$order->buyer_name.',',
            'intro_lines' => $this->blocks([
                [
                    'Otrzymaliśmy Twoje **zamówienie #'.$order->number.'** i już się nim zajmujemy.',
                    'Dziękujemy za zakupy w **'.$shop->name.'**!',
                ],
                array_merge(
                    ['**Zamówione produkty:**'],
                    $this->productLines($order),
                    ['Razem do zapłaty: **'.Money::pln($order->total_gross).'**'],
                ),
                $this->paymentBlock($order, $shop),
                $this->deliveryBlock($order, $shop),
                $this->companyBlock($order),
                $this->noteBlock($order, 'Uwagi:'),
            ]),
            'action_text' => 'Wróć do sklepu',
            'action_url' => 'https://'.$shop->host(),
            'outro_lines' => [
                'O kolejnych krokach (przygotowanie, gotowość do odbioru) poinformujemy Cię osobnym e-mailem.',
            ],
        ]);
    }

    public function notifySeller(Order $order): void
    {
        $order->loadMissing(['items', 'shop.owner']);
        $shop = $order->shop;
        $owner = $shop->owner;

        if ($owner === null) {
            return;
        }

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Nowe zamówienie #'.$order->number.' w '.$shop->name,
            'preheader' => 'Masz nowe zamówienie na kwotę '.Money::pln($order->total_gross).'.',
            'heading' => 'Nowe zamówienie #'.$order->number,
            'greeting' => 'Cześć '.$owner->name.',',
            'intro_lines' => $this->blocks([
                ['W Twoim sklepie **'.$shop->name.'** pojawiło się nowe **zamówienie #'.$order->number.'**.'],
                array_merge(
                    ['**Dane kupującego:**'],
                    ['Imię i nazwisko: '.trim($order->buyer_name.' '.$order->buyer_surname)],
                    ['E-mail: '.$order->buyer_email],
                    $order->buyer_phone ? ['Telefon: '.$this->phone->format($order->buyer_phone)] : [],
                ),
                $this->companyBlock($order),
                array_merge(
                    ['**Zamówione produkty:**'],
                    $this->productLines($order),
                    ['Wartość zamówienia: **'.Money::pln($order->total_gross).'**'],
                ),
                [
                    'Dostawa: '.$order->delivery_method->label(),
                    'Płatność: '.$order->payment_method->label(),
                ],
                $this->noteBlock($order, 'Uwagi klienta:'),
            ]),
            'outro_lines' => [
                'Obsługę statusów zamówienia udostępnimy wkrótce w panelu.',
            ],
        ]);
    }

    /**
     * Dane do faktury (gdy zakup firmowy): nazwa, NIP i adres firmy — każde w
     * osobnej linii. Adres tylko, gdy podany.
     *
     * @return list<string>
     */
    private function companyBlock(Order $order): array
    {
        if (! $order->is_company) {
            return [];
        }

        $address = trim(
            trim($order->company_street.' '.$order->company_building_number.($order->company_apartment_number ? '/'.$order->company_apartment_number : ''))
            .', '.trim($order->company_postal_code.' '.$order->company_city),
            ', '
        );

        return array_values(array_filter([
            '**Dane do faktury:**',
            'Firma: '.$order->company_name,
            'NIP: '.$order->company_nip,
            $address !== '' ? 'Adres: '.$address : null,
        ]));
    }

    /**
     * Blok „Uwagi" z notatką klienta (gdy podana). Nagłówek pogrubiony, treść
     * notatki w osobnej linii. Etykieta różni się między mailem klienta a
     * sprzedawcy.
     *
     * @return list<string>
     */
    private function noteBlock(Order $order, string $label): array
    {
        if (! filled($order->note)) {
            return [];
        }

        return ['**'.$label.'**', $order->note];
    }

    /**
     * Odfiltrowuje puste bloki (np. brak danych firmy czy notatki), by nie
     * zostawić pustego akapitu w mailu. Każdy pozostały blok to tablica linii,
     * którą komponent renderuje jako jeden akapit (linie sklejone <br>).
     *
     * @param  list<list<string>>  $blocks
     * @return list<list<string>>
     */
    private function blocks(array $blocks): array
    {
        return array_values(array_filter($blocks, fn (array $block): bool => $block !== []));
    }

    /**
     * Tożsamość nadawcy „od sklepu", wspólna dla maili zamówienia: display-name
     * koperty = nazwa sklepu, Reply-To = e-mail kontaktowy sklepu. From-address
     * zostaje nasz (deliverability) — renderer składa to w `OutboxMailable`.
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

    /**
     * Pozycje jako osobne linie: „• 2 × Nazwa — 40,00 zł".
     *
     * @return list<string>
     */
    private function productLines(Order $order): array
    {
        return $order->items
            ->map(fn ($item): string => '• '.$item->quantity.' × '.$item->name.' — '.Money::pln($item->line_total_gross))
            ->values()
            ->all();
    }

    /**
     * Blok płatności jako osobne linie (metoda + dane do przelewu / info o odbiorze).
     *
     * @return list<string>
     */
    private function paymentBlock(Order $order, mixed $shop): array
    {
        if ($order->payment_method === PaymentMethod::BankTransfer) {
            return array_values(array_filter([
                'Sposób płatności: Przelew na konto'.(filled($shop->bank_name) ? ' ('.$shop->bank_name.')' : ''),
                $shop->formattedBankAccountNumber() ? 'Numer konta: '.$shop->formattedBankAccountNumber() : null,
                'Tytuł przelewu: **Zamówienie #'.$order->number.'**',
                'Kwota: **'.Money::pln($order->total_gross).'**',
            ]));
        }

        return ['Sposób płatności: '.$order->payment_method->label().' — zapłacisz na miejscu przy odbiorze.'];
    }

    /**
     * Blok dostawy jako osobne linie (metoda + adres odbioru, jeśli odbiór).
     *
     * @return list<string>
     */
    private function deliveryBlock(Order $order, mixed $shop): array
    {
        $lines = ['Sposób dostawy: '.$order->delivery_method->label()];

        $address = trim(
            trim($shop->street.' '.$shop->building_number.($shop->apartment_number ? '/'.$shop->apartment_number : ''))
            .', '.trim($shop->postal_code.' '.$shop->city),
            ', '
        );

        if ($order->delivery_method === DeliveryMethod::Pickup && $address !== '') {
            $lines[] = 'Adres odbioru: '.$address;
        }

        return $lines;
    }
}
