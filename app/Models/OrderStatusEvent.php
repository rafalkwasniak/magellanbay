<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pojedyncze przejście statusu zamówienia (oś czasu). Niezmienne — stąd tylko
 * `created_at` (UPDATED_AT wyłączony). `from_status` bywa null dla zdarzeń bez
 * poprzednika. Tworzone wyłącznie przez Order::changeStatus().
 */
#[Fillable(['from_status', 'to_status', 'note'])]
class OrderStatusEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
