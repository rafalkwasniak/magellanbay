<?php

namespace App\Models;

use App\Enums\ShopStatus;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sklep jednego sprzedawcy. `slug` = etykieta subdomeny ({slug}.{central_domain}).
 * `domain` = opcjonalna dedykowana domena (np. mojsklep.pl). `owner_id` nie jest
 * mass-assignable — sklep tworzymy przez relację usera.
 */
#[Fillable([
    'name', 'slug', 'domain', 'status', 'description', 'company_name', 'nip', 'logo_path',
    'country', 'province', 'city', 'postal_code', 'street', 'building_number', 'apartment_number',
])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShopStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * Host storefrontu: dedykowana domena, jeśli ustawiona, w przeciwnym razie
     * subdomena {slug}.{central_domain}.
     */
    public function host(): string
    {
        return $this->domain ?: $this->slug.'.'.config('tenancy.central_domain');
    }

    /**
     * Czy adres sklepu jest kompletny (wszystkie wymagane pola wypełnione).
     * Używane m.in. na pulpicie (postęp konfiguracji) i przy publikacji.
     */
    public function addressComplete(): bool
    {
        foreach (['street', 'building_number', 'postal_code', 'city', 'province'] as $field) {
            if (blank($this->{$field})) {
                return false;
            }
        }

        return true;
    }
}
