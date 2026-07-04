<?php

namespace App\Support;

use App\Models\Shop;
use Illuminate\Support\Facades\Storage;

/**
 * Branding maili. Zwraca tablicę gotową do wstrzyknięcia w szablon (`<x-mail.*>`),
 * a nie zaszywa kolorów w widoku — dzięki temu ten sam komponent renderuje maile
 * platformy (Kramio) i „od sklepu".
 *
 * Kolor podajemy PŁASKO (`brand` + `brand_ink`), bez gradientu — maile sklepu i
 * Kramio mają być spójne. Sklep dostaje kolory swojego motywu (`themeTokens`),
 * nazwę i logo; gdy nie ma logo, w miejscu logo pokazujemy nazwę sklepu (`glyph`
 * = null → sama nazwa, bez znaku ◐ zarezerwowanego dla Kramio).
 */
class MailBranding
{
    /**
     * Branding dla danego sklepu po `shop_id`. Brak sklepu (mail platformy, np.
     * aktywacja) lub sklep nieodnaleziony → domyślny system Kramio.
     *
     * @return array<string, string|null>
     */
    public static function for(?int $shopId): array
    {
        if ($shopId === null) {
            return self::system();
        }

        $shop = Shop::find($shopId);

        return $shop === null ? self::system() : self::forShop($shop);
    }

    /**
     * Paleta systemowa (Kramio): amber na płasko, znak ◐ w miejscu logo.
     *
     * @return array<string, string|null>
     */
    public static function system(): array
    {
        return [
            'name' => config('app.name'),
            'glyph' => '◐',             // znak marki Kramio, gdy brak pliku logo
            'logo_url' => null,
            'brand' => '#f59e0b',       // amber-500 — przycisk/akcent (płaski, dawniej gradient amber→rose)
            'brand_ink' => '#ffffff',   // tekst na kolorze brand (np. na przycisku)
            'accent' => '#b45309',      // amber-700 — linki
            'text' => '#1c1917',        // stone-900
            'muted' => '#78716c',       // stone-500
            'page_bg' => '#f5f5f4',     // stone-100
        ];
    }

    /**
     * Paleta konkretnego sklepu: kolory z jego motywu (`brand`/`brand_ink`/`ink`/
     * `surface`), nazwa i logo. Drugorzędny tekst (`muted`) zostaje neutralny dla
     * czytelności. Brak tokenu → fallback do wartości systemowych.
     *
     * @return array<string, string|null>
     */
    private static function forShop(Shop $shop): array
    {
        $tokens = $shop->themeTokens();
        $system = self::system();

        return [
            'name' => $shop->name,
            'glyph' => null,            // brak logo → sama nazwa sklepu (nie znak Kramio)
            'logo_url' => self::logoUrl($shop),
            'brand' => $tokens['brand'] ?? $system['brand'],
            'brand_ink' => $tokens['brand_ink'] ?? $system['brand_ink'],
            'accent' => $tokens['brand'] ?? $system['accent'],   // linki w kolorze marki sklepu
            'text' => $tokens['ink'] ?? $system['text'],
            'muted' => $system['muted'],
            'page_bg' => $tokens['surface'] ?? $system['page_bg'],
        ];
    }

    /**
     * Absolutny URL logo sklepu (maile potrzebują pełnego, publicznego adresu).
     * Dysk `public` zwraca już `APP_URL/storage/...`. Null, gdy sklep bez logo.
     */
    private static function logoUrl(Shop $shop): ?string
    {
        if (blank($shop->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($shop->logo_path);
    }
}
