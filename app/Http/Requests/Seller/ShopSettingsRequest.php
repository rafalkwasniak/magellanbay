<?php

namespace App\Http\Requests\Seller;

use App\Enums\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Walidacja ustawień sklepu. Na razie domyślna stawka VAT (z enum VatRate).
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
            'google_analytics_enabled' => $this->boolean('google_analytics_enabled'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'default_vat_rate' => ['required', Rule::enum(VatRate::class)],
            'bank_transfer_enabled' => ['boolean'],
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
            'bank_transfer_enabled' => 'przelew na konto',
            'google_analytics_enabled' => 'Google Analytics',
        ];
    }
}
