<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\MailPriority;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\Shop;
use App\Support\Money;
use App\Support\Vocative;

/**
 * Kolejkuje maile zamówienia (outbox → cron): potwierdzenie dla klienta,
 * powiadomienie dla sprzedawcy i informacja o każdej zmianie statusu. Priorytet
 * Mid (potwierdzenie, nie pilna sprawa jak reset hasła, ale nie newsletter).
 * Treść budowana w blokach — każda pozycja i sekcja w osobnej linii, dla
 * czytelności. `shop_id` niesie branding per-sklep.
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
            'greeting' => Vocative::greeting($order->buyer_name),
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

    /**
     * Mail z gotową fakturą VAT — wysyłany przez NASZ system (nie przez
     * Fakturownię), dla spójności brandu. Przycisk prowadzi wprost do publicznego
     * PDF-a w Fakturowni (link tokenowy, bez logowania). Wołany przez job po
     * zapisaniu śladu FV, więc `invoicePdfUrl()` jest już zbudowany.
     */
    public function invoiceReady(Order $order): void
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;
        $numberSuffix = filled($order->invoice_number) ? ' nr '.$order->invoice_number : '';

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Faktura VAT'.$numberSuffix.' do zamówienia #'.$order->number.' — '.$shop->name,
            'preheader' => 'Twoja faktura VAT do zamówienia #'.$order->number.' jest gotowa.',
            'heading' => 'Twoja Faktura VAT',
            'greeting' => Vocative::greeting($order->buyer_name),
            'intro_lines' => $this->blocks([
                [
                    'Do **zamówienia #'.$order->number.'** w sklepie **'.$shop->name.'** wystawiliśmy fakturę VAT'.($numberSuffix !== '' ? ' **'.trim($numberSuffix).'**' : '').'.',
                    'Kwota: **'.Money::pln($order->total_gross).'**.',
                ],
                ['Fakturę pobierzesz przyciskiem poniżej — to bezpośredni link do pliku PDF.'],
            ]),
            'action_text' => 'Pobierz fakturę VAT',
            'action_url' => $order->invoicePdfUrl(),
            'outro_lines' => [
                'Masz pytania do faktury? Odpowiedz na tego e-maila — trafi wprost do sklepu.',
            ],
        ]);
    }

    /**
     * Mail do kupującego o KAŻDEJ zmianie statusu — bez wyjątków i bez opcji
     * wyłączenia. Także przy cofnięciu statusu: klient musi wiedzieć, bo inaczej
     * przyjedzie odebrać coś, czego nie ma. Niesie całe zamówienie, nowy status,
     * datę jego ustawienia i notatkę sprzedawcy (jeśli była).
     */
    public function statusChanged(Order $order, OrderStatusEvent $event): void
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;
        $status = $event->to_status;

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Zamówienie #'.$order->number.': '.$status->label().' — '.$shop->name,
            'preheader' => 'Nowy status Twojego zamówienia: '.$status->label().'.',
            'heading' => $status->label(),
            'greeting' => Vocative::greeting($order->buyer_name),
            'intro_lines' => $this->blocks([
                [
                    'Status Twojego **zamówienia #'.$order->number.'** w sklepie **'.$shop->name.'** zmienił się na: **'.$status->label().'**.',
                    'Data zmiany: '.$event->created_at->format('d.m.Y, H:i').'.',
                ],
                $this->eventNoteBlock($event),
                array_merge(
                    ['**Twoje zamówienie:**'],
                    $this->productLines($order),
                    ['Razem: **'.Money::pln($order->total_gross).'**'],
                ),
                // Pełne dane do przelewu tylko wtedy, gdy pieniądze wciąż są
                // oczekiwane — w mailu o „Zrealizowane" numer konta to szum.
                $status === OrderStatus::AwaitingPayment
                    ? $this->paymentBlock($order, $shop)
                    : ['Sposób płatności: '.$order->payment_method->label()],
                $this->deliveryBlock($order, $shop),
            ]),
            'action_text' => 'Wróć do sklepu',
            'action_url' => 'https://'.$shop->host(),
        ]);
    }

    /**
     * Mail o anulowaniu — osobny od `statusChanged`, choć anulowanie też jest
     * zmianą statusu. Powód: tamten niesie „co dalej" (adres odbioru, dane do
     * przelewu), a tu nie ma żadnego „dalej". Zapraszanie po odbiór zamówienia,
     * które właśnie anulowaliśmy, byłoby okrutne. Zostaje sucha informacja: co
     * anulowano, za ile i dlaczego — plus wskazanie, gdzie pytać, gdy to pomyłka.
     */
    public function cancelled(Order $order, OrderStatusEvent $event): void
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Zamówienie #'.$order->number.' zostało anulowane — '.$shop->name,
            'preheader' => 'Twoje zamówienie #'.$order->number.' zostało anulowane.',
            'heading' => 'Zamówienie anulowane',
            'greeting' => Vocative::greeting($order->buyer_name),
            'intro_lines' => $this->blocks([
                [
                    'Twoje **zamówienie #'.$order->number.'** w sklepie **'.$shop->name.'** zostało anulowane.',
                    'Data anulowania: '.$event->created_at->format('d.m.Y, H:i').'.',
                ],
                $this->cancelReasonBlock($event),
                array_merge(
                    ['**Anulowane zamówienie obejmowało:**'],
                    $this->productLines($order),
                    ['Na kwotę: **'.Money::pln($order->total_gross).'**'],
                ),
            ]),
            'outro_lines' => [
                'Jeśli to pomyłka lub masz pytania — odpowiedz na tego e-maila, trafi wprost do sklepu.',
            ],
        ]);
    }

    /**
     * Wiadomość od sprzedawcy do kupującego, pisana z ręki w panelu. Do treści
     * dokładamy pozycje zamówienia z kwotami: wiadomość zwykle dotyczy któregoś
     * z produktów, a klient nie musi wtedy szukać po skrzynce potwierdzenia,
     * żeby wiedzieć, o czym mowa.
     *
     * Reply-To niesie adres kontaktowy sklepu (`senderIdentity`), więc zachęta
     * do odpowiadania na końcu jest obietnicą z pokryciem — odpowiedź faktycznie
     * trafi do sprzedawcy, a nie w próżnię.
     */
    public function messageToCustomer(Order $order, string $body): void
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Wiadomość w sprawie zamówienia #'.$order->number.' — '.$shop->name,
            'preheader' => 'Masz wiadomość od sklepu '.$shop->name.'.',
            'heading' => 'Wiadomość od sklepu',
            'greeting' => Vocative::greeting($order->buyer_name),
            'intro_lines' => $this->blocks($this->messageBlocks(
                $order,
                $body,
                'Twoje zamówienie #'.$order->number.':',
            )),
            'outro_lines' => [
                'Chcesz o coś dopytać? Odpowiedz na tę wiadomość przyciskiem „Odpowiedz" w swojej skrzynce — dzięki temu cała nasza rozmowa zostanie w jednym wątku.',
            ],
        ]);
    }

    /**
     * Kopia wiadomości na skrzynkę sprzedawcy — tylko na jego wyraźne życzenie
     * („Wyślij kopię do mnie"). Od pierwszej linii mówi, że to kopia: bez tego
     * sprzedawca zobaczyłby w skrzynce własny tekst i musiał zgadywać, czy to
     * przypadkiem nie odpowiedź klienta.
     */
    public function messageCopyToSeller(Order $order, string $body): void
    {
        $order->loadMissing(['items', 'shop.owner']);
        $shop = $order->shop;
        $owner = $shop->owner;

        if ($owner === null) {
            return;
        }

        $buyer = trim($order->buyer_name.' '.$order->buyer_surname);

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Kopia: wiadomość do klienta — zamówienie #'.$order->number,
            'preheader' => 'Kopia wiadomości wysłanej do '.$buyer.'.',
            'heading' => 'Kopia wysłanej wiadomości',
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => $this->blocks(array_merge(
                [[
                    'To kopia wiadomości wysłanej do **'.$buyer.'** ('.$order->buyer_email.') w sprawie **zamówienia #'.$order->number.'** w sklepie **'.$shop->name.'**.',
                    'Odpowiedź klienta trafi na adres kontaktowy sklepu.',
                ]],
                $this->messageBlocks($order, $body, 'Zamówienie #'.$order->number.':'),
            )),
        ]);
    }

    /**
     * Wspólny trzon wiadomości od sprzedawcy: jego tekst, a pod nim pozycje
     * zamówienia z sumą. Kopia dla sprzedawcy używa tego samego trzonu co mail
     * klienta — kopia ma pokazywać to, co klient dostał, a nie streszczenie.
     *
     * @return list<list<string>>
     */
    private function messageBlocks(Order $order, string $body, string $productsLabel): array
    {
        return array_merge(
            $this->bodyBlocks($body),
            [array_merge(
                ['**'.$productsLabel.'**'],
                $this->productLines($order),
                ['Razem: **'.Money::pln($order->total_gross).'**'],
            )],
        );
    }

    /**
     * Tekst z textarei na bloki maila: pusta linia rozdziela akapity, a pojedyncze
     * złamanie zostaje wewnątrz akapitu (komponent sklei je `<br>`). Dzięki temu
     * wiadomość dociera w takim kształcie, w jakim sprzedawca ją napisał.
     *
     * Treść jest escapowana dopiero przy renderowaniu (`MailMarkup::inline`), więc
     * tekst sprzedawcy nie wstrzyknie HTML-u — najwyżej pokaże dosłowne `**`.
     *
     * @return list<list<string>>
     */
    private function bodyBlocks(string $body): array
    {
        $paragraphs = preg_split('/\R\s*\R/', trim($body)) ?: [];

        return array_values(array_filter(array_map(
            fn (string $paragraph): array => array_values(array_filter(
                array_map(trim(...), preg_split('/\R/', $paragraph) ?: []),
                fn (string $line): bool => $line !== '',
            )),
            $paragraphs,
        ), fn (array $block): bool => $block !== []));
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
            'greeting' => Vocative::greeting($owner->name),
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
                'Zamówienie znajdziesz w panelu, w zakładce Zamówienia — tam ustawisz jego status.',
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
     * notatki z zachowaniem akapitów. Etykieta różni się między mailem klienta a
     * sprzedawcy.
     *
     * @return list<string>
     */
    private function noteBlock(Order $order, string $label): array
    {
        return $this->multilineBlock($label, $order->note);
    }

    /**
     * Notatka sprzedawcy dopięta do konkretnej zmiany statusu (nie mylić z
     * uwagami klienta z kasy — `noteBlock`). Pomijana, gdy pusta.
     *
     * @return list<string>
     */
    private function eventNoteBlock(OrderStatusEvent $event): array
    {
        return $this->multilineBlock('Wiadomość od sklepu:', $event->note);
    }

    /**
     * Powód anulowania (notatka zdarzenia). Sprzedawca może go nie podać —
     * wtedy blok znika i mail nie udaje, że wyjaśnia.
     *
     * @return list<string>
     */
    private function cancelReasonBlock(OrderStatusEvent $event): array
    {
        return $this->multilineBlock('Powód anulowania:', $event->note);
    }

    /**
     * Blok „nagłówek + wieloliniowa treść użytkownika" (uwagi, wiadomość sklepu,
     * powód anulowania). Treść bez wpisu → blok znika. Kluczowe: rozbijamy tekst
     * na osobne linie, bo komponent skleja je przez `<br>` — inaczej znaki nowej
     * linii zjada HTML i wielolinijkowa notatka zlewa się w jedną ścianę tekstu.
     *
     * @return list<string>
     */
    private function multilineBlock(string $label, ?string $text): array
    {
        if (! filled($text)) {
            return [];
        }

        return array_merge(['**'.$label.'**'], $this->textLines((string) $text));
    }

    /**
     * Wieloliniowy tekst użytkownika → linie bloku maila. Pojedyncze przejścia do
     * nowej linii i JEDNA pusta linia między akapitami zostają (pusta linia daje
     * `<br><br>` = odstęp akapitu); nadmiarowe puste linie i te na brzegach
     * zwijamy, żeby mail nie dostał wielkich dziur.
     *
     * @return list<string>
     */
    private function textLines(string $text): array
    {
        $normalized = preg_replace(["/\r\n?/", "/\n{3,}/"], ["\n", "\n\n"], trim($text));

        return explode("\n", (string) $normalized);
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
     * Pozycje jako osobne linie: „• 2 szt. × Nazwa — 40,00 zł" (albo „2,50 kg ×…").
     *
     * @return list<string>
     */
    private function productLines(Order $order): array
    {
        return $order->items
            ->map(fn ($item): string => '• '.$item->sale_unit->formatQuantity((float) $item->quantity).' × '.$item->name.' — '.Money::pln($item->line_total_gross))
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

        if ($order->delivery_method === DeliveryMethod::Pickup) {
            $address = trim(
                trim($shop->street.' '.$shop->building_number.($shop->apartment_number ? '/'.$shop->apartment_number : ''))
                .', '.trim($shop->postal_code.' '.$shop->city),
                ', '
            );

            if ($address !== '') {
                $lines[] = 'Adres odbioru: '.$address;
            }

            return $lines;
        }

        if ($order->delivery_method->requiresShippingAddress()) {
            $address = trim(
                trim($order->ship_street.' '.$order->ship_building_number.($order->ship_apartment_number ? '/'.$order->ship_apartment_number : ''))
                .', '.trim($order->ship_postal_code.' '.$order->ship_city),
                ', '
            );

            if ($address !== '') {
                $lines[] = 'Adres dostawy: '.$address;
            }
        }

        if ($order->delivery_method->requiresParcelLocker() && filled($order->parcel_locker_code)) {
            // Kod pogrubiony: to jedyna dana, po której sprzedawca nadaje paczkę,
            // a klient odbiera. Opis punktu tylko gdy jest — przy wpisie z palca
            // (bez mapy) zamówienie niesie sam kod i nie ma czego dopowiadać.
            $lines[] = 'Paczkomat: **'.$order->parcel_locker_code.'**';

            if (filled($order->parcel_locker_address)) {
                $lines[] = $order->parcel_locker_address;
            }
        }

        if ($order->delivery_method->isShipped()) {
            $lines[] = 'Koszt dostawy: '.((float) $order->delivery_cost > 0 ? Money::pln($order->delivery_cost) : 'gratis');
        }

        return $lines;
    }
}
