<?php

namespace App\Models;

use Database\Factories\PlatformMailingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jedna wiadomość platformy do sprzedawców. Do chwili wysyłki jest szkicem:
 * treść i listę odbiorców wolno zmieniać, a próbki na własny adres można
 * wysyłać bez ograniczeń. `sent_at` zamyka ją na stałe.
 *
 * Bliźniak {@see BulkMailing}, ale bez sklepu, pakietu, karencji i promowanego
 * produktu — patrz komentarz w migracji `create_platform_mailings_table`.
 *
 * Jednorazowość zostaje mimo braku karencji (decyzja Rafała 2026-08-10): chcesz
 * napisać ponownie — tworzysz nową wiadomość, od ręki i bez czekania. Dzięki
 * temu zapis historyczny pokazuje dokładnie tę treść, którą dostali adresaci.
 */
#[Fillable(['subject', 'body'])]
class PlatformMailing extends Model
{
    /** @use HasFactory<PlatformMailingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'recipient_ids' => 'array',
        ];
    }

    /**
     * Wiadomości w kolejce powstałe z tej kampanii — po nich liczymy postęp.
     * Próbki na własny adres tu NIE wchodzą.
     *
     * @return HasMany<EmailMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function isDraft(): bool
    {
        return ! $this->isSent();
    }

    /**
     * Identyfikatory zaznaczonych kont. Pusta tablica także wtedy, gdy nikogo
     * jeszcze nie wybrano — rozróżnienie „nie wybierano" od „odznaczono
     * wszystkich" niesie kolumna (`null` vs `[]`), a nie ta metoda.
     *
     * @return array<int, int>
     */
    public function recipientIds(): array
    {
        return array_map('intval', $this->recipient_ids ?? []);
    }

    /**
     * Czy administrator w ogóle dotknął listy odbiorców. Świeży szkic ma `null`
     * i dostaje wtedy domyślne zaznaczenie wszystkich uprawnionych.
     */
    public function hasRecipientSelection(): bool
    {
        return $this->recipient_ids !== null;
    }

    public function messagesTotal(): int
    {
        $linked = $this->messages()->count();

        return $linked > 0 ? $linked : (int) ($this->recipients_count ?? 0);
    }

    /**
     * Ile wiadomości opuściło serwer. Liczone z outboxu, bo kolejkę opróżnia
     * cron paczkami — `sent_at` kampanii mówi tylko, kiedy ją zlecono.
     */
    public function deliveredCount(): int
    {
        return $this->messages()->whereNotNull('sent_at')->count();
    }

    public function failedCount(): int
    {
        return $this->messages()->whereNotNull('failed_at')->count();
    }

    /**
     * Czy wysyłka wciąż trwa: kampania ruszyła, ale część maili czeka w kolejce.
     */
    public function isDelivering(): bool
    {
        if (! $this->isSent()) {
            return false;
        }

        return $this->messages()->whereNull('sent_at')->whereNull('failed_at')->exists();
    }
}
