<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ValidatesPassword;
use App\Services\PhoneService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Walidacja formularza aktywacji. `token_email` to adres, na który wystawiono
 * token (ukryty) — po nim broker uwierzytelnia. E-mail, nazwa i adres sklepu są
 * stałe (pola disabled w widoku), więc nie przyjmujemy ich tu do zmiany.
 */
class ActivationRequest extends FormRequest
{
    use ValidatesPassword;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizacja przed walidacją: telefon do postaci kanonicznej (48 + 9 cyfr).
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
            'token' => ['required', 'string'],
            'token_email' => ['required', 'string', 'email'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'surname' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['nullable', 'regex:/^48[0-9]{9}$/'], // 48 + 9 cyfr (po normalizacji)
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
            'privacy' => ['accepted'],
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
            'phone' => 'telefon',
            'password' => 'hasło',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'phone.regex' => 'Podaj prawidłowy numer telefonu (9 cyfr).',
            'terms.accepted' => 'Musisz zaakceptować Regulamin.',
            'privacy.accepted' => 'Musisz zaakceptować Politykę Prywatności.',
        ], $this->passwordMessages());
    }
}
