<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Contracts\Support\Renderable;

/**
 * Konsola admina — sklepy. Lista wszystkich sklepów platformy z pakietem,
 * ceną i stanem, jako wejście do ręcznego zarządzania uprawnieniami/ceną
 * per sklep (edytor — osobny komponent). Widok tylko dla roli admin
 * (middleware `role:admin` na grupie tras).
 */
class ShopController extends Controller
{
    public function index(): Renderable
    {
        $shops = Shop::query()
            ->with('owner')
            ->withCount('products')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('administrator.shops.index', ['shops' => $shops]);
    }

    public function edit(Shop $shop): Renderable
    {
        return view('administrator.shops.edit', ['shop' => $shop->load('owner')]);
    }
}
