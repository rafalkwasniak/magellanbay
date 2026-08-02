<?php

namespace App\Services;

use App\Models\Shop;
use App\Services\Og\FontLoader;
use App\Services\Og\SceneCutout;
use App\Services\Og\ScreenContent;
use App\Support\Seo;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Throwable;

/**
 * Składa grafikę sklepu do social mediów (1200×630) — to, co widzi klient, gdy
 * sprzedawca wklei link do sklepu na Facebooka czy Messengera.
 *
 * Karta ma dwie strony. Po PRAWEJ scena: biurko z monitorem, w którym pokazujemy
 * zdjęcia produktów tego sklepu. Po LEWEJ tożsamość: logo albo nazwa, jedno zdanie
 * o sklepie, zachęta do zakupu i adres. Pod wszystkim gradient wyliczony z palety
 * motywu, więc karta jest zapowiedzią tego, co klient zobaczy po kliknięciu.
 *
 * DLACZEGO tak, a nie samo logo (poprzednia wersja): logo na białym tle nie mówi,
 * CO sklep sprzedaje. Karta ma zatrzymać kciuk przewijający kanał, a nie tylko
 * poprawnie się wyświetlić.
 *
 * ZERO AI w czasie działania. Scena to jeden gotowy render z przezroczystym tłem,
 * a cała zmienność — kolory, zdjęcia, napisy — powstaje tutaj, deterministycznie.
 *
 * Czego na karcie NIE MA, świadomie: cen, stanów magazynowych i obietnic zależnych
 * od ustawień sklepu (dostawa, płatności online). Facebook trzyma pobraną grafikę
 * tygodniami, więc każda taka informacja zdążyłaby się zdezaktualizować. „Zamów bez
 * rejestracji" jest bezpieczne — kasa nie wymaga konta w ŻADNYM sklepie i nie ma
 * ustawienia, które by to zmieniło.
 */
