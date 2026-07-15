<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Zmiana zgody marketingowej z profilu klienta. Jedno pole, ale przez Form
 * Request — walidacja mieszka wyłącznie tutaj (FOUNDATION sek. 5), a nie
 * w kontrolerze.
 */
class MarketingConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Odznaczony checkbox nie trafia do requestu — normalizujemy do boola, żeby
     * „brak pola" znaczyło jednoznacznie „wypisuję się", a nie „brak zmiany".
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'marketing_email' => $this->boolean('marketing_email'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'marketing_email' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'marketing_email' => 'zgoda na e-maile',
        ];
    }
}
