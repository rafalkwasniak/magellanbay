<?php

namespace App\Http\Controllers\Seller;

use App\Enums\IntegrationType;
use App\Enums\SaleUnit;
use App\Enums\VatRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ShopSettingsRequest;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ustawienia sklepu — typowane pola (domyślny VAT), fiszki metod (przelew) oraz
 * WŁĄCZNIKI usług konfigurowanych w Integracjach (Google Analytics). Podział:
 * tutaj włączasz/wyłączasz, konfigurujesz w „Integracje". Edycja przez POST.
 */
class ShopSettingsController extends Controller
{
    public function edit(Request $request): Renderable|RedirectResponse
    {
        $shop = $request->user()->shop;

        if ($shop === null) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.settings.edit', [
            'shop' => $shop,
            'vatRates' => VatRate::cases(),
            'saleUnits' => SaleUnit::cases(),
            'googleAnalyticsId' => $shop->googleAnalyticsId(),
            'googleAnalyticsEnabled' => (bool) $shop->integration(IntegrationType::GoogleAnalytics)?->enabled,
            'fakturowniaConfigured' => $shop->invoicingConfigured(),
            'fakturowniaEnabled' => (bool) $shop->integration(IntegrationType::Invoicing)?->enabled,
            'paynowConfigured' => $shop->onlinePaymentsConfigured(),
            'paynowEnabled' => (bool) $shop->integration(IntegrationType::Payments)?->enabled,
        ]);
    }

    public function update(ShopSettingsRequest $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        // Pola typowane sklepu (VAT, przelew) — bez włączników integracji, które
        // żyją na wierszach shop_integrations, nie na kolumnach shops.
        $shop->fill($request->safe()->except(['google_analytics_enabled', 'fakturownia_enabled', 'paynow_enabled']));
        $shop->save();

        // Włączniki działają tylko, gdy integracja jest skonfigurowana (istnieje
        // wiersz). Bez konfiguracji checkbox jest wyłączony i nie ma czego przełączać.
        $shop->integration(IntegrationType::GoogleAnalytics)
            ?->update(['enabled' => $request->boolean('google_analytics_enabled')]);

        $shop->integration(IntegrationType::Invoicing)
            ?->update(['enabled' => $request->boolean('fakturownia_enabled')]);

        $shop->integration(IntegrationType::Payments)
            ?->update(['enabled' => $request->boolean('paynow_enabled')]);

        return redirect()
            ->route('seller.settings.edit')
            ->with('success', 'Zapisano ustawienia sklepu.');
    }
}
