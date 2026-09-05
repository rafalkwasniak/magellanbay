<?php

namespace App\Models;

use App\Enums\SaleUnit;
use App\Enums\VatRate;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pozycja zamówienia — migawka kupionego produktu. Cena i nazwa są zapisane
 * (nie odczytywane z aktualnego produktu), więc pozostają wierne nawet po
 * zmianie lub miękkim usunięciu produktu.
 */
#[Fillable([
    'product_id', 'name', 'unit_price_gross', 'vat_rate', 'quantity', 'sale_unit', 'line_total_gross',
    'personalisation', 'configuration', 'personalisation_surcharge_gross',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price_gross' => 'decimal:2',
            'personalisation' => 'array',
            'configuration' => 'array',
            'personalisation_surcharge_gross' => 'decimal:2',
            'line_total_gross' => 'decimal:2',
            'vat_rate' => VatRate::class,
            'sale_unit' => SaleUnit::class,
            'quantity' => 'decimal:2',
            'returned_quantity' => 'decimal:2',
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Pozycje zgłoszeń zwrotu dotyczące tej linii — historia „co i kiedy wróciło".
     *
     * @return HasMany<OrderReturnItem, $this>
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    /**
     * Rozbicie ceny jednostkowej na składniki — produkt, personalizacja, opłaty
     * licencyjne. „Cena z czterech części" rozpisana na wiersze.
     *
     * NIEZMIENNIK: suma `unit_amount_gross` składników równa się
     * `unit_price_gross` pozycji. Rozbicie jest rozwinięciem ceny, nie notatką
     * obok niej — i to właśnie z tych wierszy powstają rozliczenia z partnerami.
     *
     * @return HasMany<OrderItemComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(OrderItemComponent::class)->orderBy('position');
    }

    /**
     * Ilość, za którą klient nadal płaci: kupiona minus zwrócona. To z niej liczą
     * się kwoty zamówienia, faktura i statystyki — `quantity` zostaje migawką
     * zakupu, więc widać, ile było pierwotnie.
     */
    public function effectiveQuantity(): float
    {
        return round((float) $this->quantity - (float) $this->returned_quantity, 2);
    }

    /**
     * Ile jeszcze wolno zwrócić z tej pozycji. Zwykle tyle, ile zostało — ale
     * przy towarze z wyjątku art. 38 (kwiaty, żywność, rzecz personalizowana)
     * prawa odstąpienia nie ma w ogóle, więc sufit to zero.
     */
    public function returnableQuantity(): float
    {
        return $this->isWithdrawable() ? max(0.0, $this->effectiveQuantity()) : 0.0;
    }

    /**
     * Czy ta pozycja podlega prawu odstąpienia. Pozycja bez produktu (usunięty z
     * katalogu) liczy się jako OBJĘTA — przy niepewności rozstrzygamy na korzyść
     * konsumenta.
     */
    public function isWithdrawable(): bool
    {
        return $this->product === null || $this->product->isWithdrawable();
    }

    /**
     * Czy z tej pozycji cokolwiek już wróciło.
     */
    public function hasReturns(): bool
    {
        return (float) $this->returned_quantity > 0;
    }
}
