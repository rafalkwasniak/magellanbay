<?php

namespace App\Models;

use App\Enums\OptionGroupKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupa opcji produktu — jeden „blok pytań" zadawany kupującemu przy zakupie.
 *
 * Definiowana RAZ per sklep i przypinana do wielu produktów: „Nadruk 3 linie"
 * obsługuje sto magnesów, a zmiana limitu znaków poprawia je wszystkie naraz.
 *
 * Nazewnictwo jest celowo bezbarwne — `OptionGroup`, nie `Personalisation`
 * ani `Engraving`. To ma być część generyczna, sprzedawalna kolejnym klientom
 * (CLAUDE.md sek. 1), a nazwa zaczerpnięta od pierwszego zamawiającego
 * zaciążyłaby nad każdym następnym.
 *
 * @property-read OptionGroupKind $kind
 */
#[Fillable(['name', 'kind', 'hint', 'required', 'surcharge_gross', 'excludes_group_id', 'position'])]
class OptionGroup extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => OptionGroupKind::class,
            'required' => 'boolean',
            'surcharge_gross' => 'decimal:2',
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
     * Pola formatki — mają sens wyłącznie przy `kind = text`.
     *
     * @return HasMany<OptionField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(OptionField::class)->orderBy('position');
    }

    /**
     * Pozycje biblioteki — mają sens wyłącznie przy `kind = choice`.
     *
     * @return HasMany<OptionChoice, $this>
     */
    public function choices(): HasMany
    {
        return $this->hasMany(OptionChoice::class)->orderBy('position');
    }

    /**
     * Grupa, z którą ta się wyklucza (grawer: grafika ALBO tekst).
     *
     * @return BelongsTo<OptionGroup, $this>
     */
    public function excludes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'excludes_group_id');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function isText(): bool
    {
        return $this->kind === OptionGroupKind::Text;
    }

    public function isChoice(): bool
    {
        return $this->kind === OptionGroupKind::Choice;
    }

    /**
     * Czy grupa jest gotowa do pokazania kupującemu.
     *
     * Grupa bez pól albo bez ani jednej AKTYWNEJ pozycji jest pustym pytaniem —
     * w kasie wyglądałaby jak usterka, a przy `required` zablokowałaby zakup
     * zupełnie. Panel używa tego, żeby ostrzec sprzedawcę, zamiast wypuszczać
     * taką grupę na storefront.
     */
    public function isReady(): bool
    {
        return $this->isText()
            ? $this->fields()->exists()
            : $this->choices()->where('is_active', true)->exists();
    }

    /**
     * @param  Builder<OptionGroup>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }
}
