<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Walidacja konfiguracji integracji. Na razie identyfikator Google Analytics 4
 * (G-…) lub Google Tag Managera (GTM-…). Pole jest opcjonalne — puste znaczy
 * „usuń integrację". Regex pełni podwójną rolę: kształt ID + bezpieczeństwo
 * (wartość trafia do <script> storefrontu, więc dopuszczamy tylko [A-Z0-9-]).
 */
class IntegrationRequest extends FormRequest
{
    /** GA4: „G-" + znaki; GTM: „GTM-" + znaki. Wielkość liter ujednolicona niżej. */
    public const GA_PATTERN = '/^(G-[A-Z0-9]{4,15}|GTM-[A-Z0-9]{4,12})$/';

    public function authorize(): bool
    {
        return $this->user()?->shop !== null;
    }

    /**
     * Identyfikator normalizujemy: trim + wielkie litery (G-/GTM- są
     * case-insensitive u Google, a my trzymamy je kanonicznie wielkimi).
     * Pusty string sprowadzamy do null, żeby reguła `nullable` puszczała
     * „wyczyszczenie" pola.
     */
    protected function prepareForValidation(): void
    {
        $id = trim((string) $this->input('google_analytics_id'));

        $this->merge([
            'google_analytics_id' => $id === '' ? null : strtoupper($id),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'google_analytics_id' => ['nullable', 'string', 'regex:'.self::GA_PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'google_analytics_id.regex' => 'Podaj poprawny identyfikator w formacie G-XXXXXXXXXX (GA4) lub GTM-XXXXXXX (Tag Manager).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'google_analytics_id' => 'identyfikator Google Analytics',
        ];
    }
}
