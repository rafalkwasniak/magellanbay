<?php

namespace App\Http\Requests\Seller;

use App\Models\Page;
use App\Services\HtmlSanitizer;
use App\Services\SlugService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Walidacja strony tekstowej („Informacje"). Slug liczymy z tytułu (kanoniczny
 * jest `id`, slug to ozdoba SEO). Treść to HTML z edytora Trix — sanityzujemy.
 * Strona systemowa (Regulamin) ma tytuł zablokowany i zawsze jest opublikowana;
 * pilnuje tego kontroler, tu tylko normalizujemy wspólne pola.
 */
class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->shop !== null;
    }

    /**
     * Normalizacja przed walidacją: slug z tytułu, checkbox publikacji do bool,
     * treść przez sanitizer HTML.
     */
    protected function prepareForValidation(): void
    {
        // Strona systemowa (Regulamin) ma tytuł stały — pole jest w formularzu
        // zablokowane i nie leci w żądaniu, więc bierzemy tytuł z istniejącej
        // strony (inaczej `title` byłby pusty i walidacja odrzucałaby zapis).
        $page = $this->route('page');
        $title = $page instanceof Page && $page->is_system
            ? $page->title
            : (string) $this->input('title');

        $merge = [
            'title' => $title,
            'slug' => app(SlugService::class)->make($title),
            'published' => $this->boolean('published'),
        ];

        if ($this->has('content')) {
            $merge['content'] = app(HtmlSanitizer::class)->clean((string) $this->input('content'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string'],
            'content' => ['nullable', 'string', 'max:'.config('pages.content_max')],
            'published' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'tytuł',
            'content' => 'treść',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Podaj tytuł strony.',
            'content.max' => 'Treść jest za długa — skróć ją nieco.',
        ];
    }
}
