<?php

namespace App\Livewire;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Exceptions\CartNeedsReviewException;
use App\Models\Customer;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\CompanyLookup;
use App\Services\NipService;
use App\Services\OrderService;
use App\Services\PhoneService;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Kasa (spec „Dane do zamówienia", „Rejestracja klienta → zakup bez
 * rejestracji"). Zbiera dane kupującego, wybór dostawy/płatności (tylko metody
 * włączone przez sprzedawcę) i składa zamówienie przez OrderService. Konto
 * klienta („Utwórz konto") oraz zgody z LegalDocument dojdą w module kont.
 */
class Checkout extends Component
{
    public int $shopId;

    // Dane kupującego.
    public string $buyer_name = '';
    public string $buyer_surname = '';
    public string $buyer_email = '';
    public string $buyer_phone = '';

    // Zakup jako firma (dane rozliczeniowe do FV — niezależne od dostawy).
    public bool $is_company = false;
    public string $company_name = '';
    public string $company_nip = '';
    public string $company_street = '';
    public string $company_building_number = '';
    public string $company_apartment_number = '';
    public string $company_postal_code = '';
    public string $company_city = '';

    // Adres dostawy (wypełniany tylko przy wysyłce — patrz shippedDelivery()).
    public string $ship_street = '';
    public string $ship_building_number = '';
    public string $ship_apartment_number = '';
    public string $ship_postal_code = '';
    public string $ship_city = '';

    // Metody i uwagi.
    public string $delivery_method = '';
    public string $payment_method = '';
    public string $note = '';
    public bool $accept_terms = false;

    public bool $accept_privacy = false;

    /** „Załóż konto" — działa tylko dla gościa z wolnym e-mailem (patrz resolveCustomer). */
    public bool $create_account = false;

    /** Komunikaty z finalnej weryfikacji (auto-korekta koszyka). */
    public array $reviewMessages = [];

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;

        $this->delivery_method = array_key_first($this->deliveryOptions()) ?? '';
        $this->payment_method = array_key_first($this->paymentOptions()) ?? '';

        // Zalogowany klient tego sklepu: uzupełnij dane z konta (edytowalne).
        if (($customer = $this->authCustomer()) !== null) {
            $this->buyer_name = $customer->name ?? '';
            $this->buyer_surname = $customer->surname ?? '';
            $this->buyer_email = $customer->email;
            $this->buyer_phone = $customer->phone ?? '';
        }
    }

    private function shop(): Shop
    {
        return Shop::findOrFail($this->shopId);
    }

    /**
     * Zalogowany klient TEGO sklepu (lub null). Do niego przypniemy zamówienie i
     * jego danymi wypełniamy kasę; guard `customer` jest scope'owany per sklep.
     */
    #[Computed]
    public function authCustomer(): ?Customer
    {
        $customer = Auth::guard('customer')->user();

        return $customer instanceof Customer && $customer->shop_id === $this->shopId ? $customer : null;
    }

    /**
     * Czy wpisany e-mail ma już konto w tym sklepie — wtedy zamiast „załóż konto"
     * pokazujemy informację, że zamówienie trafi do historii istniejącego konta.
     */
    #[Computed]
    public function accountExists(): bool
    {
        return filled($this->buyer_email)
            && $this->shop()->customers()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($this->buyer_email)])
                ->exists();
    }

    /**
     * Dostępne metody dostawy (tylko włączone i realnie gotowe). MVP: odbiór.
     *
     * @return array<string, string>
     */
    public function deliveryOptions(): array
    {
        $shop = $this->shop();
        $options = [];

        if ($shop->pickupAvailable()) {
            $options[DeliveryMethod::Pickup->value] = DeliveryMethod::Pickup->label();
        }

        if ($shop->courierAvailable()) {
            $options[DeliveryMethod::Courier->value] = DeliveryMethod::Courier->label();
        }

        return $options;
    }

    /**
     * Czy WYBRANA metoda dostawy wiąże się z wysyłką pod adres (kurier). Rozstrzyga
     * o widoczności i wymagalności bloku adresu oraz o kosztach dostawy.
     */
    public function shippedDelivery(): bool
    {
        return DeliveryMethod::tryFrom($this->delivery_method)?->isShipped() ?? false;
    }

    /**
     * Dostępne metody płatności (tylko włączone przez sprzedawcę).
     *
     * @return array<string, string>
     */
    public function paymentOptions(): array
    {
        $shop = $this->shop();
        $options = [];

        if ($shop->bankTransferAvailable()) {
            $options[PaymentMethod::BankTransfer->value] = PaymentMethod::BankTransfer->label();
        }

        // Płatność przy odbiorze ma sens tylko przy odbiorze osobistym — przy
        // wysyłce nie ma „odbioru", pod którym klient zapłaci. Kurier ⇒ przelew.
        if ($shop->payOnPickupAvailable() && ! $this->shippedDelivery()) {
            $options[PaymentMethod::PayOnPickup->value] = PaymentMethod::PayOnPickup->label();
        }

        return $options;
    }

    /**
     * Zmiana metody dostawy może zawęzić dostępne płatności (wybór kuriera zdejmuje
     * „płatność przy odbiorze"). Gdy bieżąca płatność wypadła z listy — przełącz na
     * pierwszą dostępną, żeby nie zostać z zaznaczeniem spoza opcji.
     */
    public function updatedDeliveryMethod(): void
    {
        $available = array_keys($this->paymentOptions());

        if (! in_array($this->payment_method, $available, true)) {
            $this->payment_method = $available[0] ?? '';
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'buyer_name' => ['required', 'string', 'max:100'],
            'buyer_surname' => ['required', 'string', 'max:100'],
            'buyer_email' => ['required', 'email', 'max:255'],
            'buyer_phone' => ['required', 'string', PhoneService::RULE],
            'delivery_method' => ['required', Rule::in(array_keys($this->deliveryOptions()))],
            'payment_method' => ['required', Rule::in(array_keys($this->paymentOptions()))],
            'note' => ['nullable', 'string', 'max:1000'],
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
            'is_company' => ['boolean'],
            'create_account' => ['boolean'],
        ];

        if ($this->shippedDelivery()) {
            $rules['ship_street'] = ['required', 'string', 'max:255'];
            $rules['ship_building_number'] = ['required', 'string', 'max:30'];
            $rules['ship_apartment_number'] = ['nullable', 'string', 'max:30'];
            $rules['ship_postal_code'] = ['required', 'string', 'max:12'];
            $rules['ship_city'] = ['required', 'string', 'max:120'];
        }

        if ($this->is_company) {
            $rules['company_name'] = ['required', 'string', 'max:255'];
            $rules['company_nip'] = ['required', function (string $attr, mixed $value, \Closure $fail): void {
                if (! app(NipService::class)->isValid((string) $value)) {
                    $fail('Podaj poprawny NIP.');
                }
            }];
            // Adres firmy — opcjonalny (pod przyszłą FV; wypełniany z NIP).
            $rules['company_street'] = ['nullable', 'string', 'max:255'];
            $rules['company_building_number'] = ['nullable', 'string', 'max:30'];
            $rules['company_apartment_number'] = ['nullable', 'string', 'max:30'];
            $rules['company_postal_code'] = ['nullable', 'string', 'max:12'];
            $rules['company_city'] = ['nullable', 'string', 'max:120'];
        }

        return $rules;
    }

    /**
     * Auto-uzupełnienie danych firmy po NIP (GUS → Biała lista MF), analogicznie
     * do panelu — tylko wprost z komponentu, bez endpointu. Nie znaleziono →
     * komunikat i ręczne uzupełnienie.
     */
    public function lookupCompany(): void
    {
        $nip = app(NipService::class)->normalize($this->company_nip);
        $this->company_nip = $nip ?? $this->company_nip;

        if ($nip === null || ! app(NipService::class)->isValid($nip)) {
            $this->addError('company_nip', 'Podaj poprawny NIP, aby pobrać dane.');

            return;
        }

        $data = app(CompanyLookup::class)->byNip($nip);

        if ($data === null) {
            $this->addError('company_nip', 'Nie znaleziono firmy dla tego NIP. Uzupełnij dane ręcznie.');

            return;
        }

        $this->company_name = $data['company_name'] ?? $this->company_name;
        $this->company_street = $data['street'] ?? '';
        $this->company_building_number = $data['building_number'] ?? '';
        $this->company_apartment_number = $data['apartment_number'] ?? '';
        $this->company_postal_code = $data['postal_code'] ?? '';
        $this->company_city = $data['city'] ?? '';
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'accept_terms.accepted' => 'Zaakceptuj regulamin, aby złożyć zamówienie.',
            'accept_privacy.accepted' => 'Zaakceptuj politykę prywatności, aby złożyć zamówienie.',
            'buyer_phone.regex' => PhoneService::RULE_MESSAGE,
        ];
    }

    /**
     * Czytelne nazwy pól w komunikatach walidacji — bez tego Laravel wstawia
     * surową nazwę właściwości („buyer phone"). Konwencja: interfejs po polsku.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'buyer_name' => 'imię',
            'buyer_surname' => 'nazwisko',
            'buyer_email' => 'e-mail',
            'buyer_phone' => 'telefon',
            'delivery_method' => 'sposób dostawy',
            'payment_method' => 'sposób płatności',
            'ship_street' => 'ulica',
            'ship_building_number' => 'numer budynku',
            'ship_apartment_number' => 'numer lokalu',
            'ship_postal_code' => 'kod pocztowy',
            'ship_city' => 'miejscowość',
            'note' => 'uwagi',
            'accept_terms' => 'regulamin',
            'accept_privacy' => 'polityka prywatności',
            'company_name' => 'nazwa firmy',
            'company_nip' => 'NIP',
            'company_street' => 'ulica',
            'company_building_number' => 'numer budynku',
            'company_apartment_number' => 'numer lokalu',
            'company_postal_code' => 'kod pocztowy',
            'company_city' => 'miejscowość',
        ];
    }

    public function place(OrderService $orders)
    {
        $this->buyer_phone = app(PhoneService::class)->normalize($this->buyer_phone) ?? $this->buyer_phone;

        if ($this->is_company) {
            $this->company_nip = app(NipService::class)->normalize($this->company_nip);
        }

        $data = $this->validate();

        try {
            $order = $orders->place($this->shop(), $data, $this->authCustomer());
        } catch (CartNeedsReviewException $e) {
            $this->reviewMessages = $e->messages;
            $this->dispatch('cart-updated');   // licznik i koszyk odświeżone

            return null;
        }

        // Numer zamówienia trzymamy w sesji — strona podziękowania czyta go stąd
        // (bez wystawiania cudzego zamówienia w URL).
        session()->put('recent_order_id', $order->id);

        return $this->redirect('/kasa/dziekujemy', navigate: false);
    }

    public function render()
    {
        $cart = app(CartService::class);
        $lines = $cart->lines($this->shopId);

        $gross = $lines->sum('line_total');
        $net = $lines->sum(fn (array $line): float => round(
            $line['line_total'] / (1 + $line['product']->vat_rate->fraction()), 2
        ));

        $shop = $this->shop();

        // Koszt dostawy zależny od wybranej metody (kurier: koszt z progiem
        // darmowej dostawy od wartości produktów; odbiór: 0). Suma = produkty + dostawa.
        $shipped = $this->shippedDelivery();
        $deliveryCost = $shipped ? $shop->courierCostFor($gross) : 0.0;

        $termsPage = $shop->pages()->where('is_system', true)->first();

        return view('livewire.checkout', [
            'lines' => $lines,
            'shippedDelivery' => $shipped,
            'deliveryCost' => $deliveryCost,
            'formattedDelivery' => $deliveryCost > 0 ? Money::pln($deliveryCost) : 'Gratis',
            'courierFreeFrom' => $shop->courier_free_from !== null ? (float) $shop->courier_free_from : null,
            'courierCostForCart' => $shop->courierAvailable() ? $shop->courierCostFor($gross) : null,
            'formattedTotal' => Money::pln($gross + $deliveryCost),
            'formattedNet' => Money::pln($net),
            'bankName' => $shop->bank_name,
            'pickupAddress' => $this->pickupAddress($shop),
            'deliveryOptions' => $this->deliveryOptions(),
            'paymentOptions' => $this->paymentOptions(),
            'termsUrl' => $termsPage?->storefrontPath(),
            'privacyUrl' => $shop->privacyPath(),
        ]);
    }

    /**
     * Adres odbioru = adres sklepu w jednym wierszu (do pokazania przy odbiorze
     * osobistym i płatności przy odbiorze).
     */
    private function pickupAddress(Shop $shop): string
    {
        $line = trim($shop->street.' '.$shop->building_number.($shop->apartment_number ? '/'.$shop->apartment_number : ''));

        return trim($line.', '.trim($shop->postal_code.' '.$shop->city), ', ');
    }
}
