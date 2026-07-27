<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Teksty i adresy dla nagłówka `<head>` storefrontu: opis do wyników Google,
 * adres kanoniczny i obrazek do udostępniania w social mediach.
 *
 * Audyt ursalogic (16.07.2026): 100% podstron bez `meta description` i bez
 * `canonical`. Skutek jest podwójny — Google wycina losowy fragment strony
 * zamiast zachęty, a link wklejony na Facebooka pokazuje się jako goła nazwa.
 *
 * ZASADA: nigdy nie zostawiamy pustego opisu. Kolejność źródeł to
 * (1) tekst zapisany przez sprzedawcę, (2) skrót jego własnej treści,
 * (3) zdanie złożone z FAKTÓW (nazwa, cena, sklep). Trzeci wariant nigdy nie
 * obiecuje niczego, czego nie wiemy — żadnej „darmowej dostawy" ani „promocji",
 * bo to zależy od ustawień konkretnego sklepu.
 */
class Seo
{
    /** Google i tak ucina dłuższe opisy — nie ma sensu wysyłać więcej. */
    public const MAX_DESCRIPTION = 155;

    /**
     * Opis strony głównej sklepu: własny opis sprzedawcy, a gdy go nie napisał —
     * zdanie z nazwy i miasta.
     */
    public static function shopDescription(Shop $shop): string
    {
        $own = self::clip($shop->aboutPlainText());

        if ($own !== '') {
            return $own;
        }

        $where = filled($shop->city) ? ' z '.$shop->city : '';

        return self::clip($shop->name.' — sklep internetowy'.$where.'. Zobacz ofertę i zamów online.');
    }

    /**
     * Opis karty produktu. Bez opisu sprzedawcy schodzimy do faktów: nazwa, cena
     * i sklep. Celowo NIE piszemy o dostawie — jeden sklep wysyła kurierem, inny
     * daje wyłącznie odbiór osobisty.
     */
    public static function productDescription(Product $product, Shop $shop): string
    {
        $own = self::clip(Excerpt::plainText($product->description));

        if ($own !== '') {
            return $own;
        }

        return self::clip($product->name.' — '.Money::pln($product->price_gross).'. Kup online w sklepie '.$shop->name.'.');
    }

    /**
     * Opis strony tekstowej („Informacje"): skrót treści, a gdy pusta — tytuł
     * z nazwą sklepu.
     */
    public static function pageDescription(Page $page, Shop $shop): string
    {
        $own = self::clip($page->plainContent());

        return $own !== '' ? $own : self::clip($page->title.' — '.$shop->name.'.');
    }

    /**
     * Opis wykazu produktów.
     */
    public static function listingDescription(Shop $shop): string
    {
        return self::clip('Cała oferta sklepu '.$shop->name.'. Przeglądaj produkty i zamów online.');
    }

    /**
     * Obrazek do social mediów. Na razie logo sklepu (absolutny adres, bo
     * Facebook nie rozwiąże ścieżki względnej); generowana grafika 1200×630
     * dojdzie osobnym krokiem i wejdzie dokładnie tutaj.
     */
    public static function shopImage(Shop $shop): ?string
    {
        return $shop->logo_path ? Storage::disk('public')->url($shop->logo_path) : null;
    }

    /**
     * Obrazek karty produktu: zdjęcie główne, a gdy produkt go nie ma —
     * grafika sklepu.
     */
    public static function productImage(Product $product, Shop $shop): ?string
    {
        return $product->mainImage()?->url() ?? self::shopImage($shop);
    }

    /**
     * Adres kanoniczny bieżącej strony: schemat, host i ścieżka BEZ parametrów,
     * z jednym wyjątkiem — numer strony zostaje, bo druga strona wykazu to inna
     * treść i ma wskazywać samą siebie (zalecenie Google dla paginacji).
     */
    public static function canonical(): string
    {
        $page = (int) request()->query('page', 1);
        $url = url()->current();

        return $page > 1 ? $url.'?page='.$page : $url;
    }

    /**
     * Skrót do długości meta opisu: czysty tekst w jednej linii, ucięty po
     * pełnym słowie (konwencja projektu — nie tniemy w połowie wyrazu).
     */
    public static function clip(?string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text) ?? '');

        return $text === '' ? '' : Str::limit($text, self::MAX_DESCRIPTION, '…', preserveWords: true);
    }
}
