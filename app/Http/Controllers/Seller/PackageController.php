<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\AiQuota;
use App\Services\PackagePaymentService;
use Illuminate\Http\RedirectResponse;
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
            // Zakup online działa dopiero ze skonfigurowanym kontem platformy —
            // bez niego ekran pokazuje ścieżkę kontaktową, żadnych martwych przycisków.
            'onlinePurchase' => filled(config('services.paynow.platform.api_key')),
            // NAJNOWSZA płatność (nie „najnowsza pending"): po odrzuceniu ekran ma
            // powiedzieć „nie udało się, spróbuj ponownie", a ponowienie tworzy
            // nowszy wiersz, który naturalnie przejmuje baner.
            'latestPayment' => $shop->packagePayments()->whereNotNull('payment_id')->latest('id')->first(),
            // Historia opłat za pakiety — log z kwotami i terminami. Bez
            // wierszy-sierot po nieudanym starcie bramki (brak payment_id).
            'payments' => $shop->packagePayments()->whereNotNull('payment_id')->get(),
        ]);
    }

    /**
     * Start zakupu pakietu: wycena → migawka → przekierowanie do Paynow.
     * Kwotę i termin liczy PackageUpgrade — dokładnie te same liczby, które
     * sprzedawca widział na ekranie.
     */
    public function purchase(Request $request, string $package, PackagePaymentService $payments): RedirectResponse
    {
        $shop = $request->user()->shop;

        abort_if($shop === null, 404);
        abort_unless(array_key_exists($package, config('shop.packages')), 404);

        $redirectUrl = $payments->start($shop, $package, route('seller.package.show', ['platnosc' => 'powrot']));

        if ($redirectUrl === null) {
            return redirect()
                ->route('seller.package.show')
                ->with('error', 'Nie udało się rozpocząć płatności. Spróbuj za chwilę albo napisz do nas.');
        }

        return redirect()->away($redirectUrl);
    }
}
