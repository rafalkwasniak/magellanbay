<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Aktywacja konta klienta z linku mailowego: ustawienie pierwszego hasła oraz
 * (opcjonalnie) uzupełnienie danych profilu. Minimum to hasło + potwierdzenie;
 * imię/nazwisko/telefon są opcjonalne (klient może dopełnić je w profilu lub w
 * kasie). Autentyczność linku pilnuje middleware `signed` na trasie.
 */
class ActivationRequest extends FormRequest
{
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
            'password' => ['required', 'confirmed', Password::default()],
            'name' => ['nullable', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
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
        ];
    }
}
