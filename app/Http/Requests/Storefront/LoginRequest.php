<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\ThrottlesLogins;
use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Logowanie klienta w obrębie sklepu. Samo uwierzytelnianie robi kontroler
 * (guard `customer`, scope `shop_id`); tutaj kształt danych i blokada po serii
 * nieudanych prób.
 *
 * Trasa ma dodatkowo `throttle` per IP, ale to inna ochrona: tamta ścina zalew
 * żądań z jednej maszyny, ta broni KONKRETNEGO konta przed zgadywaniem hasła
 * rozłożonym na wiele adresów.
 */
class LoginRequest extends FormRequest
{
    use ThrottlesLogins;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Klucz throttlingu: e-mail + sklep + IP. Sklep jest w kluczu, bo konta są
     * per sprzedawca — bez niego nieudane próby w jednym sklepie blokowałyby
     * logowanie tej samej osobie w cudzym.
     */
    public function throttleKey(): string
    {
        $shop = $this->attributes->get('shop');

        return Str::transliterate(
            Str::lower($this->string('email'))
            .'|'.($shop instanceof Shop ? $shop->id : 'brak')
            .'|'.$this->ip()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'adres e-mail',
            'password' => 'hasło',
        ];
    }
}
