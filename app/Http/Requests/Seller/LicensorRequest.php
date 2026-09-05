<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Dane licencjodawcy — firmy inkasującej opłatę za użycie swojego znaku.
 *
 * Wymagana jest wyłącznie NAZWA. Reszta to dane kontaktowe i numer umowy:
 * przydatne, ale sprzedawca dopisuje je, gdy je ma, a nie zanim doda partnera
 * do kartoteki. Wymuszanie ich na starcie kończy się wpisywaniem „—".
 */
class LicensorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->shop !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'contact_person' => Str::squish((string) $this->input('contact_person')) ?: null,
            'contact_email' => Str::lower(trim((string) $this->input('contact_email'))) ?: null,
            'agreement_reference' => Str::squish((string) $this->input('agreement_reference')) ?: null,
            'notes' => trim((string) $this->input('notes')) ?: null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shopId = $this->user()->shop->id;

        return [
            /*
             * Nazwa unikalna W OBRĘBIE SKLEPU. Dwa wpisy „Bieg Gdański" to przy
             * rozliczeniu dwie kupki pieniędzy dla jednej firmy — i reguła
             * „nie sumujemy, liczy się wyższa" przestaje działać, bo dla systemu
             * to dwaj różni partnerzy.
             */
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('licensors', 'name')
                    ->where('shop_id', $shopId)
                    ->ignore($this->route('licensor')?->id),
            ],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'string', 'email', 'max:150'],
            'agreement_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Podaj nazwę firmy — to ona pojawi się w rozliczeniu.',
            'name.unique' => 'Taki partner jest już w kartotece. Dwa wpisy o tej samej nazwie rozbiłyby rozliczenie na dwie części.',
            'contact_email.email' => 'Ten adres e-mail wygląda na nieprawidłowy.',
        ];
    }
}
