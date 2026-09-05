<?php

namespace App\Models;

use App\Enums\PriceComponentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeden składnik ceny pozycji zamówienia — „cena z czterech części" rozpisana
 * na wiersze.
 *
 * NIEZMIENNIK, KTÓRY TRZYMA CAŁOŚĆ W RYZACH: suma `unit_amount_gross`
 * wszystkich składników pozycji równa się jej `unit_price_gross`. Dzięki temu
 * rozbicie nie jest ozdobnikiem obok ceny, tylko jej rozwinięciem — a rozjazd
 * wychodzi w teście, a nie w rozliczeniu z partnerem.
 *
 * Kwota jest NA JEDNOSTKĘ. Ilość stoi na pozycji zamówienia i powtórzona tutaj
 * rozjechałaby się przy edycji zamówienia.
 */
#[Fillable(['kind', 'label', 'licensor_id', 'licensor_name', 'unit_amount_gross', 'position'])]
class OrderItemComponent extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => PriceComponentKind::class,
            'unit_amount_gross' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * Partner, któremu należy się ta kwota. Może być `null` także wtedy, gdy
     * kartotekę skasowano — wówczas została migawka nazwy.
     *
     * @return BelongsTo<Licensor, $this>
     */
    public function licensor(): BelongsTo
    {
        return $this->belongsTo(Licensor::class);
    }

    /**
     * Same opłaty licencyjne — wejście do rozliczeń z partnerami.
     *
     * @param  Builder<OrderItemComponent>  $query
     */
    public function scopeLicences(Builder $query): void
    {
        $query->where('kind', PriceComponentKind::Licence);
    }
}
