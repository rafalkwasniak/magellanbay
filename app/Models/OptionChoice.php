<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pozycja biblioteki — jedna grafika albo wariant do wyboru, z własną dopłatą.
 *
 * WYCOFANEJ POZYCJI NIE KASUJEMY, tylko gasimy `is_active`. Skasowanie
 * unieważniłoby historyczne zamówienia, w których ktoś ją wybrał — a arkusz
 * produkcyjny, reklamacja i zwrot muszą wiedzieć, co dokładnie zamówiono.
 * Ta sama zasada co przy produktach (`deleted_at`, nie `DELETE`).
 */
#[Fillable(['label', 'image_path', 'surcharge_gross', 'is_active', 'position'])]
class OptionChoice extends Model
{
    protected function casts(): array
    {
        return [
            'surcharge_gross' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<OptionGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }

    /**
     * Tylko pozycje, które kupujący ma prawo dziś wybrać.
     *
     * @param  Builder<OptionChoice>  $query
     */
    public function scopeSelectable(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position');
    }
}
