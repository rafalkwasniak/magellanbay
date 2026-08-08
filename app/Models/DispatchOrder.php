<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zlecenie odbioru paczek przez kuriera InPostu — jeden przyjazd po wiele
 * przesyłek. Sprzedawca z ruchem nadaje w ciągu dnia, a wieczorem zamawia
 * JEDEN przyjazd na wszystko: dopłata jest za przyjazd, nie za paczkę.
 *
 * Status jest po stronie InPostu i przychodzi z opóźnieniem — patrz migracja.
 */
class DispatchOrder extends Model
{
    /** Statusy ShipX, przy których kurier faktycznie przyjedzie. */
    private const ACCEPTED_STATUSES = ['sent', 'confirmed', 'accepted'];

    protected $fillable = ['shipx_id', 'status', 'error'];

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Czy InPost przyjął zlecenie. Świadomie NIE traktujemy braku statusu jako
     * sukcesu: świeżo utworzone zlecenie ma `new` i dopiero za chwilę okaże się,
     * czy przeszło.
     */
    public function isAccepted(): bool
    {
        return in_array($this->status, self::ACCEPTED_STATUSES, true);
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected' || filled($this->error);
    }

    /**
     * Czy wciąż czekamy na rozstrzygnięcie po stronie InPostu.
     */
    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isRejected();
    }
}
