<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Strona główna storefrontu (publiczny sklep na subdomenie). Sklep rozwiązuje
 * middleware ResolveShop; tutaj decydujemy tylko, czy pokazać sklep, czy ekran
 * „już wkrótce". Szkic (brak aktywnych produktów) widzi publicznie tylko ekran
 * przygotowania; właściciel i administrator dostają podgląd sklepu.
 */
class HomeController extends Controller
{
    public function show(Request $request): View
    {
        $shop = $request->attributes->get('shop');

        if (! $shop->isVisible() && ! $shop->canBePreviewedBy($request->user())) {
            return view('storefront.coming-soon', ['shop' => $shop]);
        }

        return view('storefront.home', [
            'shop' => $shop,
            'products' => $this->homepageProducts($shop),
            'contentTiles' => $this->contentTiles($shop),
            'tagCloud' => $this->tagLinks($shop),
        ]);
    }

    /**
     * Kafelki treści pod ofertą: pod promowanymi produktami stają promowane
     * treści — sprzedawca opowiada o sobie, wywiadach czy spotkaniu autorskim,
     * a nie tylko wystawia towar.
     *
     * „O sklepie" jest tu ZWYKŁYM kafelkiem, tyle że pierwszym. Jedyne, czym się
     * różni, to skąd bierze treść (`shop.description`, bo nie jest wierszem w
     * `pages`) — układ o tym nie wie. Dzięki temu reguła jest jedna: policz
     * kafelki, ułóż 1/2/3 w rzędzie.
     *
     * Sufit 2 stron (+ ewentualne „O sklepie") = najwyżej 3 kafelki, więc siatka
     * nigdy się nie zawija. `take()` to siatka bezpieczeństwa na wypadek, gdyby
     * w bazie zostało więcej wyróżnień, niż pozwala dzisiejszy config.
     *
     * Strony bez treści wypadają: pusty kafelek to dziura w siatce. Zapisu nie
     * blokujemy (co sprzedawca pisze, to jego sprawa) — po prostu licznik
     * kafelków sam zejdzie z 3 na 2.
     *
     * Kafelek ma dwa stany, rozstrzygane przez `hasMore` (patrz App\Support\Excerpt):
     * treść, która się MIEŚCI, jedzie w całości i z formatowaniem (`html`) — nie ma
     * dokąd linkować, bo cel miałby to samo, a linki w treści są klikalne na
     * miejscu. Treść UCIĘTA jedzie jako czysty wycinek (`text`) plus „Czytaj
     * więcej". Dlatego niepotrzebne pole jest nullowane, a nie wypełniane na zapas.
     *
     * @return list<array{title: string, text: string, html: ?string, url: string, hasMore: bool}>
     */
    private function contentTiles(Shop $shop): array
    {
        $tiles = [];

        if ($shop->hasAbout()) {
            $about = $shop->aboutExcerpt();

            $tiles[] = [
                'title' => (string) config('pages.about.title'),
                'text' => $about->text,
                'html' => $about->hasMore ? null : $shop->description,
                'url' => $shop->aboutPath(),
                'hasMore' => $about->hasMore,
            ];
        }

        $pages = $shop->pages()->onHomepage()
            ->take((int) config('pages.homepage_promoted_limit'))
            ->get();

        foreach ($pages as $page) {
            if (! $page->hasContent()) {
                continue;
            }

            $excerpt = $page->excerpt();

            $tiles[] = [
                'title' => $page->title,
                'text' => $excerpt->text,
                'html' => $excerpt->hasMore ? null : $page->content,
                'url' => $page->storefrontPath(),
                'hasMore' => $excerpt->hasMore,
            ];
        }

        return $tiles;
    }

    /**
     * Chmura tagów na główną — wejścia do przefiltrowanego wykazu. Każda pigułka
     * prowadzi do `/produkty?tagi=<slug>` (bez licznika, bez stanu aktywnego).
     *
     * @return list<array{name: string, count: null, url: string, active: bool}>
     */
    private function tagLinks(Shop $shop): array
    {
        return $shop->activeTagsByPopularity()
            ->map(fn ($tag): array => [
                'name' => $tag->name,
                'count' => null,
                'url' => '/produkty?tagi='.$tag->slug,
                'active' => false,
            ])
            ->all();
    }

    /**
     * Produkty na stronę główną: wyróżnione przez sprzedawcę (`show_on_homepage`),
     * do sufitu z configu ([[limit 6]]). Gdy żaden nie wyróżniony — kilka LOSOWYCH
     * aktywnych (config `homepage_fallback_count`), żeby główna nie była pusta i za
     * każdym wejściem eksponowała inne pozycje. Liczba wyników steruje układem
     * głównej (kafelek dla 1, jednolita siatka dla 2+).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Product>
     */
    private function homepageProducts(Shop $shop): \Illuminate\Support\Collection
    {
        $active = $shop->products()->where('is_active', true)->with('images');

        $promoted = (clone $active)->where('show_on_homepage', true)
            ->latest()->take((int) config('shop.homepage_promoted_limit'))->get();

        return $promoted->isNotEmpty()
            ? $promoted
            : $active->inRandomOrder()->take((int) config('shop.homepage_fallback_count'))->get();
    }
}
