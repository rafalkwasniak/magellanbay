<?php

namespace App\Models;

use App\Enums\VatRate;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pozycja zamówienia — migawka kupionego produktu. Cena i nazwa są zapisane
 * (nie odczytywane z aktualnego produktu), więc pozostają wierne nawet po
 * zmianie lub miękkim usunięciu produktu.
 */
#[Fillable([
    'product_id', 'name', 'unit_price_gross', 'vat_rate', 'quantity', 'line_total_gross',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price_gross' => 'decimal:2',
            'line_total_gross' => 'decimal:2',
            'vat_rate' => VatRate::class,
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
