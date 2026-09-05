<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Licencjodawca — firma inkasująca opłatę za użycie swojego znaku lub grafiki.
 *
 * Organizator biegu, klub, wydawca. Kartoteka jest widoczna WYŁĄCZNIE dla
 * sprzedawcy: kupujący widzi opłatę w rozbiciu ceny, ale nie ma powodu wiedzieć,
 * z kim sklep ma podpisaną umowę.
 *
 * WYCOFANEGO PARTNERA GASIMY, NIE KASUJEMY. Rozliczenia historyczne muszą dalej
 * wskazywać, komu należały się pieniądze za sprzedaż sprzed roku — a skasowanie
 * wiersza zamieniłoby raport w listę kwot bez adresata.
 */
#[Fillable(['name', 'contact_email', 'contact_person', 'agreement_reference', 'notes', 'is_active'])]
class Licensor extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Pozycje biblioteki objęte licencją tego partnera.
     *
     * @return HasMany<OptionChoice, $this>
     */
    public function choices(): HasMany
    {
        return $this->hasMany(OptionChoice::class);
    }

    /**
     * Składniki ceny należne temu partnerowi — podstawa rozliczenia.
     *
     * @return HasMany<OrderItemComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(OrderItemComponent::class);
    }

    /**
     * @param  Builder<Licensor>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
