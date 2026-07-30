<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wpis „to powiadomienie już poszło". Istnieje wyłącznie po to, żeby codzienny
 * cron był idempotentny — nie niesie żadnej treści, bo maile buduje serwis.
 */
#[Fillable(['shop_id', 'kind', 'ends_at'])]
class SubscriptionNotice extends Model
{
    public const KIND_LOCKED = 'locked';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Rodzaj przypomnienia dla progu „X dni przed terminem".
     */
    public static function reminderKind(int $days): string
    {
        return 'reminder_'.$days;
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
