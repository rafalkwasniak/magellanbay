<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\ShopStatus;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Support\PackageAttention;
use App\Support\PackageRevenue;
use Illuminate\Contracts\Support\Renderable;

class DashboardController extends Controller
{
    public function __invoke(): Renderable
    {
        $attention = PackageAttention::groups();

        return view('administrator.dashboard', [
            // Realne, operacyjne dane platformy.
            'shopsCount' => Shop::count(),
            'activeShopsCount' => Shop::where('status', ShopStatus::Active)->count(),
            'recentShops' => Shop::with('owner')->latest()->limit(6)->get(),

            // Pieniądze z TEGO SAMEGO źródła co dział „Pakiety" — dwa ekrany
            // podające dwie różne kwoty przychodu byłyby gorsze niż jeden.
            // (Do 2026-08-11 stały tu zera z komentarzem „powstaną z billingiem";
            // rejestr opłat istniał już wcześniej, więc pulpit kłamał nad gotowymi
            // danymi. Liczby obejmują też wpłaty wpisane ręcznie.)
            'revenue' => PackageRevenue::revenue(),

            // Licznik spraw do załatwienia — pulpit jest pierwszym ekranem po
            // zalogowaniu, więc kończący się abonament ma się rzucić w oczy tutaj,
            // a nie dopiero po wejściu w Pakiety.
            'attentionCount' => array_sum(array_map(fn (array $group): int => count($group['items']), $attention)),
        ]);
    }
}
