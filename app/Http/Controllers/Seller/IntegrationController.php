<?php

namespace App\Http\Controllers\Seller;

use App\Enums\IntegrationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\IntegrationRequest;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Integracje sklepu (kategoria 3 z plan-shop-settings-storage). Tu sprzedawca
 * KONFIGURUJE usługi (wpisuje identyfikatory/klucze); WŁĄCZA/wyłącza je w
 * Ustawieniach. Na razie jedna integracja: Google Analytics / Tag Manager.
 * Edycja przez POST (FOUNDATION sek. 5).
 */
class IntegrationController extends Controller
{
    public function edit(Request $request): Renderable|RedirectResponse
    {
        $shop = $request->user()->shop;

        if ($shop === null) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.integrations.edit', [
            'shop' => $shop,
            'googleAnalyticsId' => $shop->googleAnalyticsId(),
        ]);
    }

    public function update(IntegrationRequest $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        $id = $request->validated()['google_analytics_id'] ?? null;

        $integration = $shop->integration(IntegrationType::GoogleAnalytics);

        if (blank($id)) {
            // Wyczyszczenie ID = usunięcie integracji (z nią znika też włącznik).
            $integration?->delete();
        } elseif ($integration !== null) {
            $integration->update(['config' => ['tracking_id' => $id]]);
        } else {
            // Pierwsza konfiguracja: od razu włączona (mniej klikania — analogia
            // do przelewu; sprzedawca i tak może ją wyłączyć w Ustawieniach).
            $shop->integrations()->create([
                'type' => IntegrationType::GoogleAnalytics,
                'enabled' => true,
                'config' => ['tracking_id' => $id],
            ]);
        }

        return redirect()
            ->route('seller.integrations.edit')
            ->with('success', 'Zapisano ustawienia integracji.');
    }
}
