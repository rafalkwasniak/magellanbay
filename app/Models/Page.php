<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Strona tekstowa sklepu („Informacje") — Regulamin i dowolne strony sprzedawcy.
 * `content` to HTML z edytora Trix. Kanoniczny adres to /informacje/{id}-{slug}
 * (styl PrestaShop — szukamy po ID, slug to ozdoba SEO). `position` daje jedną
 * wspólną kolejność w menu i stopce. `is_system` (Regulamin) nie jest
 * mass-assignable — strona systemowa powstaje wyłącznie przez ShopObserver.
 */
#[Fillable([
    'title', 'slug', 'content', 'position', 'published',
])]
class Page extends Model
{
    /** @use HasFactory<\Database\Factories\PageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'published' => 'boolean',
            'is_system' => 'boolean',
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
     * Kanoniczny adres strony na storefroncie: /informacje/{id}-{slug} (styl
     * PrestaShop — szukamy po ID, slug to ozdoba SEO). Względny, bo storefront
     * to jeden host; unika parametru {shop} przy generowaniu URLi.
     */
    public function storefrontPath(): string
    {
        return '/informacje/'.$this->id.'-'.$this->slug;
    }

    /**
     * Tylko opublikowane strony (widoczne na storefroncie), w ustalonej kolejności.
     *
     * @param  Builder<Page>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('published', true)->orderBy('position');
    }
}
