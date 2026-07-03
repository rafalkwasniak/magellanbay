<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Zamówienie sklepu. Wszystkie dane (kupujący, adres, ceny) to migawka z chwili
 * złożenia — nie odczytujemy ich z bieżących produktów/profilu. `number` to numer
 * per-sklep prezentowany klientom; `shop_id` nie jest mass-assignable (tworzymy
 * przez relację sklepu). Usuwanie wyłącznie logiczne (SoftDeletes).
 */
#[Fillable([
    'number', 'customer_id', 'status',
    'buyer_name', 'buyer_surname', 'buyer_email', 'buyer_phone',
    'is_company', 'company_name', 'company_nip',
    'ship_street', 'ship_building_number', 'ship_apartment_number', 'ship_postal_code', 'ship_city',
    'delivery_method', 'delivery_cost', 'payment_method',
    'items_total', 'total_net', 'total_vat', 'total_gross', 'note',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'payment_method' => PaymentMethod::class,
            'is_company' => 'boolean',
            'delivery_cost' => 'decimal:2',
            'items_total' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_vat' => 'decimal:2',
            'total_gross' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
