<?php

namespace App\Models;

use Database\Factories\BulkMailingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedna wiadomość korespondencji seryjnej. Do chwili wysyłki jest szkicem:
 * treść wolno poprawiać, a próbki na własny adres sprzedawca może wysyłać bez
 * ograniczeń. `sent_at` zamyka ją na stałe — ta sama wiadomość nie poleci do
 * klientów drugi raz.
 *
 * `shop_id` nie jest mass-assignable — mailing tworzymy przez relację sklepu.
 */
#[Fillable(['subject', 'body'])]
class BulkMailing extends Model
{
    /** @use HasFactory<BulkMailingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
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
     * Czy poszła już do klientów. Wysyłka jest jednorazowa i nieodwracalna.
     */
    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    /**
     * Czy to wciąż szkic — można edytować treść i wysyłać próbki do siebie.
     */
    public function isDraft(): bool
    {
        return ! $this->isSent();
    }
}
