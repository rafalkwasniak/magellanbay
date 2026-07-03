<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Strona koszyka storefrontu (`/koszyk`). Koszyk to własność kupującego (sesja),
 * więc nie podlega bramce widoczności sklepu — po prostu renderujemy powłokę,
 * a zawartość i akcje obsługuje komponent Livewire `Cart`.
 */
class CartController extends Controller
{
    public function show(Request $request): View
    {
        $shop = $request->attributes->get('shop');

        return view('storefront.cart', ['shop' => $shop]);
    }
}
