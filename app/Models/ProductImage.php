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
