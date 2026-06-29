<?php

namespace App\Http\Requests\Seller;

use App\Enums\VatRate;
use App\Services\HtmlSanitizer;
use App\Services\SlugService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Walidacja produktu (krok podstawowy — bez zdjęć i tagów). Cena podawana
 * brutto; slug liczymy z nazwy. Stan ma znaczenie tylko przy włączonej kontroli
 * stanu (`track_stock`).
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->shop !== null;
    }

    /**
     * Normalizacja przed walidacją: slug z nazwy, cena do kropki dziesiętnej,
     * checkboxy do wartości logicznych.
     */
    protected function prepareForValidation(): void
    {
        $merge = [
            'slug' => app(SlugService::class)->make((string) $this->input('name')),
            'price_gross' => str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $this->input('price_gross'))),
            'track_stock' => $this->boolean('track_stock'),
            'is_active' => $this->boolean('is_active'),
            'show_on_homepage' => $this->boolean('show_on_homepage'),
        ];

        // Opis to HTML z edytora Trix — sanityzujemy wąską whitelistą przed walidacją/zapisem.
        if ($this->has('description')) {
            $merge['description'] = app(HtmlSanitizer::class)->clean((string) $this->input('description'));
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
            'slug' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:'.config('shop.product_description_max')],
            'price_gross' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'vat_rate' => ['required', Rule::enum(VatRate::class)],
            'track_stock' => ['boolean'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:1000000', 'required_if:track_stock,true'],
            'is_active' => ['boolean'],
            'show_on_homepage' => ['boolean'],
            'tags' => ['nullable', 'string', 'max:500'],
            // Zdjęcia dodawane przy TWORZENIU produktu (na edycji galeria działa przez AJAX).
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nazwa',
            'description' => 'opis',
            'price_gross' => 'cena',
            'vat_rate' => 'stawka VAT',
            'stock' => 'stan magazynowy',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price_gross.numeric' => 'Podaj cenę liczbą, np. 49,99.',
            'stock.required_if' => 'Podaj stan magazynowy lub wyłącz kontrolę stanu.',
            'images.max' => 'Możesz dodać maksymalnie 8 zdjęć.',
            'images.*.image' => 'Każdy plik musi być obrazem (PNG, JPG lub WebP).',
            'images.*.mimes' => 'Dozwolone formaty zdjęć: PNG, JPG, WebP.',
            'images.*.max' => 'Zdjęcie może mieć maksymalnie 4 MB.',
        ];
    }
}
