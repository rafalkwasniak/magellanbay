<?php

namespace App\Models;

use App\Enums\IntegrationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Konfiguracja jednej integracji sklepu. `config` jest szyfrowany APP_KEY-em
 * (jeden mechanizm dla wszystkich integracji — także GA, którego ID nie jest
 * sekretem). Rozdział ról: stronę Integracje ustawia `config`, stronę
 * Ustawienia przełącza `enabled` — jeden wiersz, dwa formularze.
 */
#[Fillable(['type', 'enabled', 'config'])]
class ShopIntegration extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IntegrationType::class,
            'enabled' => 'boolean',
            'config' => 'encrypted:array',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
