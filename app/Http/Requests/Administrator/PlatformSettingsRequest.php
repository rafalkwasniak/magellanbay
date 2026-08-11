<?php

namespace App\Http\Requests\Administrator;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Przełączniki operacyjne platformy. Walidacja wyłącznie tutaj (FOUNDATION sek. 5).
 */
class PlatformSettingsRequest extends FormRequest
{
    /** Dostęp pilnuje middleware `admin` na grupie tras. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'registration_open' => ['nullable', 'boolean'],
            // Baner ma być krótkim komunikatem, nie stroną. Limit trzyma go w
            // jednej, czytelnej linijce na każdym ekranie centrali.
            'maintenance_notice' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * Puste pole = brak baneru. Bez tego zapisany biały znak włączałby pustą
     * pomarańczową belkę na całej platformie.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'maintenance_notice' => trim((string) $this->input('maintenance_notice')),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'registration_open' => 'rejestracja sprzedawców',
            'maintenance_notice' => 'komunikat o przerwie',
        ];
    }
}
