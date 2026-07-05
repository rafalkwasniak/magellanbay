<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\AppearanceRequest;
use App\Models\ProductImage;
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

        // Losowe zdjęcie produktu sklepu do mini-podglądu szablonu — „to Twój
        // sklep". Gdy sprzedawca nie ma jeszcze żadnego zdjęcia, widok pokazuje
        // neutralny placeholder w kolorze palety.
        $previewImageUrl = ProductImage::query()
            ->whereHas('product', fn ($query) => $query->where('shop_id', $shop->id))
            ->inRandomOrder()
            ->first()?->url();

        return view('seller.appearance.edit', [
            'shop' => $shop,
            'previewImageUrl' => $previewImageUrl,
        ]);
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

        // Szablon, paleta i kolor własny zapisują się jednym submitem. Paletę
        // bierzemy spod klucza wybranego szablonu, więc przełączanie szablonów
        // zapamiętuje wybór palety każdego z nich osobno.
        if ($request->filled('template')) {
            $template = $request->string('template')->toString();
            $palette = $request->input("palettes.{$template}");
            $brandColor = $request->filled('brand_color')
                ? strtoupper($request->string('brand_color')->toString())
                : null;

            // SAFEGUARD: paleta „custom" ma sens tylko z kolorem własnym. Gdy
            // sprzedawca wyczyścił kolor, a wybór został na „custom", cofamy
            // paletę na domyślną szablonu (brak wpisu → resolver bierze default).
            if ($palette === 'custom' && $brandColor === null) {
                $palette = null;
            }

            $theme = array_filter([
                'palette' => filled($palette) ? $palette : null,
                'brand_color' => $brandColor,
            ], fn ($value) => $value !== null);

            $shop->template = $template;
            $shop->theme = $theme !== [] ? $theme : null;
        }

        $shop->save();

        return redirect()
            ->route('seller.appearance.edit')
            ->with('success', 'Zapisano wygląd sklepu.');
    }
}
