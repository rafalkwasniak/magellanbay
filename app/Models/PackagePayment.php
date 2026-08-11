<?php

namespace App\Models;

use Database\Factories\PackagePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedna opłata za pakiet Kramio — migawka wyceny z chwili rozpoczęcia
 * płatności. `shop_id` nie jest mass-assignable (tworzymy przez relację).
 */
#[Fillable(['target_package', 'amount', 'credit', 'new_ends_at', 'status', 'payment_id', 'method', 'recorded_by', 'note', 'paid_at', 'invoice_number'])]
class PackagePayment extends Model
{
    /** @use HasFactory<PackagePaymentFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    /** Wpłata przez bramkę — potwierdza ją webhook, nie człowiek. */
    public const METHOD_PAYNOW = 'paynow';

    public const METHOD_TRANSFER = 'transfer';

    public const METHOD_CASH = 'cash';

    /**
     * Sposoby wpłaty do REJESTRACJI RĘCZNEJ. Bramki świadomie tu nie ma —
     * płatności Paynow powstają same i wpisanie jej z ręki znaczyłoby, że w
     * rejestrze siedzi wpłata, której operator nigdy nie widział.
     *
     * @return array<string, string>
     */
    public static function manualMethods(): array
    {
        return [
            self::METHOD_TRANSFER => 'Przelew',
            self::METHOD_CASH => 'Gotówka',
        ];
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            self::METHOD_TRANSFER => 'Przelew',
            self::METHOD_CASH => 'Gotówka',
            default => 'Paynow',
        };
    }

    public function isManual(): bool
    {
        return $this->method !== self::METHOD_PAYNOW;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'credit' => 'decimal:2',
            'new_ends_at' => 'datetime',
            'paid_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }

    /**
     * Kto wpisał wpłatę do systemu (tylko rejestracja ręczna).
     *
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Czy faktura za tę opłatę już powstała — gard idempotencji, wystawiamy
     * DOKŁADNIE RAZ.
     *
     * Sam numer bez `invoice_id` znaczy „dokument wystawiony poza systemem"
     * (admin wpisał go przy wpłacie ręcznej). Też blokuje: wystawienie drugiej
     * faktury do tej samej wpłaty jest gorsze niż brak faktury w systemie.
     */
    public function hasInvoice(): bool
    {
        return filled($this->invoice_id) || filled($this->invoice_number);
    }

    /**
     * Publiczny link do PDF faktury w Fakturowni (`{konto}/invoice/{token}.pdf`).
     * Token nie wymaga api_token, więc link działa wprost ze skrzynki. null, gdy
     * FV jeszcze nie ma albo brak konfiguracji konta platformy.
     */
    public function invoicePdfUrl(): ?string
    {
        $accountUrl = config('services.fakturownia.url');

        if (blank($this->invoice_token) || blank($accountUrl)) {
            return null;
        }

        return rtrim($accountUrl, '/').'/invoice/'.$this->invoice_token.'.pdf';
    }
}
