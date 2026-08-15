<?php

namespace App\Http\Requests;

use App\Enums\ContentReportCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Zgłoszenie treści bezprawnej — formularz publiczny, bez logowania.
 *
 * Zakres pól to wprost lista z art. 16 ust. 2 DSA: adres, uzasadnienie, dane
 * kontaktowe i oświadczenie o dobrej wierze. Zgłoszenie bez któregokolwiek z
 * nich nie uruchamia naszych obowiązków, więc nie przyjmujemy go po cichu —
 * odbijamy z komunikatem, co uzupełnić.
 *
 * Nazwisko zostaje NIEobowiązkowe: art. 16 wymaga danych kontaktowych, a adres
 * e-mail wystarcza, żeby wysłać potwierdzenie i decyzję. Wymaganie nazwiska
 * odstraszałoby część zgłaszających bez pożytku dla sprawy.
 */
class ContentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'url' => trim((string) $this->input('url')),
            'reporter_email' => Str::lower(trim((string) $this->input('reporter_email'))),
            'reporter_name' => Str::squish((string) $this->input('reporter_name')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // `active_url` świadomie POMINIĘTE: odpytywałoby DNS przy każdym
            // zgłoszeniu, a treść bywa zgłaszana właśnie dlatego, że zaraz znika.
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'category' => ['required', Rule::enum(ContentReportCategory::class)],
            'justification' => ['required', 'string', 'min:20', 'max:5000'],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            // Bez `dns` — reszta projektu waliduje adresy tak samo, a odpytywanie
            // rekordów MX wciągałoby sieć do każdego testu i do każdego zgłoszenia.
            'reporter_email' => ['required', 'string', 'email', 'max:255'],
            'good_faith' => ['accepted'],
            // Pułapka na boty — ten sam wzorzec, co przy rejestracji. Formularz
            // jest publiczny i bez logowania, więc musi mieć czym odsiać automaty,
            // a captcha na shared hostingu to armata na wróbla (i bariera przed
            // czymś, co z założenia ma być łatwo dostępne).
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => 'Podaj dokładny adres strony, na której jest zgłaszana treść.',
            'url.url' => 'To nie wygląda na adres strony — wklej go w całości, razem z „https://".',
            'category.required' => 'Wybierz, czego dotyczy zgłoszenie.',
            'justification.required' => 'Napisz, dlaczego uważasz tę treść za bezprawną.',
            'justification.min' => 'Napisz trochę więcej — z jednego zdania nie da się ocenić zgłoszenia.',
            'reporter_email.required' => 'Podaj adres e-mail — bez niego nie wyślemy Ci potwierdzenia ani decyzji.',
            'reporter_email.email' => 'Ten adres e-mail wygląda na nieprawidłowy.',
            'good_faith.accepted' => 'Potwierdź, że zgłoszenie składasz w dobrej wierze.',
            'website.prohibited' => 'Nie udało się wysłać formularza. Odśwież stronę i spróbuj ponownie.',
        ];
    }
}
