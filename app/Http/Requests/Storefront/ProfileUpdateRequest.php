<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aktualizacja danych profilu klienta (imię, nazwisko, telefon). E-mail jest
 * identyfikatorem logowania — nie zmieniamy go tutaj. Telefon normalizujemy jak
 * w kasie (kanoniczne 48 + 9 cyfr) — prepareForValidation + reguła PhoneService.
 */
class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => app(\App\Services\PhoneService::class)->normalize($this->input('phone')) ?? $this->input('phone'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', \App\Services\PhoneService::RULE],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => \App\Services\PhoneService::RULE_MESSAGE,
        ];
    }
}
