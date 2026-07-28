<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zużycie zadań AI przez sklep w jednym oknie rozliczeniowym (tydzień ISO).
 * Zapis idzie przez `AiQuota` (atomowy inkrement), więc model służy głównie do
 * odczytu — dokładnie jak `ShopStat`.
 */
#[Fillable(['shop_id', 'period', 'tasks'])]
class AiUsage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tasks' => 'integer',
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
