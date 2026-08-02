<?php

namespace App\Services\Og;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Przygotowuje scenę pod grafikę promującą sklep: render biurka z monitorem,
 * w którym miejsce ekranu jest wypełnione zielenią (chroma key).
 *
 * Robi dwie rzeczy, obie RAZ na wersję renderu, bo są kosztowne:
 *  1. wycina zieleń, zostawiając w jej miejscu prawdziwą DZIURĘ — dzięki temu
 *     przy składaniu podkładamy zawartość ekranu pod spód, a ramka monitora,
 *     odblaski i cienie zostają nietknięte na wierzchu;
 *  2. wyznacza CZTERY NAROŻNIKI ekranu — monitor stoi pod kątem, więc ekran
 *     jest trapezem i zawartość trzeba w niego wpasować perspektywicznie.
 *
 * Wynik leży w `storage/app/og-scene/` i jest odtwarzany automatycznie, gdy
 * plik źródłowy się zmieni (porównujemy skrót zawartości). Dzięki temu podmiana
 * renderu przez grafika nie wymaga pamiętania o żadnej komendzie.
 *
 * DLACZEGO wypełnienie obszarem, a nie filtr po kolorze: zieleń liści rośliny
 * stojącej na biurku jest niemal identyczna z zielenią ekranu (sprawdzone:
 * 121,168,88 kontra 95,175,84). Filtr kolorystyczny zjadłby liście razem z
 * ekranem. Wypełnienie startuje ze środka ekranu i rozlewa się tylko po
 * spójnym obszarze, więc rośliny nie tyka.
 */
class SceneCutout
{
    /** Punkt startowy wypełnienia — środek ekranu w renderze. */
    private const SEED_RATIO_X = 0.68;

    private const SEED_RATIO_Y = 0.47;

    /** Dopuszczalne odchylenie od koloru startowego (na kanał). */
    private const TOLERANCE = 55;

    /**
     * O ile pikseli poszerzamy dziurę wzdłuż krawędzi. Antyaliasing zostawia
     * na obwodzie piksele wpół zielone — bez tego wokół ekranu widać zielony
     * rant. Poszerzamy WYŁĄCZNIE piksele o zielonkawym odcieniu, żeby nie
     * nadgryźć czarnej ramki monitora.
     */
    private const FRINGE = 3;

    private const DIR = 'og-scene';

    /**
     * Ścieżka do renderu z wyciętym ekranem oraz narożniki ekranu.
     *
     * @return array{path: string, corners: array<int, array{int, int}>}
     */
    public function prepare(): array
    {
        $source = public_path(config('seo.shop_card.scene'));

        if (! is_file($source)) {
            throw new RuntimeException('Brak sceny do grafiki sklepu: '.$source);
        }

        $stamp = substr(md5_file($source) ?: '', 0, 12);
        $imagePath = self::DIR.'/'.$stamp.'.png';
        $metaPath = self::DIR.'/'.$stamp.'.json';

        if (Storage::exists($imagePath) && Storage::exists($metaPath)) {
            /** @var array{corners: array<int, array{int, int}>} $meta */
            $meta = json_decode((string) Storage::get($metaPath), true);

            return ['path' => Storage::path($imagePath), 'corners' => $meta['corners']];
        }

        $result = $this->cut($source);

        Storage::put($imagePath, $result['png']);
        Storage::put($metaPath, json_encode(['corners' => $result['corners']]));
        $this->forgetOlder($stamp);

        return ['path' => Storage::path($imagePath), 'corners' => $result['corners']];
    }

    /**
     * Wycina zielony ekran i zwraca gotowy PNG wraz z narożnikami.
     *
     * @return array{png: string, corners: array<int, array{int, int}>}
     */
    private function cut(string $source): array
    {
        $image = @imagecreatefrompng($source);

        if ($image === false) {
            throw new RuntimeException('Nie udało się wczytać sceny: '.$source);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $width = imagesx($image);
        $height = imagesy($image);

        $seedX = (int) round($width * self::SEED_RATIO_X);
        $seedY = (int) round($height * self::SEED_RATIO_Y);

        $seed = imagecolorat($image, $seedX, $seedY);
        $seedR = ($seed >> 16) & 0xFF;
        $seedG = ($seed >> 8) & 0xFF;
        $seedB = $seed & 0xFF;

        if (! ($seedG > $seedR && $seedG > $seedB)) {
            throw new RuntimeException('Punkt startowy sceny nie trafił w zielony ekran — sprawdź render.');
        }

        $filled = $this->floodFill($image, $width, $height, $seedX, $seedY, $seedR, $seedG, $seedB);
        $corners = $this->corners($filled, $width);

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);

        foreach ($this->withFringe($image, $filled, $width, $height) as $key => $ignored) {
            imagesetpixel($image, $key % $width, intdiv($key, $width), $transparent);
        }

        ob_start();
        imagepng($image, null, 6);
        $png = (string) ob_get_clean();

        return ['png' => $png, 'corners' => $corners];
    }

