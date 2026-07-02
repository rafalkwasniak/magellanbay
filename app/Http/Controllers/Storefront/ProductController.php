<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Strona pojedynczego produktu na storefroncie. URL w stylu PrestaShop:
 * /produkt/{id}-{slug} — szukamy po ID (stabilne), slug jest tylko ozdobą SEO.
 * Zły/nieaktualny slug → 301 na kanoniczny adres (jedno miejsce w indeksie).
 * Produkt jest scope'owany do sklepu z subdomeny; nieaktywny widzą wyłącznie
 * właściciel i administrator (podgląd), reszta dostaje 404.
 */
class ProductController extends Controller
{
    /**
     * Wykaz produktów sklepu — pełny katalog aktywnych produktów (paginowany).
     * Sklep-szkic widzi publicznie tylko ekran „już wkrótce"; podgląd dla
     * właściciela/administratora. Kafel = ten sam klocek co na głównej.
     */
    public function index(Request $request): View
    {
        $shop = $request->attributes->get('shop');

        if (! $shop->isVisible() && ! $shop->canBePreviewedBy($request->user())) {
            return view('storefront.coming-soon', ['shop' => $shop]);
        }

        $products = $shop->products()
            ->where('is_active', true)
            ->with('images')
            ->latest()
            ->paginate($shop->productsPerPage())
            ->withQueryString();

        return view('storefront.products', ['shop' => $shop, 'products' => $products]);
    }

    public function show(Request $request, string $product): View|RedirectResponse
    {
        $shop = $request->attributes->get('shop');

        $model = $shop->products()
            ->with(['images', 'priceHistory'])
            ->find((int) $product);

        abort_if($model === null, 404);

        // Publicznie widoczny tylko aktywny produkt w opublikowanym sklepie;
        // szkic sklepu i ukryty produkt widzą wyłącznie właściciel/administrator.
        $public = $shop->isVisible() && $model->is_active;
        abort_if(! $public && ! $shop->canBePreviewedBy($request->user()), 404);

        if ('/produkt/'.$product !== $model->storefrontPath()) {
            return redirect()->to($model->storefrontPath(), 301);
        }

        return view('storefront.product', ['shop' => $shop, 'product' => $model]);
    }
}
