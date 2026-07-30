<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedna opłata za pakiet Kramio — migawka wyceny z chwili rozpoczęcia
 * płatności. `shop_id` nie jest mass-assignable (tworzymy przez relację).
 */
#[Fillable(['target_package', 'amount', 'credit', 'new_ends_at', 'status', 'payment_id'])]
class PackagePayment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'credit' => 'decimal:2',
            'new_ends_at' => 'datetime',
            'paid_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }
}
