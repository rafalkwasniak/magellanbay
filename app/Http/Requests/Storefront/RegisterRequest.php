<?php

namespace App\Http\Requests\Storefront;

use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rejestracja klienta w sklepie — minimum danych: sam e-mail. Hasło ustawiane
 * później, z linku aktywacyjnego (ActivationRequest). Unikalność e-maila liczona
 * w obrębie SKLEPU (ten sam adres może mieć konto w innym sklepie). Sklep bierze
 * się z atrybutów żądania (middleware `tenant`/ResolveShop).
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function shop(): Shop
    {
        return $this->attributes->get('shop');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('customers', 'email')->where('shop_id', $this->shop()->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'adres e-mail',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ten adres ma już konto w tym sklepie — zaloguj się.',
        ];
    }
}
