<?php

namespace App\Http\Requests\Auth;

use App\Services\SlugService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizacja przed walidacją: subdomenę (slug) liczymy z nazwy sklepu po
     * stronie serwera — pole adresu w formularzu jest tylko podglądem (disabled),
     * więc nie ufamy jego wartości. Slug staje się przedmiotem walidacji.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => app(SlugService::class)->make($this->input('shop_name')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'max:255'],
            // Slug = etykieta subdomeny: małe litery/cyfry, myślniki tylko w środku.
            // Unikalny globalnie (to publiczny adres sklepu) + poza pulą zarezerwowaną.
            'slug' => [
                'required', 'string',
                'min:'.config('tenancy.subdomain.min'),
                'max:'.config('tenancy.subdomain.max'),
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(config('tenancy.reserved_subdomains')),
                Rule::unique('shops', 'slug'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // Hasło ustawiane jest później, w formularzu aktywacji (ActivationController).
            'terms' => ['accepted'],
            'privacy' => ['accepted'],

            // Pułapka na boty (honeypot): pole ukryte w formularzu, którego
            // człowiek nie widzi i nie wypełni. Cokolwiek w nim przyszło, przyszło
            // od automatu. `prohibited` przepuszcza brak pola i pustą wartość.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'shop_name' => 'nazwa sklepu',
            'slug' => 'adres sklepu',
            'name' => 'imię',
            'surname' => 'nazwisko',
            'email' => 'adres e-mail',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Błędy slugu pokazujemy przy parze pól nazwa/adres — komunikat odnosi
            // się do nazwy sklepu, bo to ją sprzedawca edytuje.
            'slug.required' => 'Nazwa sklepu musi zawierać przynajmniej kilka liter lub cyfr.',
            'slug.min' => 'Adres sklepu jest zbyt krótki — wydłuż nazwę sklepu.',
            'slug.regex' => 'Nazwa sklepu musi zawierać litery lub cyfry.',
            'slug.unique' => 'Ten adres sklepu jest już zajęty — wybierz inną nazwę.',
            'slug.not_in' => 'Ten adres sklepu jest zarezerwowany — wybierz inną nazwę.',
            'terms.accepted' => 'Musisz zaakceptować Regulamin.',
            'privacy.accepted' => 'Musisz zaakceptować Politykę Prywatności.',

            // Komunikat celowo nie mówi, CO odrzuciło formularz — bot nie dostaje
            // wskazówki, a człowiek, który jakimś cudem tu trafił (dziwny
            // autouzupełniacz), dostaje radę, która realnie pomoże.
            'website.prohibited' => 'Nie udało się wysłać formularza. Odśwież stronę i spróbuj ponownie.',
        ];
    }
}
