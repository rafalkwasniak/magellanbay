<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Prośba o link do zmiany hasła — sam adres e-mail.
 *
 * ŚWIADOMIE bez reguły `exists`: walidacja „nie znamy takiego adresu" zamieniłaby
 * formularz w wyszukiwarkę kont. Istnienie konta sprawdza dopiero kontroler i
 * niczego o nim nie mówi na ekranie.
 */
class PasswordResetLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Adres normalizujemy tak samo jak przy logowaniu — inaczej „Jan@Firma.PL"
     * nie znalazłby konta zapisanego małymi literami.
     */
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
