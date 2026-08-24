<?php

namespace App\Http\Requests\Administrator;

use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Treść wiadomości serwisowej do jednego sprzedawcy (karta sklepu w konsoli).
 * Bliźniak {@see PlatformMailingRequest} — te same reguły i ten sam sanitizer,
 * bo treść pisze się w tym samym edytorze.
 */
class SellerNoticeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:'.config('platform_mail.body_max')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Podaj temat wiadomości.',
            'subject.max' => 'Temat jest za długi — zmieść się w 150 znakach, inaczej skrzynki go utną.',
            'body.required' => 'Napisz treść wiadomości.',
            'body.max' => 'Treść jest za długa — skróć ją nieco.',
        ];
    }

    /**
     * Sanitizacja HTML przed walidacją (konwencja projektu: sanitizer na
     * zapisie, `Prose` na wyjściu). Pusty edytor przysyła sam znacznik bez
     * tekstu — po oczyszczeniu zostaje pustka i zadziała reguła `required`.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('body')) {
            $clean = app(HtmlSanitizer::class)->clean((string) $this->input('body'));

            $this->merge([
                'body' => trim(strip_tags($clean)) === '' ? '' : $clean,
            ]);
        }
    }
}
