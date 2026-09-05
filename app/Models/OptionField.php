<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pojedyncze pole formatki — jedna linia, którą kupujący wpisuje.
 *
 * `max_length` NIE jest podpowiedzią. Wynika z fizyki produktu: na magnes
 * 70 × 50 mm wchodzi tyle liter, ile wchodzi, a dłuższy tekst to zamówienie
 * niewykonalne. Dlatego limit jest twardą walidacją przy dodaniu do koszyka,
 * a nie tylko atrybutem `maxlength` w formularzu (ten da się obejść).
 */
#[Fillable(['label', 'max_length', 'required', 'placeholder', 'position'])]
class OptionField extends Model
{
    protected function casts(): array
    {
        return [
            'max_length' => 'integer',
            'required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<OptionGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }
}
