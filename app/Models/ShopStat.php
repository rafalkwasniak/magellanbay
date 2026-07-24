<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dzienny agregat ruchu sklepu (Poziom 2). Zapis idzie przez `TrafficRecorder`
 * (atomowy inkrement), więc model służy głównie do ODCZYTU w analityce.
 */
#[Fillable(['shop_id', 'date', 'visits', 'product_views'])]
class ShopStat extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'visits' => 'integer',
            'product_views' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
