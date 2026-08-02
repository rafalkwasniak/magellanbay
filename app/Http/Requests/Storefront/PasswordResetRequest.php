<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\ValidatesPassword;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ustawienie nowego hasła przez klienta. Te same wymagania co wszędzie indziej
 * (trait ValidatesPassword), żeby odzyskiwanie nie było furtką na słabsze hasło.
 */
class PasswordResetRequest extends FormRequest
{
    use ValidatesPassword;

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
        return ['password' => 'hasło'];
    }
}
