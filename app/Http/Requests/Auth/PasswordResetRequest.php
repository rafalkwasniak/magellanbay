<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ValidatesPassword;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ustawienie nowego hasła z linku. Wymagania dla samego hasła są wspólne dla
 * całej aplikacji (trait ValidatesPassword), żeby reset nie okazał się furtką
 * na słabsze hasło niż rejestracja.
 */
class PasswordResetRequest extends FormRequest
{
    use ValidatesPassword;

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
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->passwordMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'adres e-mail', 'password' => 'hasło'];
    }
}
