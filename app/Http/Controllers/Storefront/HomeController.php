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
            'totalProducts' => $shop->products()->where('is_active', true)->count(),
        ]);
    }

    /**
     * Produkty na stronę główną: wyróżnione przez sprzedawcę (`show_on_homepage`),
     * do sufitu z configu ([[limit 6]]). Gdy żaden nie wyróżniony — najnowsze
     * aktywne jako sensowna domyślna witryna (główna nigdy nie jest pusta).
     * Liczba wyników steruje układem głównej (1/2/3 mają dedykowane aranżacje).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Product>
     */
    private function homepageProducts(Shop $shop): \Illuminate\Support\Collection
    {
        $limit = (int) config('shop.homepage_promoted_limit');

        $active = $shop->products()->where('is_active', true)->with('images');

        $promoted = (clone $active)->where('show_on_homepage', true)
            ->latest()->take($limit)->get();

        return $promoted->isNotEmpty()
            ? $promoted
            : $active->latest()->take($limit)->get();
    }
}
