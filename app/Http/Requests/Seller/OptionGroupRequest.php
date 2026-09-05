<?php

namespace App\Http\Requests\Seller;

use App\Enums\OptionGroupKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Grupa opcji produktu — „blok pytań" zadawany kupującemu przy zakupie.
 *
 * RODZAJ (`kind`) przyjmujemy WYŁĄCZNIE przy tworzeniu. Zmiana z formatki na
 * bibliotekę osierociłaby pola tekstowe, a w drugą stronę — pozycje biblioteki;
 * grupa zostałaby pusta, a produkty, które ją mają przypiętą, przestałyby dać
 * się kupić. To nie jest edycja, tylko inna grupa.
 */
class OptionGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->shop !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'hint' => Str::squish((string) $this->input('hint')) ?: null,
            // Polski zapis kwoty: przecinek i spacje jako separator tysięcy.
            'surcharge_gross' => str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $this->input('surcharge_gross'))) ?: '0',
            'required' => $this->boolean('required'),
            'excludes_group_id' => $this->input('excludes_group_id') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shopId = $this->user()->shop->id;
        $group = $this->route('optionGroup');

        return [
            'name' => ['required', 'string', 'max:120'],
            'hint' => ['nullable', 'string', 'max:300'],

            /*
             * Dopłata za SAMO skorzystanie z grupy — koszt wykonania. Zero jest
             * normalną wartością: specyfikacja mówi wprost, że koszt nadruku na
             * awersie jest zawarty w cenie produktu i nie wykazujemy go osobno.
             */
            'surcharge_gross' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'required' => ['boolean'],

            /*
             * Grupa wykluczająca — „grawer to grafika ALBO tekst". Musi należeć
             * do tego samego sklepu i nie może wskazywać samej siebie: grupa
             * wykluczająca się z sobą byłaby niemożliwa do wypełnienia.
             */
            'excludes_group_id' => [
                'nullable',
                Rule::exists('option_groups', 'id')->where('shop_id', $shopId),
                ...($group !== null ? [Rule::notIn([$group->id])] : []),
            ],

            // Rodzaj tylko przy tworzeniu — patrz docblock klasy.
            'kind' => [
                $group === null ? 'required' : 'prohibited',
                new Enum(OptionGroupKind::class),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Podaj nazwę grupy — zobaczy ją kupujący nad polami.',
            'kind.required' => 'Wybierz, czy klient ma wpisywać tekst, czy wybierać z biblioteki.',
            'kind.prohibited' => 'Rodzaju grupy nie da się zmienić po utworzeniu — załóż nową.',
            'surcharge_gross.numeric' => 'Dopłata musi być liczbą. Wpisz 0, jeśli nie doliczasz nic.',
            'excludes_group_id.not_in' => 'Grupa nie może wykluczać samej siebie.',
        ];
    }
}
