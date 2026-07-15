<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Aktywacja konta klienta z linku mailowego: ustawienie pierwszego hasła oraz
 * (opcjonalnie) uzupełnienie danych profilu i zgoda marketingowa. Minimum to
 * hasło + potwierdzenie; imię/nazwisko/telefon są opcjonalne (klient może
 * dopełnić je w profilu lub w kasie). Autentyczność linku pilnuje middleware
 * `signed` na trasie.
 *
 * Zgoda marketingowa jest tu, bo to najmocniejszy moment na jej zebranie: klient
 * kliknął podpisany link ze swojej skrzynki, więc adres jest potwierdzony i zgoda
 * ma dowód. Niezaznaczony checkbox = brak zgody (nie wysyła się nic).
 */
class ActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Niezaznaczony checkbox nie trafia w ogóle do requestu — normalizujemy do
     * boola, żeby reguła miała co walidować (konwencja: boole w prepareForValidation).
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
            'password' => ['required', 'confirmed', Password::default()],
            'name' => ['nullable', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'marketing_email' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password' => 'hasło',
            'name' => 'imię',
            'surname' => 'nazwisko',
            'phone' => 'telefon',
            'marketing_email' => 'zgoda na e-maile',
        ];
    }
}
