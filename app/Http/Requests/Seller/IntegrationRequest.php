<?php

namespace App\Http\Requests\Seller;

use App\Enums\IntegrationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Walidacja konfiguracji integracji: identyfikator Google Analytics (G-…/GTM-…)
 * oraz dane Fakturowni (adres konta + token API). Wszystkie pola opcjonalne —
 * puste znaczy „usuń/nie zmieniaj" (szczegóły w kontrolerze). Regex GA pełni
 * podwójną rolę: kształt ID + bezpieczeństwo (wartość trafia do <script>
 * storefrontu, więc dopuszczamy tylko [A-Z0-9-]).
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
     * Normalizacja wejścia. GA: trim + wielkie litery (G-/GTM- są case-insensitive
     * u Google, trzymamy kanonicznie wielkimi). Fakturownia: adres dostaje schemat
     * https:// gdy go brak i traci końcowy ukośnik (kanoniczna baza do API i PDF);
     * token tylko trim. Puste stringi → null, by `nullable` puszczało czyszczenie.
     */
    protected function prepareForValidation(): void
    {
        $id = trim((string) $this->input('google_analytics_id'));
        $url = trim((string) $this->input('fakturownia_url'));
        $token = trim((string) $this->input('fakturownia_token'));
        $paynowApiKey = trim((string) $this->input('paynow_api_key'));
        $paynowSignatureKey = trim((string) $this->input('paynow_signature_key'));

        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $this->merge([
            'google_analytics_id' => $id === '' ? null : strtoupper($id),
            'fakturownia_url' => $url === '' ? null : rtrim($url, '/'),
            'fakturownia_token' => $token === '' ? null : $token,
            'paynow_api_key' => $paynowApiKey === '' ? null : $paynowApiKey,
            'paynow_signature_key' => $paynowSignatureKey === '' ? null : $paynowSignatureKey,
            // Środowisko wybieramy checkboxem „testowe (sandbox)": zaznaczony =
            // sandbox, odznaczony = produkcja. Odznaczony domyślnie znaczy produkcję,
            // więc UI musi renderować stan bieżący, żeby zapis go nie zresetował.
            'paynow_environment' => $this->boolean('paynow_sandbox') ? 'sandbox' : 'production',
            'paynow_auto_invoice' => $this->boolean('paynow_auto_invoice'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'google_analytics_id' => ['nullable', 'string', 'regex:'.self::GA_PATTERN],
            'fakturownia_url' => ['nullable', 'string', 'url', 'max:255'],
            'fakturownia_token' => ['nullable', 'string', 'max:255'],
            'paynow_api_key' => ['nullable', 'string', 'max:255'],
            'paynow_signature_key' => ['nullable', 'string', 'max:255'],
            'paynow_environment' => ['required', 'in:sandbox,production'],
            'paynow_auto_invoice' => ['boolean'],
        ];
    }

    /**
     * Reguła między-polowa: adres Fakturowni bez tokenu jest dopuszczalny TYLKO
     * wtedy, gdy token jest już zapisany (puste pole = „zostaw token bez zmian").
     * Gdy sklep tokenu nie ma, sam adres nie wystarczy — nie da się wystawić FV
     * bez tokenu, więc żądamy go od razu.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $url = $this->input('fakturownia_url');
            $token = $this->input('fakturownia_token');
            $storedToken = $this->user()?->shop?->integration(IntegrationType::Invoicing)?->config['api_token'] ?? null;

            if (filled($url) && blank($token) && blank($storedToken)) {
                $validator->errors()->add('fakturownia_token', 'Podaj token API Fakturowni, aby połączyć konto.');
            }

            // Bliźniacza reguła dla Paynow: klucz API bez klucza podpisu jest OK
            // tylko, gdy podpis jest już zapisany (puste pole = „zostaw bez zmian").
            // Przy pierwszej konfiguracji obu kluczy nie da się rozdzielić.
            $paynowApiKey = $this->input('paynow_api_key');
            $paynowSignatureKey = $this->input('paynow_signature_key');
            $storedSignature = $this->user()?->shop?->integration(IntegrationType::Payments)?->config['signature_key'] ?? null;

            if (filled($paynowApiKey) && blank($paynowSignatureKey) && blank($storedSignature)) {
                $validator->errors()->add('paynow_signature_key', 'Podaj klucz obliczania podpisu Paynow, aby połączyć konto.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'google_analytics_id.regex' => 'Podaj poprawny identyfikator w formacie G-XXXXXXXXXX (GA4) lub GTM-XXXXXXX (Tag Manager).',
            'fakturownia_url.url' => 'Podaj poprawny adres konta Fakturowni, np. https://twojadomena.fakturownia.pl.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'google_analytics_id' => 'identyfikator Google Analytics',
            'fakturownia_url' => 'adres konta Fakturowni',
            'fakturownia_token' => 'token API Fakturowni',
            'paynow_api_key' => 'klucz dostępu do API Paynow',
            'paynow_signature_key' => 'klucz obliczania podpisu Paynow',
            'paynow_environment' => 'środowisko Paynow',
        ];
    }
}