    /**
     * Wypełnienie obszarem od punktu startowego. Zwraca klucze pikseli
     * (`y * szerokość + x`) należących do ekranu.
     *
     * @param  \GdImage  $image
     * @return array<int, true>
     */
    private function floodFill($image, int $width, int $height, int $seedX, int $seedY, int $r, int $g, int $b): array
    {
        $inside = [];
        $stack = [[$seedX, $seedY]];

        while ($stack !== []) {
            [$x, $y] = array_pop($stack);

            if ($x < 0 || $y < 0 || $x >= $width || $y >= $height) {
                continue;
            }

            $key = $y * $width + $x;

            if (isset($inside[$key])) {
                continue;
            }

            $color = imagecolorat($image, $x, $y);

            if ((($color >> 24) & 0x7F) > 60) {
                continue;
            }

            if (abs((($color >> 16) & 0xFF) - $r) > self::TOLERANCE
                || abs((($color >> 8) & 0xFF) - $g) > self::TOLERANCE
                || abs(($color & 0xFF) - $b) > self::TOLERANCE) {
                continue;
            }

            $inside[$key] = true;

            $stack[] = [$x + 1, $y];
            $stack[] = [$x - 1, $y];
            $stack[] = [$x, $y + 1];
            $stack[] = [$x, $y - 1];
        }

        if (count($inside) < 10000) {
            throw new RuntimeException('Wykryty ekran jest podejrzanie mały — sprawdź render sceny.');
        }

        return $inside;
    }

    /**
     * Cztery narożniki obszaru. Dla wypukłego czworokąta wystarczą punkty
     * skrajne sumy i różnicy współrzędnych — nie trzeba liczyć otoczki wypukłej.
     *
     * @param  array<int, true>  $inside
     * @return array<int, array{int, int}>  [lewy-górny, prawy-górny, prawy-dolny, lewy-dolny]
     */
    private function corners(array $inside, int $width): array
    {
        $minSum = PHP_INT_MAX;
        $maxSum = PHP_INT_MIN;
        $minDiff = PHP_INT_MAX;
        $maxDiff = PHP_INT_MIN;
        $leftTop = $rightTop = $rightBottom = $leftBottom = [0, 0];

        foreach (array_keys($inside) as $key) {
            $y = intdiv($key, $width);
            $x = $key % $width;
            $sum = $x + $y;
            $diff = $x - $y;

            if ($sum < $minSum) {
                $minSum = $sum;
                $leftTop = [$x, $y];
            }
            if ($sum > $maxSum) {
                $maxSum = $sum;
                $rightBottom = [$x, $y];
            }
            if ($diff > $maxDiff) {
                $maxDiff = $diff;
                $rightTop = [$x, $y];
            }
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $leftBottom = [$x, $y];
            }
        }

        return [$leftTop, $rightTop, $rightBottom, $leftBottom];
    }

    /**
     * Piksele do wyczyszczenia: wnętrze ekranu plus zielonkawa obwódka po
     * antyaliasingu.
     *
     * @param  \GdImage  $image
     * @param  array<int, true>  $inside
     * @return array<int, true>
     */
    private function withFringe($image, array $inside, int $width, int $height): array
    {
        $all = $inside;

        foreach (array_keys($inside) as $key) {
            $y = intdiv($key, $width);
            $x = $key % $width;

            for ($dy = -self::FRINGE; $dy <= self::FRINGE; $dy++) {
                for ($dx = -self::FRINGE; $dx <= self::FRINGE; $dx++) {
                    $nx = $x + $dx;
                    $ny = $y + $dy;

                    if ($nx < 0 || $ny < 0 || $nx >= $width || $ny >= $height) {
                        continue;
                    }

                    $nk = $ny * $width + $nx;

                    if (isset($all[$nk])) {
                        continue;
                    }

                    $color = imagecolorat($image, $nx, $ny);
                    $r = ($color >> 16) & 0xFF;
                    $g = ($color >> 8) & 0xFF;
                    $b = $color & 0xFF;

                    if ($g > $r + 8 && $g > $b + 8) {
                        $all[$nk] = true;
                    }
                }
            }
        }

        return $all;
    }

    /**
     * Scena zmienia się rzadko, ale gdy się zmieni, stara wersja jest już tylko
     * śmieciem — katalog trzyma jedną aktualną.
     */
    private function forgetOlder(string $keep): void
    {
        foreach (Storage::files(self::DIR) as $file) {
            if (! str_contains($file, $keep)) {
                Storage::delete($file);
            }
        }
    }
}
