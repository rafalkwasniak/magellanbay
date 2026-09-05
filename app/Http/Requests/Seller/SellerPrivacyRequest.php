<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Kreator wzoru POLITYKI PRYWATNOŚCI — bliźniak {@see SellerTermsRequest}.
 *
 * Pól jest mniej i to nie przypadek: polityka w ~90% opisuje infrastrukturę
 * (jakie dane płyną, do kogo, na jak długo), a nie biznes sprzedawcy. Odbiorców
 * danych wyliczamy z WŁĄCZONYCH integracji sklepu, więc pytamy wyłącznie o to,
 * czego nie da się odczytać: kto jest administratorem i jak się z nim
 * skontaktować.
 */
class SellerPrivacyRequest extends FormRequest
{
    /**
     * Normalizacja przed walidacją — ta sama konwencja co w regulaminie:
     * zbite spacje, e-mail małymi literami, NIP bez znaków nieliczbowych.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'seller_name' => Str::squish((string) $this->input('seller_name')),
            'nip' => preg_replace('/\D+/', '', (string) $this->input('nip')) ?: null,
            'address' => Str::squish((string) $this->input('address')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'phone' => Str::squish((string) $this->input('phone')) ?: null,
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'seller_name' => ['required', 'string', 'max:150'],
            // Bez sumy kontrolnej: działalność nierejestrowana NIE MA NIP-u,
            // a pustka jest tu poprawną odpowiedzią, nie brakiem.
            'nip' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'seller_name.required' => 'Podaj, kto odpowiada za dane — nazwę firmy albo imię i nazwisko. To administrator danych.',
            'address.required' => 'Podaj adres. Klient ma prawo wiedzieć, dokąd napisać w sprawie swoich danych.',
            'email.required' => 'Podaj adres e-mail — tędy idą żądania dostępu do danych, sprostowania i usunięcia.',
            'email.email' => 'Ten adres e-mail wygląda na nieprawidłowy.',
        ];
    }
}
