<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\OrderReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Oświadczenie o odstąpieniu od umowy — jedno zgłoszenie zwrotu do zamówienia.
 * Powstaje z formularza na tokenie (bez logowania) i od razu pomniejsza
 * zamówienie; sprzedawca go nie zatwierdza, bo odstąpienie działa z mocy prawa.
 *
 * `order_id` nie jest mass-assignable — zwrot tworzymy przez relację zamówienia.
 */
#[Fillable([
    'customer_name', 'customer_address', 'bank_account', 'note', 'refund_gross',
])]
class OrderReturn extends Model
{
    /** @use HasFactory<OrderReturnFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'refund_gross' => 'decimal:2',
            'refunded_at' => 'datetime',
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
     * @return HasMany<OrderReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    /**
     * Czy sprzedawca oznaczył, że oddał pieniądze. Jedyny stan tego zgłoszenia —
     * świadomie bez maszyny statusów: zwrot albo jest rozliczony, albo czeka.
     */
    public function isRefunded(): bool
    {
        return $this->refunded_at !== null;
    }

    /**
     * Ostatni dzień na oddanie pieniędzy: 14 dni od OTRZYMANIA oświadczenia
     * (art. 32 ust. 1 ustawy o prawach konsumenta), czyli od zapisania tego
     * zgłoszenia. Sprzedawca może wstrzymać wypłatę do chwili otrzymania towaru
     * albo dowodu odesłania — ale to zawieszenie wykonania, nie nowy termin,
     * więc pokazujemy jedną, konkretną datę.
     */
    public function refundDeadline(): CarbonInterface
    {
        return $this->created_at
            ->copy()
            ->addDays((int) config('legal.withdrawal.days'))
            ->endOfDay();
    }

    /**
     * Czy termin na zwrot pieniędzy już minął — do wyróżnienia zaległych
     * zgłoszeń w panelu.
     */
    public function isRefundOverdue(): bool
    {
        return ! $this->isRefunded() && $this->refundDeadline()->isPast();
    }

    /**
     * Oznacza zwrot jako rozliczony finansowo. Idempotentne — pierwszy zapis
     * ustala datę, kolejne kliknięcia jej nie przesuwają.
     */
    public function markRefunded(): void
    {
        if ($this->isRefunded()) {
            return;
        }

        $this->forceFill(['refunded_at' => now()])->save();
    }
}
