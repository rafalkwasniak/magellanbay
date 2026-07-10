<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Renderable
    {
        $shop = $request->user()->shop;
        $productCount = $shop ? $shop->products()->count() : 0;
        $activeProductCount = $shop ? $shop->products()->where('is_active', true)->count() : 0;

        // Sprzedaż z ostatnich 30 dni — realne liczby zamiast zer na kafelkach.
        // Anulowane zamówienia nie liczą się ani do sztuk, ani do przychodu.
        // Wyświetlenia/ruch: brak trackingu → kafelek świadomie usunięty z Pulpitu
        // (wróci przy module analityki), zamiast straszyć placeholderem „wkrótce".
        $recentOrders = $shop
            ? $shop->orders()
                ->where('created_at', '>=', now()->subDays(30))
                ->where('status', '!=', OrderStatus::Cancelled->value)
                ->get(['id', 'total_gross'])
            : collect();

        $orderCount = $recentOrders->count();
        $revenue = (float) $recentOrders->sum(fn ($order) => (float) $order->total_gross);

        // Ścieżka „kim jesteś → jak wyglądasz → idź na żywo". Kolejność = realne kroki
        // do pokazania sklepu klientom; ostatni (widoczny produkt) publikuje sklep —
        // dlatego liczymy AKTYWNE produkty, nie wszystkie (ukryty produkt nie publikuje).
        // Każdy krok prowadzi do konkretnej sekcji (kotwica). Liczone z danych.
        $steps = $shop ? [
            ['label' => 'Dane sklepu', 'desc' => 'Adres prowadzenia działalności.', 'done' => $shop->addressComplete(), 'route' => 'seller.shop.edit', 'anchor' => '#adres'],
            ['label' => 'Dane kontaktowe', 'desc' => 'E-mail i telefon dla klientów.', 'done' => $shop->contactComplete(), 'route' => 'seller.shop.edit', 'anchor' => '#dane-kontaktowe'],
            ['label' => 'Dane firmowe', 'desc' => 'Nazwa firmy i NIP.', 'done' => filled($shop->nip), 'route' => 'seller.shop.edit', 'anchor' => '#dane-firmowe'],
            ['label' => 'O sklepie', 'desc' => 'Krótko o tym, co sprzedajesz.', 'done' => filled($shop->description), 'route' => 'seller.shop.edit', 'anchor' => '#dane-podstawowe'],
            ['label' => 'Logo sklepu', 'desc' => 'Wizytówka Twojej marki.', 'done' => filled($shop->logo_path), 'route' => 'seller.appearance.edit', 'anchor' => '#logo'],
            ['label' => 'Widoczny produkt', 'desc' => 'Dodaj produkt i ustaw go jako aktywny — wtedy sklep staje się widoczny.', 'done' => $activeProductCount > 0, 'route' => 'seller.products.create', 'anchor' => ''],
        ] : [];

        return view('seller.dashboard', [
            'shop' => $shop,
            'steps' => $steps,
            'productCount' => $productCount,
            'activeProductCount' => $activeProductCount,
            'orderCount' => $orderCount,
            'revenue' => $revenue,
            'unseenOrders' => (int) ($shop?->unseen_orders_count ?? 0),
            'done' => collect($steps)->where('done', true)->count(),
            'total' => count($steps),
        ]);
    }
}
