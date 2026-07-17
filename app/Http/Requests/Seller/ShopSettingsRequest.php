<?php

namespace App\Http\Requests\Seller;

use App\Enums\SaleUnit;
use App\Enums\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Walidacja ustawień sklepu — domyślna stawka VAT i domyślna jednostka
 * sprzedaży (podpowiadane przy nowym produkcie), fiszki metod i włączniki.
 * Sprzedawca edytuje wyłącznie własny sklep.
 */
class ShopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->shop !== null;
    }

    /**
     * Włączniki usług to checkboxy — nieobecne w POST, gdy odznaczone.
     * Normalizujemy do jawnego boola, żeby zapis nie gubił wyłączenia metody.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'bank_transfer_enabled' => $this->boolean('bank_transfer_enabled'),
            'pickup_enabled' => $this->boolean('pickup_enabled'),
            'pay_on_pickup_enabled' => $this->boolean('pay_on_pickup_enabled'),
            'courier_enabled' => $this->boolean('courier_enabled'),
            // Kwoty kuriera: przecinek → kropka, spacje out (jak cena produktu).
            // Puste = null: koszt dopiero wymusimy regułą, gdy kurier włączony, a
            // próg pusty to świadomy „brak darmowej dostawy".
            'courier_cost' => $this->normalizeAmount($this->input('courier_cost')),
            'courier_free_from' => $this->normalizeAmount($this->input('courier_free_from')),
            'parcel_locker_enabled' => $this->boolean('parcel_locker_enabled'),
            'parcel_locker_cost' => $this->normalizeAmount($this->input('parcel_locker_cost')),
            'parcel_locker_free_from' => $this->normalizeAmount($this->input('parcel_locker_free_from')),
            'google_analytics_enabled' => $this->boolean('google_analytics_enabled'),
            'fakturownia_enabled' => $this->boolean('fakturownia_enabled'),
            // Nowe pole — gdy formularz go nie przyśle (starszy submit), zostawiamy
            // bieżącą jednostkę sklepu, żeby częściowy zapis jej nie wyzerował.
            'default_sale_unit' => $this->input('default_sale_unit', $this->user()?->shop?->default_sale_unit?->value ?? 'piece'),
        ]);
    }

    /**
     * Kwota z pola tekstowego → format akceptowany przez `numeric` (kropka
     * dziesiętna, bez spacji). Puste pole → null (kolumna nullable). Wzorzec z
     * ProductRequest, żeby „19,90" i „19.90" znaczyły to samo.
     */
    private function normalizeAmount(mixed $value): ?string
    {
        $normalized = str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'default_vat_rate' => ['required', Rule::enum(VatRate::class)],
            'default_sale_unit' => ['required', Rule::enum(SaleUnit::class)],
            'bank_transfer_enabled' => ['boolean'],
            'pickup_enabled' => ['boolean'],
            'pay_on_pickup_enabled' => ['boolean'],
            'courier_enabled' => ['boolean'],
            // Koszt wymagany dopiero, gdy kurier włączony (0 jest OK = gratis).
            'courier_cost' => [Rule::requiredIf($this->boolean('courier_enabled')), 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            // Próg opcjonalny (null = brak darmowej dostawy).
            'courier_free_from' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'parcel_locker_enabled' => ['boolean'],
            'parcel_locker_cost' => [Rule::requiredIf($this->boolean('parcel_locker_enabled')), 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'parcel_locker_free_from' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'google_analytics_enabled' => ['boolean'],
            'fakturownia_enabled' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'default_vat_rate' => 'domyślna stawka VAT',
            'default_sale_unit' => 'domyślna jednostka sprzedaży',
            'bank_transfer_enabled' => 'przelew na konto',
            'pickup_enabled' => 'odbiór osobisty',
            'pay_on_pickup_enabled' => 'płatność przy odbiorze',
            'courier_enabled' => 'dostawa kurierem',
            'courier_cost' => 'koszt dostawy kurierem',
            'courier_free_from' => 'próg darmowej dostawy',
            'parcel_locker_enabled' => 'dostawa do paczkomatu',
            'parcel_locker_cost' => 'koszt dostawy do paczkomatu',
            'parcel_locker_free_from' => 'próg darmowej dostawy do paczkomatu',
            'google_analytics_enabled' => 'Google Analytics',
            'fakturownia_enabled' => 'Fakturownia',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'courier_cost.required' => 'Podaj koszt dostawy kurierem (może być 0).',
            'courier_cost.numeric' => 'Podaj koszt liczbą, np. 15,99.',
            'courier_free_from.numeric' => 'Podaj kwotę progu liczbą, np. 200.',
            'parcel_locker_cost.required' => 'Podaj koszt dostawy do paczkomatu (może być 0).',
            'parcel_locker_cost.numeric' => 'Podaj koszt liczbą, np. 12,99.',
            'parcel_locker_free_from.numeric' => 'Podaj kwotę progu liczbą, np. 150.',
        ];
    }
}
