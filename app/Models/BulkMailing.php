<?php

namespace App\Models;

use Database\Factories\BulkMailingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jedna wiadomość korespondencji seryjnej. Do chwili wysyłki jest szkicem:
 * treść wolno poprawiać, a próbki na własny adres sprzedawca może wysyłać bez
 * ograniczeń. `sent_at` zamyka ją na stałe — ta sama wiadomość nie poleci do
 * klientów drugi raz.
 *
 * `shop_id` nie jest mass-assignable — mailing tworzymy przez relację sklepu.
 */
#[Fillable(['subject', 'body', 'product_id'])]
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
     * Promowany produkt (opcjonalny) — jego karta stoi pod treścią wiadomości.
     * Null także wtedy, gdy produkt skasowano z katalogu po wysyłce; wysłane
     * maile niosą własną migawkę, więc nic z nich nie znika.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Wiadomości w kolejce powstałe z tej kampanii — po nich liczymy postęp
     * wysyłki. Próbki na adres sprzedawcy tu NIE wchodzą.
     *
     * @return HasMany<EmailMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    /**
     * Czy poszła już do klientów. Wysyłka jest jednorazowa i nieodwracalna.
     */
    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    /**
     * Ile wiadomości należy do tej kampanii. Zwykle liczone z outboxu, ale
     * kampanie wysłane ZANIM powstało powiązanie `bulk_mailing_id` nie mają
     * tam ani jednego wiersza — dla nich jedynym śladem jest migawka
     * `recipients_count` z chwili wysyłki i to jej ufamy.
     */
    public function messagesTotal(): int
    {
        $linked = $this->messages()->count();

        return $linked > 0 ? $linked : (int) ($this->recipients_count ?? 0);
    }

    /**
     * Ile wiadomości faktycznie opuściło serwer. Liczone z outboxu, bo kolejkę
     * opróżnia cron paczkami — `sent_at` na kampanii mówi tylko, kiedy
     * sprzedawca nacisnął „wyślij".
     */
    public function deliveredCount(): int
    {
        // Historia bez powiązania: skoro kampanię wysłano, a maili nie da się
        // już z nią zestawić, pokazujemy liczbę odbiorców z chwili wysyłki —
        // lepsze niż uparte „0", które wygląda na awarię.
        if ($this->messages()->count() === 0) {
            return $this->isSent() ? (int) ($this->recipients_count ?? 0) : 0;
        }

        return $this->messages()->whereNotNull('sent_at')->count();
    }

    /**
     * Ile nie dotarło mimo ponowień — wyczerpany limit prób.
     */
    public function failedCount(): int
    {
        return $this->messages()->whereNotNull('failed_at')->count();
    }

    /**
     * Czy wysyłka wciąż trwa: kampania ruszyła, ale część wiadomości czeka
     * jeszcze w kolejce. Przy 350 odbiorcach i tempie 10/min to ponad pół
     * godziny, więc sprzedawca musi widzieć, że coś się dzieje.
     */
    public function isDelivering(): bool
    {
        if (! $this->isSent()) {
            return false;
        }

        return $this->messages()->whereNull('sent_at')->whereNull('failed_at')->exists();
    }

    /**
     * Czy to wciąż szkic — można edytować treść i wysyłać próbki do siebie.
     */
    public function isDraft(): bool
    {
        return ! $this->isSent();
    }
}
