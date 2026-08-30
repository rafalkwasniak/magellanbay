<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\AnalyticsPeriod;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\ShopAnalytics;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * Dział „Analityka" administratora: ten sam ekran co u sprzedawcy, tylko liczony
 * dla CAŁEJ PLATFORMY (`ShopAnalytics::for(null, …)`) — jeden serwis, więc admin
 * i sprzedawca nie zobaczą dwóch różnych prawd o tych samych zamówieniach.
 *
 * Filtry: okres (`okres`) i sklep (`sklep`). Bez wskazania sklepu liczby są
 * sumaryczne. Nieznane id sklepu wraca do przekroju platformy — tak samo jak
 * nieznany okres wraca do wartości domyślnej, zamiast wywracać ekran.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request, ShopAnalytics $analytics): Renderable
    {
        $period = AnalyticsPeriod::fromValue($request->query('okres'));
        $shop = Shop::find($request->query('sklep'));

        return view('administrator.analytics.index', [
            'shop' => $shop,
            'period' => $period,
            'periods' => AnalyticsPeriod::cases(),
            // Wszystkie sklepy z bazy — także wyłączone i w karencji na usunięcie:
            // ich sprzedaż wchodzi do sumy, więc muszą dać się też obejrzeć osobno.
            'shops' => Shop::query()->orderBy('name')->get(['id', 'name']),
            'analytics' => $analytics->for($shop, $period),
        ]);
    }
}
