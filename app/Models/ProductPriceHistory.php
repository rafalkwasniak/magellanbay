<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pojedynczy wpis historii ceny produktu: cena brutto obowiązująca od
 * `recorded_at`. Wpisy są niezmienne (append-only) — nie aktualizujemy ich,
 * stąd brak kolumn `timestamps`. Służą wyliczeniu „najniższej ceny z 30 dni"
 * (App\Support\OmnibusPrice).
 */
#[Fillable(['price_gross', 'recorded_at'])]
class ProductPriceHistory extends Model
{
    protected $table = 'product_price_history';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_gross' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
