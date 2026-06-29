<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Renderable
    {
        $shop = $request->user()->shop;
        $productCount = $shop ? $shop->products()->count() : 0;

        // Ścieżka „kim jesteś → jak wyglądasz → idź na żywo". Kolejność = realne kroki
        // do pokazania sklepu klientom; ostatni (pierwszy produkt) publikuje sklep.
        // Każdy krok prowadzi do konkretnej sekcji (kotwica). Liczone z danych.
        $steps = $shop ? [
            ['label' => 'Dane sklepu', 'desc' => 'Adres prowadzenia działalności.', 'done' => $shop->addressComplete(), 'route' => 'seller.shop.edit', 'anchor' => '#adres'],
            ['label' => 'Dane firmowe', 'desc' => 'Nazwa firmy i NIP.', 'done' => filled($shop->nip), 'route' => 'seller.shop.edit', 'anchor' => '#dane-firmowe'],
            ['label' => 'Opis sklepu', 'desc' => 'Krótko o tym, co sprzedajesz.', 'done' => filled($shop->description), 'route' => 'seller.shop.edit', 'anchor' => '#dane-podstawowe'],
            ['label' => 'Logo sklepu', 'desc' => 'Wizytówka Twojej marki.', 'done' => filled($shop->logo_path), 'route' => 'seller.appearance.edit', 'anchor' => '#logo'],
            ['label' => 'Pierwszy produkt', 'desc' => 'Dodaj produkt — wtedy publikujemy sklep.', 'done' => $productCount > 0, 'route' => 'seller.products.create', 'anchor' => ''],
        ] : [];

        return view('seller.dashboard', [
            'shop' => $shop,
            'steps' => $steps,
            'productCount' => $productCount,
            'done' => collect($steps)->where('done', true)->count(),
            'total' => count($steps),
        ]);
    }
}
