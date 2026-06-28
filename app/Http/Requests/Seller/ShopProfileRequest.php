<?php

namespace App\Http\Requests\Seller;

use App\Services\NipService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Walidacja edycji profilu sklepu: nazwa (edytowalna), opis i dane adresowe.
 * Slug/subdomena NIE są tu edytowalne — zostają zabetonowane z rejestracji,
 * więc świadomie nie przyjmujemy ich do zmiany. Adres w osobnych, walidowanych
 * polach (spec: późniejsze użycie w dokumentach i integracjach).
 */
class ShopProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Sprzedawca edytuje wyłącznie własny sklep (ładowany z relacji usera).
        return $this->user()?->shop !== null;
    }

    /**
     * Normalizacja przed walidacją: kod pocztowy do postaci NN-NNN z samych cyfr.
     */
    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D/', '', (string) $this->input('postal_code'));

        if (strlen($digits) === 5) {
            $digits = substr($digits, 0, 2).'-'.substr($digits, 2);
        }

        $merge = ['postal_code' => $digits];

        if ($this->has('nip')) {
            $merge['nip'] = app(NipService::class)->normalize($this->input('nip'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:'.config('shop.description_max')],
            'company_name' => ['nullable', 'string', 'max:255'],
            'nip' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && ! app(NipService::class)->isValid((string) $value)) {
                    $fail('Podaj prawidłowy NIP (10 cyfr).');
                }
            }],
            'logo' => [
                'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048',
                Rule::dimensions()->minWidth(100)->minHeight(100)->maxWidth(2000)->maxHeight(2000),
            ],
            'remove_logo' => ['nullable', 'boolean'],
            'country' => ['required', 'string', 'max:255'],
            'province' => ['required', Rule::in(config('shop.provinces'))],
            'city' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'regex:/^\d{2}-\d{3}$/'],
            'street' => ['required', 'string', 'max:255'],
            'building_number' => ['required', 'string', 'max:32'],
            'apartment_number' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nazwa sklepu',
            'description' => 'opis sklepu',
            'company_name' => 'nazwa firmy',
            'nip' => 'NIP',
            'logo' => 'logo',
            'country' => 'kraj',
            'province' => 'województwo',
            'city' => 'miejscowość',
            'postal_code' => 'kod pocztowy',
            'street' => 'ulica',
            'building_number' => 'numer budynku',
            'apartment_number' => 'numer lokalu',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postal_code.regex' => 'Podaj kod pocztowy w formacie NN-NNN.',
            'province.in' => 'Wybierz województwo z listy.',
            'logo.image' => 'Logo musi być obrazem (PNG, JPG lub WebP).',
            'logo.mimes' => 'Logo musi być w formacie PNG, JPG lub WebP.',
            'logo.max' => 'Logo może mieć maksymalnie 2 MB.',
            'logo.dimensions' => 'Logo powinno mieć od 100 do 2000 px boku.',
        ];
    }
}
