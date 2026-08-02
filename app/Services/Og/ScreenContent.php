<?php

namespace App\Services\Og;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickPixel;

/**
 * Zawartość monitora na grafice promującej sklep — to, co klient zobaczy jako
 * „tak wygląda ten sklep".
 *
 * Układ dobiera się do tego, CO SKLEP MA, a nie do sztywnego szablonu:
 *  - jeden produkt ze zdjęciem → jedno duże zdjęcie na całość. Wygląda jak
 *    karta produktu i jest to najlepszy wariant na start, gdy sprzedawca dodał
 *    dopiero pierwszą rzecz — siatka z jednym kaflem i pustką obok wyglądałaby
 *    jak niedokończona strona;
 *  - dwa lub trzy → rząd;
 *  - cztery i więcej → siatka.
 *
 * Gdy ŻADEN produkt nie ma zdjęcia, ekran dostaje jednolity kolor marki z nazwą
 * sklepu. W ramce monitora czyta się to jak celowa strona tytułowa, a nie jak brak.
 *
 * Nazw ani cen tu nie ma świadomie. Nazwy byłyby w tej skali nieczytelne, a ceny
 * są pułapką: Facebook trzyma pobraną grafikę tygodniami, więc cena z dnia
 * generowania żyłaby własnym życiem długo po tym, jak sprzedawca ją zmieni.
 */
class ScreenContent
{
    /** Rozdzielczość robocza ekranu — z zapasem, bo potem idzie przez perspektywę. */
    public const WIDTH = 1380;

    public const HEIGHT = 880;

    /** Pasek marki u góry: sygnał „to strona sklepu", nie samo zdjęcie. */
    private const BAR_HEIGHT = 90;

    public function __construct(private readonly FontLoader $fonts) {}

    /**
     * @param  array<string, string>  $tokens  paleta motywu sklepu
     */
    public function render(Shop $shop, array $tokens): Imagick
    {
        $products = $this->pickProducts($shop);

        $canvas = new Imagick;
        $canvas->newImage(self::WIDTH, self::HEIGHT, new ImagickPixel('#FFFCF7'));
        $canvas->setImageFormat('png');

        $products->isEmpty()
            ? $this->drawTitleScreen($canvas, $shop, $tokens)
            : $this->drawProducts($canvas, $products);

        $this->drawBar($canvas, $shop, $tokens);

        return $canvas;
    }

    /**
     * Produkty na ekran — WYBÓR STABILNY, i to jest tu najważniejsze.
     *
     * Bierzemy najpierw te, które sprzedawca sam wskazał do pokazania na stronie
     * głównej, a resztę dobieramy od NAJSTARSZYCH. Gdyby brać najnowsze, każdy
     * dodany towar zmieniałby zestaw zdjęć, a więc i skrót w nazwie pliku — czyli
     * adres grafiki. Linki udostępnione wcześniej pokazywałyby wtedy starą wersję,
     * a my przepalalibyśmy pracę przy każdej edycji katalogu.
     *
     * @return Collection<int, Product>
     */
    private function pickProducts(Shop $shop): Collection
    {
        $limit = (int) config('seo.shop_card.max_products', 6);

        return $shop->products()
            ->where('is_active', true)
            ->orderByDesc('show_on_homepage')
            ->orderBy('id')
            ->get()
            ->filter(fn (Product $product) => $product->mainImage() !== null)
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function drawProducts(Imagick $canvas, Collection $products): void
    {
        $top = self::BAR_HEIGHT;
        $areaHeight = self::HEIGHT - $top;
        $count = $products->count();

        // Jeden produkt zajmuje całą wolną powierzchnię; przy dwóch–trzech
        // dzielimy ją na kolumny, wyżej wchodzi siatka dwuwierszowa.
        $columns = match (true) {
            $count === 1 => 1,
            $count <= 3 => $count,
            default => (int) ceil($count / 2),
        };
        $rows = $count <= 3 ? 1 : 2;

        $cellWidth = (int) floor(self::WIDTH / $columns);
        $cellHeight = (int) floor($areaHeight / $rows);

        foreach ($products as $index => $product) {
            $tile = $this->loadTile($product, $cellWidth, $cellHeight);

            if ($tile === null) {
                continue;
            }

            $column = $index % $columns;
            $row = intdiv($index, $columns);

            $canvas->compositeImage(
                $tile,
                Imagick::COMPOSITE_OVER,
                $column * $cellWidth,
                $top + $row * $cellHeight,
            );
            $tile->clear();
        }
    }

    /**
     * Kafel wypełniający komórkę bez zniekształceń: skalujemy „do pokrycia"
     * i docinamy nadmiar — proporcje zdjęć produktów bywają dowolne, a
     * rozciągnięte zdjęcie widać od razu.
     */
    private function loadTile(Product $product, int $width, int $height): ?Imagick
    {
        $image = $product->mainImage();

        if ($image === null || ! Storage::disk('public')->exists($image->path)) {
            return null;
        }

        try {
            $tile = new Imagick;
            $tile->readImageBlob((string) Storage::disk('public')->get($image->path));
            $tile->setImageFormat('png');
            $tile->cropThumbnailImage($width, $height);

            return $tile;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Ekran bez zdjęć: kolor marki i nazwa sklepu w kolorze kontrastowym
     * (`brand_ink` jest już policzony pod czytelność).
     *
     * @param  array<string, string>  $tokens
     */
    private function drawTitleScreen(Imagick $canvas, Shop $shop, array $tokens): void
    {
        // Zamalowujemy PROSTOKĄTEM, a nie kolorem tła: płótno ma już wypełnione
        // piksele, więc samo ustawienie tła zostawiłoby biel i nazwa w kolorze
        // kontrastowym zniknęłaby na jasnym.
        $fill = new ImagickDraw;
        $fill->setFillColor(new ImagickPixel($tokens['brand'] ?? '#B26E79'));
        $fill->rectangle(0, 0, self::WIDTH, self::HEIGHT);
        $canvas->drawImage($fill);
        $fill->clear();

        $draw = new ImagickDraw;
        $draw->setFont($this->fonts->bold());
        $draw->setFillColor(new ImagickPixel($tokens['brand_ink'] ?? '#FFFFFF'));
        $draw->setFontSize(72);
        $draw->setGravity(Imagick::GRAVITY_CENTER);

        $canvas->annotateImage($draw, 0, self::BAR_HEIGHT / 2, 0, $shop->name);
        $draw->clear();
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function drawBar(Imagick $canvas, Shop $shop, array $tokens): void
    {
        $bar = new ImagickDraw;
        $bar->setFillColor(new ImagickPixel($tokens['brand'] ?? '#B26E79'));
        $bar->rectangle(0, 0, self::WIDTH, self::BAR_HEIGHT);
        $canvas->drawImage($bar);
        $bar->clear();

        $text = new ImagickDraw;
        $text->setFont($this->fonts->bold());
        $text->setFillColor(new ImagickPixel($tokens['brand_ink'] ?? '#FFFFFF'));
        $text->setFontSize(38);
        $text->setGravity(Imagick::GRAVITY_NORTHWEST);
        $canvas->annotateImage($text, 40, 22, 0, $shop->name);
        $text->clear();
    }
}
