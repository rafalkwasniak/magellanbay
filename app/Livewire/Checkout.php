<?php

namespace App\Livewire;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Exceptions\CartNeedsReviewException;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\CompanyLookup;
use App\Services\NipService;
use App\Services\OrderService;
use App\Services\PhoneService;
use App\Support\Money;
use Illuminate\Validation\Rule;
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

    // Metody i uwagi.
    public string $delivery_method = '';
    public string $payment_method = '';
    public string $note = '';
    public bool $accept_terms = false;

    /** Komunikaty z finalnej weryfikacji (auto-korekta koszyka). */
    public array $reviewMessages = [];

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;

        $this->delivery_method = array_key_first($this->deliveryOptions()) ?? '';
        $this->payment_method = array_key_first($this->paymentOptions()) ?? '';
    }

    private function shop(): Shop
    {
        return Shop::findOrFail($this->shopId);
    }

    /**
     * Dostępne metody dostawy (tylko włączone i realnie gotowe). MVP: odbiór.
     *
     * @return array<string, string>
     */
    public function deliveryOptions(): array
    {
        $options = [];

        if ($this->shop()->pickupAvailable()) {
            $options[DeliveryMethod::Pickup->value] = DeliveryMethod::Pickup->label();
        }

        return $options;
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

        if ($shop->payOnPickupAvailable()) {
            $options[PaymentMethod::PayOnPickup->value] = PaymentMethod::PayOnPickup->label();
        }

        return $options;
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
            'buyer_phone' => ['required', 'string', 'max:30'],
            'delivery_method' => ['required', Rule::in(array_keys($this->deliveryOptions()))],
            'payment_method' => ['required', Rule::in(array_keys($this->paymentOptions()))],
            'note' => ['nullable', 'string', 'max:1000'],
            'accept_terms' => ['accepted'],
            'is_company' => ['boolean'],
        ];

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
            $order = $orders->place($this->shop(), $data);
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

        return view('livewire.checkout', [
            'lines' => $lines,
            'formattedTotal' => Money::pln($gross),
            'formattedNet' => Money::pln($net),
            'bankName' => $shop->bank_name,
            'pickupAddress' => $this->pickupAddress($shop),
            'deliveryOptions' => $this->deliveryOptions(),
            'paymentOptions' => $this->paymentOptions(),
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
