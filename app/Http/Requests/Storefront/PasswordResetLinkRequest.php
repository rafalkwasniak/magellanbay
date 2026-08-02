<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Prośba klienta o link do zmiany hasła — sam adres e-mail.
 *
 * Bez reguły `exists`: sprawdzanie istnienia konta w walidacji zamieniłoby
 * formularz w narzędzie do ustalania, kto kupuje w tym sklepie.
 */
class PasswordResetLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'adres e-mail'];
    }
}
