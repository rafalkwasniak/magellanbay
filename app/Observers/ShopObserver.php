<?php

namespace App\Observers;

use App\Jobs\GenerateSeoDescription;
use App\Jobs\GenerateShopOgImage;
use App\Models\Shop;

/**
 * Zakłada nowemu sklepowi stronę systemową Regulamin (nieusuwalną). Szkielet
 * treści bierzemy z config/pages.php — sprzedawca uzupełnia go pod swój sklep.
 * `is_system` ustawiamy jawnie, bo nie jest mass-assignable na modelu Page.
 *
 * Pilnuje też grafiki sklepu do social mediów: przerysowuje ją, gdy zmieni się
 * cokolwiek, co na niej widać (nazwa, logo, kolory motywu).
 */
class ShopObserver
{
    /**
     * Pola, których zmiana wymusza nową grafikę Open Graph.
     *
     * @var list<string>
     */
    private const OG_SOURCES = [
        'name', 'logo_path', 'theme', 'template',
        // Z tych pól powstaje zdanie o sklepie widoczne na karcie
        // (Seo::shopTagline: opis SEO → opis „O sklepie" → miasto).
        'meta_description', 'description', 'city',
    ];

    public function created(Shop $shop): void
    {
        $regulamin = config('pages.regulamin');

        $page = $shop->pages()->make([
            'title' => $regulamin['title'],
            'slug' => $regulamin['slug'],
            'content' => $regulamin['content'],
            'position' => 0,
            'published' => true,
        ]);
        $page->is_system = true;
        $page->save();

        GenerateShopOgImage::dispatch($shop);
    }

    /**
     * Grafika OG przerysowuje się tylko wtedy, gdy zmieniło się to, co na niej
     * widać. Bez tego warunku każde zapisanie ustawień sklepu (VAT, dostawa,
     * numer konta) generowałoby obrazek od nowa bez powodu.
     */
    public function updated(Shop $shop): void
    {
        if ($shop->wasChanged(self::OG_SOURCES)) {
            GenerateShopOgImage::dispatch($shop);
        }

        // Opis SEO przepisujemy tylko po zmianie treści, z której powstaje —
        // i nigdy, gdy sprzedawca napisał go ręcznie.
        if (! $shop->meta_description_manual && $shop->wasChanged(['description', 'name'])) {
            GenerateSeoDescription::dispatch($shop);
        }
    }
}
