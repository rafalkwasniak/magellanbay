<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Adres (etykieta subdomeny) po usuniętym sklepie, trzymany w kwarantannie.
 * Jedyny ślad, jaki zostaje po sklepie — dlatego osobna tabela, a nie kolumna
 * na `shops`, która znika razem z wierszem.
 *
 * Wpis po terminie niczego nie blokuje (walidacja patrzy na `released_at`),
 * więc sprzątanie przez `shops:purge` jest higieną, nie warunkiem poprawności.
 */
#[Fillable(['slug', 'released_at'])]
class ReservedSlug extends Model
{
    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
        ];
    }

    /**
     * Rezerwacje wciąż obowiązujące.
     *
     * @param  Builder<ReservedSlug>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('released_at', '>', Carbon::now());
    }
}
