<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Licencjodawca — firma inkasująca opłatę za użycie swojego znaku lub grafiki.
 *
 * Organizator biegu, klub, wydawca.
 *
 * KARTOTEKA ZOSTAJE PRYWATNA, NAZWA JEST PUBLICZNA. Osoba kontaktowa, e-mail,
 * numer umowy, notatki i stawki widzi wyłącznie sprzedawca — kupujący widzi
 * opłatę w rozbiciu ceny, ale nie ma powodu znać warunków, na jakich sklep ją
 * pobiera. Jawna jest sama nazwa i lista produktów: jedno i drugie widnieje
 * przecież na magnesie, a partner dostaje dzięki temu link, który wyśle swoim
 * ludziom (specyfikacja: „ekran prezentujący wszystkie produkty tylko wybranej
 * firmy").
 *
 * WYCOFANEGO PARTNERA GASIMY, NIE KASUJEMY. Rozliczenia historyczne muszą dalej
 * wskazywać, komu należały się pieniądze za sprzedaż sprzed roku — a skasowanie
 * wiersza zamieniłoby raport w listę kwot bez adresata.
 */
#[Fillable(['name', 'slug', 'contact_email', 'contact_person', 'agreement_reference', 'notes', 'is_active'])]
class Licensor extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Pozycje biblioteki objęte licencją tego partnera.
     *
     * @return HasMany<OptionChoice, $this>
     */
    public function choices(): HasMany
    {
        return $this->hasMany(OptionChoice::class);
    }

    /**
     * Składniki ceny należne temu partnerowi — podstawa rozliczenia.
     *
     * @return HasMany<OrderItemComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(OrderItemComponent::class);
    }

    /**
     * Produkty objęte licencją tego partnera — z OBU stron magnesu.
     *
     * Awers: znak jest na produkcie na stałe (`products.licensor_id`).
     * Rewers: grafika graweru z jego logo leży w bibliotece i kupujący MOŻE ją
     * wybrać. Jedno i drugie to „produkt tej firmy" w rozumieniu partnera,
     * który pyta, gdzie w tym sklepie jest jego znak.
     *
     * Wygaszone pozycje biblioteki nie liczą się — nikt ich już nie wybierze.
     *
     * @return Builder<Product>
     */
    public function products(): Builder
    {
        return Product::query()
            ->where('shop_id', $this->shop_id)
            ->where(function (Builder $query): void {
                $query->where('licensor_id', $this->id)
                    ->orWhereHas('optionGroups.choices', fn (Builder $choices) => $choices
                        ->where('option_choices.licensor_id', $this->id)
                        ->where('option_choices.is_active', true));
            });
    }

    /**
     * Kanoniczny adres publicznej strony partnera. Względny, jak przy produkcie
     * i kategorii: storefront to jeden host.
     */
    public function storefrontPath(): string
    {
        return '/partner/'.$this->slug;
    }

    /**
     * @param  Builder<Licensor>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
