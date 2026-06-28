<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ShopProfileRequest;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Edycja profilu sklepu w centrali (nazwa, opis, adres). Sprzedawca zarządza
 * własnym sklepem tutaj — nie na subdomenie. Slug/subdomena pozostają stałe.
 */
class ShopProfileController extends Controller
{
    public function edit(Request $request): Renderable|RedirectResponse
    {
        $shop = $request->user()->shop;

        if ($shop === null) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.shop.edit', ['shop' => $shop]);
    }

    public function update(ShopProfileRequest $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        // Pola skalarne; plik i flaga usunięcia obsługiwane osobno poniżej.
        $shop->fill($request->safe()->except(['logo', 'remove_logo']));

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
            ->route('seller.shop.edit')
            ->with('success', 'Zapisano dane sklepu.');
    }
}
