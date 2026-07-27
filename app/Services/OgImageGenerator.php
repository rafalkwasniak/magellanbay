<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Składa grafikę sklepu do social mediów (1200×630 PNG) — to, co widzi klient,
 * gdy sprzedawca wklei link do sklepu na Facebooka czy Messengera.
 *
 * DLACZEGO nie samo logo: logo bywa wąskie, wysokie albo przezroczyste, więc
 * jako `og:image` wygląda jak znaczek zgubiony na czarnym tle. Tu logo dostaje
 * własne płótno z marginesami i podpisem, więc karta wygląda na zaprojektowaną.
 *
 * Dwa warianty:
 *  - JEST logo → jasne tło, logo wyśrodkowane z marginesami, pasek w kolorze
 *    przewodnim u dołu. Bez podpisu — logo prawie zawsze samo niesie nazwę;
 *  - BRAK logo → tło w kolorze przewodnim sklepu i sama nazwa w kolorze
 *    kontrastowym (`brand_ink`). Wygląda jak decyzja, nie jak brak grafiki.
 *
 * PNG, nie WebP — Facebook sobie radzi, ale część podglądów i czytników nie.
 */
class OgImageGenerator
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** Margines bezpieczny: logo nigdy nie dotyka krawędzi. */
    private const PADDING = 90;

    /** Pasek marki u dołu wariantu z logo. */
    private const ACCENT_HEIGHT = 14;

    /**
     * Ile razy wolno powiększyć logo. Sprzedawcy wgrywają małe pliki (200×200
     * to typowy przypadek), a nieskalowane logo ginie na płótnie 1200×630;
     * powyżej tego progu robi się papka, więc wolimy mniejsze niż rozmyte.
     */
    private const MAX_LOGO_UPSCALE = 2.4;

    /**
     * Generuje grafikę i zapisuje ją na dysku publicznym. Zwraca ścieżkę pliku.
     *
     * Nazwa pliku niesie skrót ze składników (logo, nazwa, kolor), bo Facebook
     * i LinkedIn cache'ują grafikę PO ADRESIE — bez zmiany nazwy sprzedawca
     * tygodniami widziałby starą wersję po zmianie logo.
     */
    public function generate(Shop $shop): string
    {
        $canvas = $this->compose($shop);

        ob_start();
        imagepng($canvas, null, 6);
        $png = (string) ob_get_clean();

        $path = 'og/'.$shop->id.'/'.$this->fingerprint($shop).'.png';

        Storage::disk('public')->put($path, $png);
        $this->forgetPrevious($shop, $path);

        return $path;
    }

    /**
     * @return \GdImage
     */
    private function compose(Shop $shop)
    {
        $tokens = $shop->themeTokens();
        $logo = $this->loadLogo($shop);

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($canvas, true);

        return $logo !== null
            ? $this->withLogo($canvas, $logo, $shop, $tokens)
            : $this->withoutLogo($canvas, $shop, $tokens);
    }

    /**
     * Wariant z logo: jasne tło (logo bywa przezroczyste i ciemne, więc biel jest
     * najbezpieczniejsza), logo w kadrze z marginesami i pasek marki u dołu,
     * żeby karta nie była pustym prostokątem.
     *
     * ŚWIADOMIE BEZ podpisu z nazwą sklepu: logo prawie zawsze samo ją zawiera,
     * więc dopisek pod spodem byłby powtórzeniem i psuł kompozycję. Nazwa wraca
     * tylko tam, gdzie logo nie ma (patrz `withoutLogo`).
     *
     * @param  \GdImage  $canvas
     * @param  \GdImage  $logo
     * @param  array<string, string>  $tokens
     * @return \GdImage
     */
    private function withLogo($canvas, $logo, Shop $shop, array $tokens)
    {
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $this->color($canvas, '#ffffff'));

        // Kadr na logo: całe płótno minus marginesy i pasek marki. Sprzedawcy
        // wgrywają logo w bardzo różnych rozmiarach (bywa 200×200), więc skalujemy
        // też W GÓRĘ — inaczej mała ikonka ginęłaby na płótnie 1200×630. Sufit
        // powiększenia chroni przed papką z 64-pikselowego pliku.
        $usableHeight = self::HEIGHT - self::ACCENT_HEIGHT;
        $boxWidth = self::WIDTH - 2 * self::PADDING;
        $boxHeight = $usableHeight - 2 * self::PADDING;

        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);
        $scale = min($boxWidth / $logoWidth, $boxHeight / $logoHeight, self::MAX_LOGO_UPSCALE);
        $drawWidth = (int) round($logoWidth * $scale);
        $drawHeight = (int) round($logoHeight * $scale);

        imagecopyresampled(
            $canvas, $logo,
            (int) ((self::WIDTH - $drawWidth) / 2),
            (int) (($usableHeight - $drawHeight) / 2),
            0, 0,
            $drawWidth, $drawHeight, $logoWidth, $logoHeight,
        );

        imagefilledrectangle(
            $canvas, 0, self::HEIGHT - self::ACCENT_HEIGHT, self::WIDTH, self::HEIGHT,
            $this->color($canvas, $tokens['brand'] ?? '#d97706'),
        );

        return $canvas;
    }

    /**
     * Wariant bez logo: tło w kolorze przewodnim i sama nazwa sklepu w kolorze
     * kontrastowym. `brand_ink` jest już policzony pod czytelność, więc nazwa
     * będzie widoczna i na jasnym, i na ciemnym kolorze.
     *
     * @param  \GdImage  $canvas
     * @param  array<string, string>  $tokens
     * @return \GdImage
     */
    private function withoutLogo($canvas, Shop $shop, array $tokens)
    {
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $this->color($canvas, $tokens['brand'] ?? '#d97706'));

        $ink = $tokens['brand_ink'] ?? '#ffffff';
        $this->centeredText($canvas, $shop->name, 76, (int) (self::HEIGHT / 2) + 10, $ink, self::WIDTH - 2 * self::PADDING);

        return $canvas;
    }

    /**
     * Tekst wyśrodkowany w poziomie. Gdy nie mieści się w podanej szerokości,
     * zmniejszamy krój, zamiast pozwolić mu wyjść poza kadr — nazwy sklepów
     * bywają długie.
     *
     * @param  \GdImage  $canvas
     */
    private function centeredText($canvas, string $text, int $size, int $baseline, string $hex, ?int $maxWidth = null): void
    {
        $font = $this->fontPath();
        $maxWidth ??= self::WIDTH - 2 * self::PADDING;

        do {
            $box = imagettfbbox($size, 0, $font, $text);
            $width = abs($box[2] - $box[0]);

            if ($width <= $maxWidth || $size <= 20) {
                break;
            }

            $size -= 4;
        } while (true);

        imagettftext(
            $canvas, $size, 0,
            (int) ((self::WIDTH - $width) / 2), $baseline,
            $this->color($canvas, $hex), $font, $text,
        );
    }

    /**
     * Logo sklepu jako obraz GD (null, gdy sklep go nie ma albo pliku brakuje).
     *
     * @return \GdImage|null
     */
    private function loadLogo(Shop $shop)
    {
        if (blank($shop->logo_path) || ! Storage::disk('public')->exists($shop->logo_path)) {
            return null;
        }

        $image = @imagecreatefromstring((string) Storage::disk('public')->get($shop->logo_path));

        if ($image === false) {
            return null;
        }

        // Logo bywa PNG z przezroczystością — na białym płótnie chcemy je złożyć,
        // nie wyciąć dziury.
        imagealphablending($image, true);

        return $image;
    }

    /**
     * Krój pisma leży w repozytorium (Figtree, licencja OFL — patrz
     * resources/fonts/OFL.txt). ŚWIADOMIE nie sięgamy po czcionki systemowe:
     * na shared hoście są tylko odmiany mono, a i te mogą zniknąć przy migracji.
     */
    private function fontPath(): string
    {
        $path = resource_path('fonts/Figtree-Bold.ttf');

        if (! is_file($path)) {
            throw new RuntimeException('Brak kroju pisma do grafiki OG: '.$path);
        }

        return $path;
    }

    /**
     * Skrót ze składników grafiki — zmiana logo, nazwy lub koloru daje nową
     * nazwę pliku, a więc i nowy adres, którego cache social mediów nie zna.
     */
    private function fingerprint(Shop $shop): string
    {
        $tokens = $shop->themeTokens();

        return substr(md5(implode('|', [
            $shop->name,
            (string) $shop->logo_path,
            $tokens['brand'] ?? '',
            $tokens['brand_ink'] ?? '',
        ])), 0, 12);
    }

    /**
     * Sprzątanie po poprzedniej wersji — katalog sklepu trzyma jedną aktualną
     * grafikę, nie kolekcję sierot po każdej zmianie logo.
     */
    private function forgetPrevious(Shop $shop, string $keep): void
    {
        foreach (Storage::disk('public')->files('og/'.$shop->id) as $file) {
            if ($file !== $keep) {
                Storage::disk('public')->delete($file);
            }
        }
    }

    /**
     * @param  \GdImage  $canvas
     */
    private function color($canvas, string $hex): int
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return (int) imagecolorallocate(
            $canvas,
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );
    }
}
