<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pozycja zgłoszenia zwrotu: ile sztuk danej pozycji zamówienia wraca i za ile.
 * `refund_gross` to migawka kwoty po rabacie — liczona w chwili zgłoszenia,
 * żeby późniejsza edycja zamówienia nie zmieniła tego, co obiecaliśmy oddać.
 */
#[Fillable([
    'order_item_id', 'quantity', 'refund_gross',
])]
class OrderReturnItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'refund_gross' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<OrderReturn, $this>
     */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
