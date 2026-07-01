<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Pojedyncze zdjęcie produktu. Główne = o najniższej pozycji (patrz Product).
 */
#[Fillable(['path', 'position'])]
class ProductImage extends Model
{
    /**
     * Plik zdjęcia znika z dysku razem z rekordem — jedno miejsce sprzątania dla
     * każdej ścieżki usuwania (pojedyncze zdjęcie, twarde usunięcie produktu).
     * UWAGA: kaskada FK na poziomie bazy NIE odpala tego zdarzenia, dlatego przy
     * usuwaniu produktu kasujemy zdjęcia przez Eloquent (patrz Product::purge()).
     */
    protected static function booted(): void
    {
        static::deleting(function (ProductImage $image): void {
            Storage::disk('public')->delete($image->path);
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Publiczny URL zdjęcia.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
