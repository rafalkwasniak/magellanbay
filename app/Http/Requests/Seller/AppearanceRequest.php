<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Walidacja wyglądu sklepu — na razie logo (i flaga jego usunięcia). Docelowo
 * dojdą tu kolory i wybór szablonu. Sprzedawca edytuje wyłącznie własny sklep.
 */
class AppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->shop !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048',
                Rule::dimensions()->minWidth(100)->minHeight(100)->maxWidth(2000)->maxHeight(2000),
            ],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'logo' => 'logo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.image' => 'Logo musi być obrazem (PNG, JPG lub WebP).',
            'logo.mimes' => 'Logo musi być w formacie PNG, JPG lub WebP.',
            'logo.max' => 'Logo może mieć maksymalnie 2 MB.',
            'logo.dimensions' => 'Logo powinno mieć od 100 do 2000 px boku.',
        ];
    }
}
