<?php

namespace App\Models;

use App\Enums\ConsentChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zgoda marketingowa klienta na jeden kanał. Jeden wiersz na parę
 * (klient, kanał) — patrz migracja `customer_consents`.
 *
 * Zgoda jest AKTYWNA, gdy została udzielona i nie została wycofana. Sam wiersz
 * nie wystarcza: po wypisie zostaje z wypełnionym `revoked_at`, żeby odróżnić
 * „wypisał się" od „nigdy się nie zgodził".
 */
class CustomerConsent extends Model
{
    protected $fillable = [
        'channel', 'granted_at', 'revoked_at', 'version', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ConsentChannel::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Czy zgoda obowiązuje teraz — udzielona i niewycofana.
     */
    public function isActive(): bool
    {
        return $this->granted_at !== null && $this->revoked_at === null;
    }
}
