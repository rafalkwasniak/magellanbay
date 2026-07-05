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
            'google_analytics_enabled' => $this->boolean('google_analytics_enabled'),
            // Nowe pole — gdy formularz go nie przyśle (starszy submit), zostawiamy
            // bieżącą jednostkę sklepu, żeby częściowy zapis jej nie wyzerował.
            'default_sale_unit' => $this->input('default_sale_unit', $this->user()?->shop?->default_sale_unit?->value ?? 'piece'),
        ]);
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
            'google_analytics_enabled' => ['boolean'],
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
            'google_analytics_enabled' => 'Google Analytics',
        ];
    }
}
