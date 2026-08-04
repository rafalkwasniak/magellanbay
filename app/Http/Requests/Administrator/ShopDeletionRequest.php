<?php

namespace App\Http\Requests\Administrator;

use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Potwierdzenie usunięcia sklepu w konsoli admina: trzeba przepisać nazwę
 * sklepu. Sam `confirm` w przeglądarce nie wystarcza — kliknięcie „OK" jest
 * odruchem, a przepisanie nazwy wymaga spojrzenia, KTÓRY sklep się kasuje.
 *
 * Porównanie jest odporne na wielkość liter i podwójne spacje, ale nie na
 * skróty — całą nazwę trzeba wpisać.
 */
class ShopDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirm_name' => Str::squish((string) $this->input('confirm_name')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shop = $this->route('shop');

        return [
            'confirm_name' => [
                'required', 'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($shop): void {
                    if (! $shop instanceof Shop || mb_strtolower((string) $value) !== mb_strtolower(Str::squish($shop->name))) {
                        $fail('Wpisana nazwa nie zgadza się z nazwą sklepu.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_name.required' => 'Wpisz nazwę sklepu, żeby potwierdzić usunięcie.',
        ];
    }
}
