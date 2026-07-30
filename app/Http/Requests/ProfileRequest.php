<?php

namespace App\Http\Requests;

use App\Services\PhoneService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Walidacja edycji własnego profilu. E-mail nie jest tu przyjmowany (zmiana
 * adresu logowania = osobny temat). Hasło opcjonalne — zmieniane tylko gdy
 * podane, i wtedy wymaga potwierdzenia aktualnego hasła.
 */
class ProfileRequest extends FormRequest
{
    use ValidatesPassword;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalizacja telefonu do postaci kanonicznej (48 + 9 cyfr).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => app(PhoneService::class)->normalize($this->input('phone'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'surname' => ['required', 'string', 'min:2', 'max:255'],
            // E-mail = login do platformy; unikalny w obrębie kont platformy (users),
            // z pominięciem własnego. Ten sam adres jako klient sklepu to osobna baza.
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['nullable', PhoneService::RULE],
            // Zgoda marketingowa: DOBROWOLNA, więc boolean (nie accepted).
            'marketing' => ['nullable', 'boolean'],
            'avatar' => [
                'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048',
                Rule::dimensions()->minWidth(100)->minHeight(100)->maxWidth(2000)->maxHeight(2000),
            ],
            'remove_avatar' => ['nullable', 'boolean'],
            // Nowe hasło opcjonalne; gdy podane — wymaga poprawnego aktualnego hasła.
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'imię',
            'surname' => 'nazwisko',
            'email' => 'adres e-mail',
            'phone' => 'telefon',
            'avatar' => 'awatar',
            'current_password' => 'aktualne hasło',
            'password' => 'nowe hasło',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'phone.regex' => PhoneService::RULE_MESSAGE,
            'email.unique' => 'Ten adres e-mail jest już zajęty.',
            'avatar.image' => 'Awatar musi być obrazem (PNG, JPG lub WebP).',
            'avatar.mimes' => 'Awatar musi być w formacie PNG, JPG lub WebP.',
            'avatar.max' => 'Awatar może mieć maksymalnie 2 MB.',
            'avatar.dimensions' => 'Awatar powinien mieć od 100 do 2000 px boku.',
            'current_password.required_with' => 'Podaj aktualne hasło, aby ustawić nowe.',
            'current_password.current_password' => 'Aktualne hasło jest nieprawidłowe.',
        ], $this->passwordMessages());
    }
}
