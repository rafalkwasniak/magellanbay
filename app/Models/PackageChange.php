<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeden wpis w historii pakietu sklepu — migawka stanu po zmianie. Zmiany
 * przychodzą dwiema drogami: `payment` (sprzedawca kupił) i `admin` (nadaliśmy
 * ręcznie). Wpis jest dokumentem historycznym, więc nigdy go nie edytujemy.
 */
#[Fillable(['package_payment_id', 'package', 'price_yearly', 'ends_at', 'source', 'comped'])]
class PackageChange extends Model
{
    public const SOURCE_PAYMENT = 'payment';

    public const SOURCE_ADMIN = 'admin';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_yearly' => 'decimal:2',
            'ends_at' => 'datetime',
            'comped' => 'boolean',
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
     * Opłata, z której wynikła ta zmiana (null przy nadaniu ręcznym) — po niej
     * historia dociąga kwotę i link do faktury.
     *
     * @return BelongsTo<PackagePayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(PackagePayment::class, 'package_payment_id');
    }

    public function fromPayment(): bool
    {
        return $this->source === self::SOURCE_PAYMENT;
    }
}
