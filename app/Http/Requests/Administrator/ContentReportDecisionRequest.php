<?php

namespace App\Http\Requests\Administrator;

use App\Enums\ContentReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Rozstrzygnięcie zgłoszenia treści bezprawnej.
 *
 * Uzasadnienie jest WYMAGANE przy każdym rozstrzygnięciu, także przy odrzuceniu —
 * art. 17 DSA każe podać powody, a „odrzucono" bez słowa wyjaśnienia jest
 * dokładnie tym, przed czym ten przepis chroni. Minimalna długość jest po to,
 * żeby nie dało się odbębnić tego kropką.
 *
 * `status` celowo bez `New` na liście dopuszczalnych wartości: rozstrzygnięcie
 * to ruch W JEDNĄ stronę, a „cofnięcie do nowego" ukryłoby fakt, że decyzja
 * (i maile) już poszły.
 */
class ContentReportDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                (new Enum(ContentReportStatus::class))->only([
                    ContentReportStatus::Upheld,
                    ContentReportStatus::Rejected,
                ]),
            ],
            'decision_reason' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Wybierz rozstrzygnięcie.',
            'decision_reason.required' => 'Uzasadnienie jest obowiązkowe — trafia do zgłaszającego i do sprzedawcy.',
            'decision_reason.min' => 'Napisz uzasadnienie pełnym zdaniem — to pismo idzie na zewnątrz.',
        ];
    }
}
