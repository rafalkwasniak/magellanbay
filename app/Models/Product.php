<?php

namespace App\Models;

use App\Enums\VatRate;
use App\Observers\ProductObserver;
use App\Support\OmnibusPrice;
use Carbon\CarbonInterface;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Produkt sklepu. Cena `price_gross` jest brutto; netto i kwotę VAT wyliczamy
 * z brutto i ułamka stawki. `track_stock` decyduje, czy `stock` ma znaczenie.
 * Zmiany produktu napędzają widoczność sklepu (ProductObserver).
 */
#[ObservedBy(ProductObserver::class)]
#[Fillable([
    'name', 'slug', 'description', 'price_gross', 'vat_rate',
    'track_stock', 'stock', 'is_active', 'show_on_homepage',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vat_rate' => VatRate::class,
            'price_gross' => 'decimal:2',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'show_on_homepage' => 'boolean',
            'stock' => 'integer',
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
     * Zdjęcia produktu w kolejności (najniższa pozycja = główne).
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Zdjęcie główne (o najniższej pozycji) albo null.
     */
    public function mainImage(): ?ProductImage
    {
        return $this->images->first();
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Historia cen w kolejności chronologicznej (najstarsza pierwsza).
     *
     * @return HasMany<ProductPriceHistory, $this>
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class)->orderBy('recorded_at')->orderBy('id');
    }

    /**
     * Najniższa cena z 30 dni przed obniżką (Omnibus) — do pokazania obok ceny.
     * Zwraca null, gdy nie ma czego ujawniać: brak obniżki w ostatnich 30 dniach
     * albo bieżąca cena nie jest niższa od wcześniejszej.
     */
    public function lowestPriceLast30Days(?CarbonInterface $now = null): ?float
    {
        $reference = OmnibusPrice::lowestBeforeCurrent($this->priceHistory, $now ?? now());

        return ($reference !== null && $reference > (float) $this->price_gross) ? $reference : null;
    }

    /**
     * Cena netto wyliczona z brutto i stawki VAT.
     */
    public function priceNet(): float
    {
        return round((float) $this->price_gross / (1 + $this->vat_rate->fraction()), 2);
    }

    /**
     * Kwota VAT (brutto − netto).
     */
    public function vatAmount(): float
    {
        return round((float) $this->price_gross - $this->priceNet(), 2);
    }

    /**
     * Czy produkt jest dostępny do kupienia (brak kontroli stanu albo stan > 0).
     */
    public function inStock(): bool
    {
        return ! $this->track_stock || (int) $this->stock > 0;
    }
}