class OgImageGenerator
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** Lewy margines kolumny z tekstem. */
    private const PAD = 64;

    /** Wysokość sceny na płótnie. Reszta to oddech nad biurkiem. */
    private const SCENE_HEIGHT = 560;

    /**
     * Szerokość, w której musi zmieścić się tekst lewej kolumny. Scena wchodzi
     * w kadr od prawej i na wysokości napisów zaczyna się około 720 piksela —
     * ten limit trzyma tekst z dala od monitora.
     */
    private const TEXT_WIDTH = 600;

    public function __construct(
        private readonly SceneCutout $scene,
        private readonly ScreenContent $screen,
        private readonly FontLoader $fonts,
    ) {}

    /**
     * Generuje grafikę i zapisuje ją na dysku publicznym. Zwraca ścieżkę pliku.
     *
     * JPG, nie PNG: ta sama karta w PNG waży kilkanaście razy więcej, a pobiera
     * ją robot przy każdym pierwszym udostępnieniu linku. Przy zdjęciach różnicy
     * w jakości nie widać.
     */
    /**
     * @param  bool  $force  przerysuj, nawet jeśli plik o tej nazwie już jest —
     *                       potrzebne po zmianie samego generatora, bo skrót
     *                       opisuje DANE sklepu, nie wersję kodu.
     */
    public function generate(Shop $shop, bool $force = false): string
    {
        $path = 'og/'.$shop->id.'/'.$this->fingerprint($shop).'.jpg';

        // Karta o tej nazwie już istnieje, czyli nic, co na niej widać, się nie
        // zmieniło. Dzięki temu obserwatory mogą zlecać odświeżenie przy każdej
        // edycji produktu czy ustawień — składanie w Imagicku (blisko sekundy)
        // wykona się dopiero wtedy, gdy naprawdę ma być inaczej.
        if (! $force && Storage::disk('public')->exists($path)) {
            return $path;
        }

        $card = $this->compose($shop);
        $card->setImageFormat('jpeg');
        $card->setImageCompressionQuality(88);
        // Bez podpróbkowania chrominancji: napisy w kolorze marki na jasnym tle
        // to najgorszy przypadek dla domyślnego 4:2:0 — na krawędziach liter
        // robią się brudne smugi.
        $card->setSamplingFactors(['1x1', '1x1', '1x1']);
        $card->setInterlaceScheme(Imagick::INTERLACE_PLANE);
        $card->stripImage();

        Storage::disk('public')->put($path, $card->getImageBlob());
        $card->clear();

        $this->forgetPrevious($shop, $path);

        return $path;
    }

    private function compose(Shop $shop): Imagick
    {
        $tokens = $shop->themeTokens();

        $canvas = $this->background($tokens);
        $this->placeScene($canvas, $shop, $tokens);
        $this->placeIdentity($canvas, $shop, $tokens);

        return $canvas;
    }

    /**
     * Tło: pionowy gradient od koloru powierzchni motywu do tej samej powierzchni
     * podbarwionej kolorem marki. Karta jest dzięki temu rozpoznawalnie „w kolorach
     * sklepu", a jednocześnie nie krzyczy — sam kolor marki zostaje mocnym akcentem
     * dla przycisku i adresu.
     *
     * @param  array<string, string>  $tokens
     */
    private function background(array $tokens): Imagick
    {
        $surface = $tokens['surface'] ?? '#FBF5F6';
        $brand = $tokens['brand'] ?? '#B26E79';

        $canvas = new Imagick;
        $canvas->newPseudoImage(
            self::WIDTH,
            self::HEIGHT,
            'gradient:'.$surface.'-'.$this->mix($surface, $brand, 0.20),
        );
        $canvas->setImageFormat('png');

        return $canvas;
    }

    /**
     * Scena z podłożoną zawartością ekranu, dosunięta do prawej krawędzi.
     *
     * Kolejność jest tu istotna: zawartość ekranu wchodzi POD scenę, w wyciętą
     * dziurę po zieleni. Gdyby ją nałożyć na wierzch, zakryłaby ramkę monitora
     * i odblaski, a karta natychmiast wyglądałaby na sklejaną.
     *
     * @param  array<string, string>  $tokens
     */
    private function placeScene(Imagick $canvas, Shop $shop, array $tokens): void
    {
        $prepared = $this->scene->prepare();

        $frame = new Imagick($prepared['path']);
        $frame->setImageFormat('png');

        $screen = $this->screen->render($shop, $tokens);
        $this->warpToScreen($screen, $prepared['corners'], $frame->getImageWidth(), $frame->getImageHeight());

        $screen->compositeImage($frame, Imagick::COMPOSITE_OVER, 0, 0);
        $frame->clear();

        $screen->trimImage(0);
        $screen->setImagePage(0, 0, 0, 0);

        $scale = self::SCENE_HEIGHT / $screen->getImageHeight();
        $screen->resizeImage(
            (int) round($screen->getImageWidth() * $scale),
            self::SCENE_HEIGHT,
            Imagick::FILTER_LANCZOS,
            1,
        );

        $canvas->compositeImage(
            $screen,
            Imagick::COMPOSITE_OVER,
            self::WIDTH - $screen->getImageWidth() - 20,
            self::HEIGHT - $screen->getImageHeight(),
        );
        $screen->clear();
    }

    /**
     * Wpasowuje prostokątną zawartość w czworokąt ekranu. Monitor stoi pod kątem,
     * więc samo skalowanie by nie wystarczyło — potrzebne jest przekształcenie
     * perspektywiczne, którego GD nie potrafi (stąd Imagick w tym miejscu).
     *
     * @param  array<int, array{int, int}>  $corners
     */
    private function warpToScreen(Imagick $screen, array $corners, int $frameWidth, int $frameHeight): void
    {
        $width = $screen->getImageWidth();
        $height = $screen->getImageHeight();

        // Płótno musi mieć rozmiar docelowy PRZED przekształceniem, inaczej wynik
        // zostaje przycięty do rozmiaru źródła.
        $screen->setImageBackgroundColor(new ImagickPixel('transparent'));
        $screen->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $screen->extentImage($frameWidth, $frameHeight, 0, 0);
        $screen->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_TRANSPARENT);

        [$leftTop, $rightTop, $rightBottom, $leftBottom] = $corners;

        $screen->distortImage(Imagick::DISTORTION_PERSPECTIVE, [
            0, 0, $leftTop[0], $leftTop[1],
            $width, 0, $rightTop[0], $rightTop[1],
            $width, $height, $rightBottom[0], $rightBottom[1],
            0, $height, $leftBottom[0], $leftBottom[1],
        ], false);
    }

    /**
     * Lewa kolumna: znak, zdanie, zachęta, przycisk i adres.
     *
     * @param  array<string, string>  $tokens
     */
    private function placeIdentity(Imagick $canvas, Shop $shop, array $tokens): void
    {
        $ink = $tokens['ink'] ?? '#3B2C30';
        $brand = $tokens['brand'] ?? '#B26E79';
        $surface = $tokens['surface'] ?? '#FBF5F6';
        $muted = $this->mix($ink, $surface, 0.35);

        $logo = $this->loadLogo($shop);

        if ($logo !== null) {
            // Jest logo — nazwy nie powtarzamy, bo logo prawie zawsze samo ją niesie.
            $canvas->compositeImage($logo, Imagick::COMPOSITE_OVER, self::PAD, 86);
            $logo->clear();
        } else {
            $this->drawName($canvas, $shop->name, $ink);
        }

        if ($tagline = Seo::shopTagline($shop)) {
            // Łamiemy na dwie linie i pilnujemy szerokości: na tej wysokości
            // scena zaczyna się już około 720 piksela, więc dłuższe zdanie
            // wchodziłoby na monitor.
            $baseline = 214;

            foreach ($this->wrap($tagline, 24, $this->fonts->semiBold(), self::TEXT_WIDTH, 2) as $line) {
                $this->text($canvas, $line, self::PAD, $baseline, 24, $muted, $this->fonts->semiBold());
                $baseline += 32;
            }
        }

        $this->text($canvas, 'Wybierz produkt, dodaj do koszyka', self::PAD, 292, 27, $ink, $this->fonts->semiBold());
        $this->text($canvas, 'i zamów bez rejestracji w 5 minut.', self::PAD, 330, 27, $ink, $this->fonts->semiBold());

        $this->drawButton($canvas, $tokens);

        $this->text($canvas, 'Znajdziesz nas pod adresem', self::PAD, 506, 20, $muted, $this->fonts->semiBold());
        $this->text($canvas, $shop->host(), self::PAD, 536, 22, $brand, $this->fonts->bold());
    }

    /**
     * Nazwa sklepu jako znak — łamana najwyżej na dwie linie, bo niżej zaczyna
     * się scena i tekst wszedłby na biurko.
     */
    private function drawName(Imagick $canvas, string $name, string $ink): void
    {
        $lines = $this->wrap($name, 46, $this->fonts->bold(), self::TEXT_WIDTH, 2);
        $baseline = count($lines) === 1 ? 150 : 112;

        foreach ($lines as $line) {
            $this->text($canvas, $line, self::PAD, $baseline, 46, $ink, $this->fonts->bold());
            $baseline += 52;
        }
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function drawButton(Imagick $canvas, array $tokens): void
    {
        $shape = new ImagickDraw;
        $shape->setFillColor(new ImagickPixel($tokens['brand'] ?? '#B26E79'));
        $shape->roundRectangle(self::PAD, 392, self::PAD + 258, 448, 28, 28);
        $canvas->drawImage($shape);
        $shape->clear();

        $this->text($canvas, 'Zobacz ofertę', self::PAD + 36, 428, 21, $tokens['brand_ink'] ?? '#FFFFFF', $this->fonts->bold());
    }

    private function text(Imagick $canvas, string $content, int $x, int $baseline, int $size, string $color, string $font): void
    {
        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($size);
        $draw->setFillColor(new ImagickPixel($color));
        $canvas->annotateImage($draw, $x, $baseline, 0, $content);
        $draw->clear();
    }

    /**
     * Łamanie po pełnych słowach do zadanej szerokości.
     *
     * @return array<int, string>
     */
    private function wrap(string $text, int $size, string $font, int $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($this->textWidth($candidate, $size, $font) <= $maxWidth) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }

            $current = $word;

            if (count($lines) >= $maxLines) {
                $current = '';
                break;
            }
        }

        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }

        return $lines === [] ? [$text] : $lines;
    }

    private function textWidth(string $text, int $size, string $font): float
    {
        $probe = new Imagick;
        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($size);
        $metrics = $probe->queryFontMetrics($draw, $text);
        $draw->clear();
        $probe->clear();

        return (float) $metrics['textWidth'];
    }

    /**
     * Logo sklepu przeskalowane do wysokości znaku (null, gdy sklep go nie ma).
     * Skalujemy też W GÓRĘ, ale z sufitem — sprzedawcy wgrywają czasem pliki
     * 200×200, a takie logo ginęłoby na karcie; powyżej progu robi się papka.
     */
    private function loadLogo(Shop $shop): ?Imagick
    {
        if (blank($shop->logo_path) || ! Storage::disk('public')->exists($shop->logo_path)) {
            return null;
        }

        try {
            $logo = new Imagick;
            $logo->readImageBlob((string) Storage::disk('public')->get($shop->logo_path));
            $logo->setImageFormat('png');

            $scale = min(96 / $logo->getImageHeight(), 2.4);
            $logo->resizeImage(
                (int) round($logo->getImageWidth() * $scale),
                (int) round($logo->getImageHeight() * $scale),
                Imagick::FILTER_LANCZOS,
                1,
            );

            return $logo;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Mieszanie dwóch kolorów w podanej proporcji — do gradientu tła i do
     * przygaszonego wariantu koloru tekstu.
     */
    private function mix(string $base, string $with, float $ratio): string
    {
        [$r1, $g1, $b1] = $this->rgb($base);
        [$r2, $g2, $b2] = $this->rgb($with);

        return sprintf(
            '#%02X%02X%02X',
            (int) round($r1 * (1 - $ratio) + $r2 * $ratio),
            (int) round($g1 * (1 - $ratio) + $g2 * $ratio),
            (int) round($b1 * (1 - $ratio) + $b2 * $ratio),
        );
    }

    /**
     * @return array{int, int, int}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Skrót ze składników grafiki — zmiana daje nową nazwę pliku, a więc i nowy
     * adres, którego cache social mediów nie zna.
     *
     * Z katalogu bierzemy ZDJĘCIA, KTÓRE FAKTYCZNIE TRAFIĄ NA EKRAN — ich ścieżki,
     * w kolejności wyświetlania. To jedyna miara, która nie kłamie w żadną stronę:
     *
     *  - podmiana zdjęcia na ładniejsze zmienia ścieżkę, więc karta się przerysuje
     *    (bez tego sprzedawca zostałby ze starym zdjęciem i nie miałby jak tego
     *    naprawić, bo ręcznego odświeżania świadomie nie ma);
     *  - dodanie towaru do sklepu, który ma już komplet kafli, nie zmienia niczego,
     *    bo wybór jest stabilny (wyróżnione, potem najstarsze) — adres wklejony
     *    wcześniej na Facebooka zostaje ważny.
     */
    private function fingerprint(Shop $shop): string
    {
        $tokens = $shop->themeTokens();

        $photos = $this->screen->photoPaths($shop);

        return substr(md5(implode('|', [
            $shop->name,
            (string) $shop->logo_path,
            (string) Seo::shopTagline($shop),
            $tokens['brand'] ?? '',
            $tokens['brand_ink'] ?? '',
            $tokens['surface'] ?? '',
            $tokens['ink'] ?? '',
            implode(',', $photos),
            (string) md5_file(public_path(config('seo.shop_card.scene'))),
        ])), 0, 12);
    }

    /**
     * Sprzątanie po poprzedniej wersji — katalog sklepu trzyma jedną aktualną
     * grafikę, nie kolekcję sierot po każdej zmianie.
     */
    private function forgetPrevious(Shop $shop, string $keep): void
    {
        foreach (Storage::disk('public')->files('og/'.$shop->id) as $file) {
            if ($file !== $keep) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
