<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\AppearanceRequest;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Wygląd sklepu — identyfikacja wizualna (logo; docelowo kolory i szablony).
 * Wydzielone z profilu sklepu na osobną pozycję lewego menu, żeby grupować
 * konfigurację po charakterze danych. Edycja przez POST (FOUNDATION sek. 5).
 */
class AppearanceController extends Controller
{
    public function edit(Request $request): Renderable|RedirectResponse
    {
        $shop = $request->user()->shop;

        if ($shop === null) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.appearance.edit', ['shop' => $shop]);
    }

    public function update(AppearanceRequest $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        if ($request->boolean('remove_logo') && $shop->logo_path !== null) {
            Storage::disk('public')->delete($shop->logo_path);
            $shop->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            // Podmiana: kasujemy stary plik, żeby nie zostawiać sierot na dysku.
            if ($shop->logo_path !== null) {
                Storage::disk('public')->delete($shop->logo_path);
            }
            $shop->logo_path = $request->file('logo')->store('shops/'.$shop->id, 'public');
        }

        $shop->save();

        return redirect()
            ->route('seller.appearance.edit')
            ->with('success', 'Zapisano wygląd sklepu.');
    }
}
