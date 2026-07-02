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

        if (! $shop->isVisible() && ! $this->canPreview($request, $shop)) {
            return view('storefront.coming-soon', ['shop' => $shop]);
        }

        return view('storefront.home', ['shop' => $shop]);
    }

    /**
     * Kto może podejrzeć sklep-szkic przed publikacją: jego właściciel i admin.
     */
    private function canPreview(Request $request, Shop $shop): bool
    {
        $user = $request->user();

        return $user !== null && ($user->id === $shop->owner_id || $user->isAdmin());
    }
}
