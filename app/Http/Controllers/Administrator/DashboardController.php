<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\ShopStatus;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Contracts\Support\Renderable;

class DashboardController extends Controller
{
    public function __invoke(): Renderable
    {
        return view('administrator.dashboard', [
            // Realne, operacyjne dane platformy.
            'shopsCount' => Shop::count(),
            'activeShopsCount' => Shop::where('status', ShopStatus::Active)->count(),
            'recentShops' => Shop::with('owner')->latest()->limit(6)->get(),

            // Metryki SPRZEDAŻY SaaS (Twój przychód z abonamentów) — docelowe kafelki,
            // na razie 0. Wymagają rejestru sprzedaży/odnowień, który powstanie z
            // billingiem (Faza 4). WTEDY podmieniamy TYLKO te trzy pola — reszta
            // pulpitu (kafelek Sklepy, lista) już jest realna.
            'subscriptionsSold' => 0,
            'saasRevenueTotal' => 0.0,
            'saasRevenue12m' => 0.0,
        ]);
    }
}
