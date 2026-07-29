<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\AiQuota;
use App\Support\PackageFeatures;
use App\Support\PackageUpgrade;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * „Mój pakiet" — co sprzedawca ma wykupione, do kiedy i co dostanie wyżej.
 *
 * Dotąd nie było takiego miejsca: pakiet dawał się ustawić wyłącznie z konsoli
 * admina, a sprzedawca nie widział ani nazwy pakietu, ani terminu, ani tego, ile
 * z limitów zużył. Ekran jest też docelowym miejscem, do którego skieruje zakup
 * pakietu ze strony głównej.
 *
 * Zakup online jeszcze nie działa (osobny etap — wymaga konta płatniczego
 * platformy), więc zmianę pakietu prowadzimy przez kontakt. Mówimy to wprost,
 * zamiast pokazywać przycisk, który nic nie robi.
 */
class PackageController extends Controller
{
    public function show(Request $request, AiQuota $quota): Renderable
    {
        $shop = $request->user()->shop;

        abort_if($shop === null, 404);

        $productLimit = (int) $shop->entitlement('max_products');
        $aiLimit = (int) $shop->entitlement('ai_weekly_limit');

        return view('seller.package.show', [
            'shop' => $shop,
            'features' => PackageFeatures::forShop($shop),
            // Pakiety wyżej w cenniku — z zaznaczeniem, co w nich dochodzi.
            'upgrades' => collect(PackageFeatures::landing())
                ->filter(fn (array $package): bool => $package['price_yearly'] > $shop->priceYearly())
                ->values()
                ->all(),
            // Wycena przejścia na każdy droższy pakiet — sprzedawca ma widzieć
            // kwotę, nie musieć o nią pytać.
            'quotes' => PackageUpgrade::upgradeQuotes($shop),
            'usage' => [
                'products' => $shop->products()->count(),
                'products_limit' => $productLimit,
                'ai_used' => $quota->used($shop),
                'ai_limit' => $aiLimit,
            ],
            'contactEmail' => config('company.email') ?: config('mail.from.address'),
        ]);
    }
}
