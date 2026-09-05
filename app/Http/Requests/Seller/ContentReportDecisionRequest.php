<?php

namespace App\Http\Requests\Seller;

use App\Enums\ContentReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Rozstrzygnięcie zgłoszenia przez właściciela sklepu dedykowanego.
 *
 * Bliźniak {@see App\Http\Requests\Administrator\ContentReportDecisionRequest}
 * — te same reguły, inny adresat. Osobna klasa, a nie współdzielona: tamta
 * autoryzuje rolą `admin`, więc użyta tutaj odsyłałaby właściciela z 403 na
 * jego własnym ekranie.
 *
 * Uzasadnienie jest WYMAGANE przy każdym rozstrzygnięciu, także przy odrzuceniu.
 * „Odrzucono" bez słowa wyjaśnienia jest dokładnie tym, przed czym chroni art. 17
 * DSA, a minimalna długość pilnuje, żeby nie dało się odbębnić tego kropką.
 *
 * `status` celowo bez `New`: rozstrzygnięcie to ruch W JEDNĄ stronę, a cofnięcie
 * do nowego ukryłoby fakt, że decyzja i mail już poszły.
 */
class ContentReportDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->shop !== null;
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
            'decision_reason.required' => 'Uzasadnienie jest obowiązkowe — trafia do zgłaszającego.',
            'decision_reason.min' => 'Napisz uzasadnienie pełnym zdaniem — to pismo idzie na zewnątrz.',
        ];
    }
}
