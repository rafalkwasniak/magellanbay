<?php

namespace App\Models;

use App\Enums\ConsentChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zgoda sprzedawcy na informacje handlowe od Kramio w danym kanale. Brak wiersza
 * = nigdy się nie zgodził; wiersz z `revoked_at` = wypisał się. Ta różnica ma
 * znaczenie dowodowe, dlatego przy wycofaniu nie kasujemy rekordu.
 */
#[Fillable(['channel', 'granted_at', 'revoked_at', 'version', 'ip_address'])]
class UserMarketingConsent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => ConsentChannel::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->granted_at !== null && $this->revoked_at === null;
    }
}
