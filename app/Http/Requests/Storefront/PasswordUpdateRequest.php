<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Zmiana hasła klienta: weryfikacja bieżącego hasła (na guardzie `customer`) +
 * nowe hasło z potwierdzeniem. `current_password:customer` sprawdza hash konta
 * zalogowanego klienta, nie sprzedawcy (domyślny guard).
 */
class PasswordUpdateRequest extends FormRequest
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
            'current_password' => ['required', 'current_password:customer'],
            'password' => ['required', 'confirmed', Password::default()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'obecne hasło',
            'password' => 'nowe hasło',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Obecne hasło jest nieprawidłowe.',
        ];
    }
}
