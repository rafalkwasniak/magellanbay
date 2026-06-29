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

        // Realny postęp konfiguracji sklepu — liczony z danych, jakie już mamy.
        $steps = $shop ? [
            ['label' => 'Dane sklepu', 'desc' => 'Adres prowadzenia działalności.', 'done' => $shop->addressComplete(), 'route' => 'seller.shop.edit'],
            ['label' => 'Opis sklepu', 'desc' => 'Krótko o tym, co sprzedajesz.', 'done' => filled($shop->description), 'route' => 'seller.shop.edit'],
            ['label' => 'Logo sklepu', 'desc' => 'Wizytówka Twojej marki.', 'done' => filled($shop->logo_path), 'route' => 'seller.appearance.edit'],
            ['label' => 'Dane firmowe', 'desc' => 'Nazwa firmy i NIP.', 'done' => filled($shop->nip), 'route' => 'seller.shop.edit'],
        ] : [];

        return view('seller.dashboard', [
            'shop' => $shop,
            'steps' => $steps,
            'done' => collect($steps)->where('done', true)->count(),
            'total' => count($steps),
        ]);
    }
}
