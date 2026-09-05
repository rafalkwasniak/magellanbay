<?php

namespace App\Observers;

use App\Jobs\GenerateSeoDescription;
use App\Jobs\GenerateShopOgImage;
use App\Models\Shop;
use App\Support\Mode;

/**
 * Zakłada nowemu sklepowi strony systemowe (nieusuwalne): Regulamin zawsze,
 * a w trybie dedykowanym także Politykę prywatności. Szkielet treści bierzemy
 * z config/pages.php — właściciel uzupełnia go pod swój sklep.
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
        $this->systemPage($shop, config('pages.regulamin'), 0);

        /*
         * POLITYKA PRYWATNOŚCI TYLKO W TRYBIE DEDYKOWANYM.
         *
         * W Kramio pod adresem polityki renderuje się NASZ dokument: platforma
         * jest tam podmiotem przetwarzającym i to ona opisuje, co dzieje się
         * z danymi. W sklepie dedykowanym platformy nie ma — właściciel jest
         * administratorem danych i gospodarzem serwera naraz, więc polityka
         * musi być jego, edytowalna w panelu jak każda inna strona.
         *
         * Bez tego sklep klienta publikował pod tym adresem dokument cudzego
         * podmiotu (albo pustkę, gdy dokumentu platformy nie wgrano), a odnośnik
         * do niego `informationMenu()` dokleja w nagłówku i stopce ZAWSZE.
         */
        if (Mode::dedicated()) {
            $this->systemPage($shop, config('pages.privacy'), 1);
        }

        GenerateShopOgImage::dispatch($shop);
    }

    /**
     * Strona systemowa — nieusuwalna, od razu opublikowana, ze szkieletem treści
     * z `config/pages.php`. `is_system` ustawiamy jawnie, bo nie jest
     * mass-assignable na modelu Page.
     *
     * @param  array{title: string, slug: string, content: string}  $definicja
     */
    private function systemPage(Shop $shop, array $definicja, int $position): void
    {
        $page = $shop->pages()->make([
            'title' => $definicja['title'],
            'slug' => $definicja['slug'],
            'content' => $definicja['content'],
            'position' => $position,
            'published' => true,
        ]);
        $page->is_system = true;
        $page->save();
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
