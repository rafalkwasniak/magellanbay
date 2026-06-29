<?php

namespace App\Http\Controllers\Seller;

use App\Enums\VatRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ShopSettingsRequest;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ustawienia sklepu — typowane pola konfiguracyjne (na razie domyślna stawka
 * VAT). Osobna pozycja lewego menu; integracje zaawansowane (GA, płatności)
 * dojdą później jako odrębny moduł. Edycja przez POST (FOUNDATION sek. 5).
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
        ]);
    }

    public function update(ShopSettingsRequest $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        $shop->fill($request->validated());
        $shop->save();

        return redirect()
            ->route('seller.settings.edit')
            ->with('success', 'Zapisano ustawienia sklepu.');
    }
}
