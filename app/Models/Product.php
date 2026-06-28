<?php

namespace App\Models;

use App\Enums\VatRate;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Produkt sklepu. Cena `price_gross` jest brutto; netto i kwotę VAT wyliczamy
 * z brutto i ułamka stawki. `track_stock` decyduje, czy `stock` ma znaczenie.
 */
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
