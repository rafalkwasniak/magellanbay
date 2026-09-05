<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Pozycja biblioteki — jedna grafika albo wariant do wyboru, z własną dopłatą.
 *
 * WYCOFANEJ POZYCJI NIE KASUJEMY, tylko gasimy `is_active`. Skasowanie
 * unieważniłoby historyczne zamówienia, w których ktoś ją wybrał — a arkusz
 * produkcyjny, reklamacja i zwrot muszą wiedzieć, co dokładnie zamówiono.
 * Ta sama zasada co przy produktach (`deleted_at`, nie `DELETE`).
 */
#[Fillable(['label', 'image_path', 'surcharge_gross', 'licensor_id', 'licence_fee_gross', 'is_active', 'position'])]
class OptionChoice extends Model
{
    protected function casts(): array
    {
        return [
            'surcharge_gross' => 'decimal:2',
            'licence_fee_gross' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Partner, któremu należy się opłata za tę pozycję.
     *
     * `licence_fee_gross` jest ODDZIELNE od `surcharge_gross`, bo rządzi nim inna
     * arytmetyka: dopłaty się sumują, opłaty licencyjne podlegają regule „suma
     * po firmach, maksimum wewnątrz jednej" (patrz App\Support\LicenceFees).
     *
     * @return BelongsTo<Licensor, $this>
     */
    public function licensor(): BelongsTo
    {
        return $this->belongsTo(Licensor::class);
    }

    /**
     * @return BelongsTo<OptionGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }

    /**
     * Adres podglądu grafiki — ta sama konwencja co przy zdjęciach produktu
     * (`ProductImage::url()`), bo to ten sam dysk i ten sam sposób serwowania.
     *
     * DLACZEGO PODGLĄD W OGÓLE JEST POTRZEBNY: klient wybiera grafikę
     * Z BIBLIOTEKI i płaci za nią opłatę licencyjną. Wybór grafiki, której się
     * nie widzi, jest absurdem — a zapłacenie licencji za coś niewidocznego
     * tym bardziej. W specyfikacji nie ma o tym słowa, bo jest to zbyt
     * oczywiste, żeby pisać (uwaga Rafała, 05.09).
     *
     * Do ZAMÓWIENIA i tak idzie sama etykieta: właściciel wie, o którą grafikę
     * chodzi, a arkusz produkcyjny bierze plik po identyfikatorze pozycji.
     */
    public function imageUrl(): ?string
    {
        return blank($this->image_path)
            ? null
            : Storage::disk('public')->url($this->image_path);
    }

    /**
     * Tylko pozycje, które kupujący ma prawo dziś wybrać.
     *
     * @param  Builder<OptionChoice>  $query
     */
    public function scopeSelectable(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position');
    }
}
