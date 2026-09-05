<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Skalowanie i ponowne kodowanie obrazu do WebP.
 *
 * WYDZIELONE ZE `ProductImageService`, bo ta sama obróbka jest potrzebna przy
 * grafikach do graweru. Kopiowanie trzydziestu linii GD skończyłoby się tym, że
 * jedna kopia dostaje poprawkę, a druga nie — i po pół roku sklep miałby dwa
 * różne sposoby przetwarzania obrazów, każdy z własnymi wadami.
 *
 * DLACZEGO ZAWSZE WEBP, niezależnie od formatu wejściowego: mniejsze pliki niż
 * JPEG i PNG przy tej samej jakości, z obsługą przezroczystości. Jeden format
 * na wyjściu znaczy też jeden zestaw problemów do rozwiązania.
 *
 * PONOWNE KODOWANIE USUWA METADANE (EXIF). To nie efekt uboczny, tylko powód:
 * zdjęcie z telefonu niesie współrzędne GPS miejsca, w którym powstało, a te
 * nie mają czego szukać w publicznym katalogu sklepu.
 */
final class ImageOptimizer
{
    /**
     * Zwraca binarną zawartość pliku WebP: dłuższy bok przeskalowany do
     * `$maxSide`, obraz nigdy nie jest powiększany.
     *
     * @throws RuntimeException gdy pliku nie da się odczytać jako obrazu
     */
    public static function toWebp(UploadedFile $file, int $maxSide, int $quality): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Nie udało się odczytać obrazu.');
        }

        $width = imagesx($source);
        $height = imagesy($source);

        // `min(1.0, …)` — mały obrazek zostaje mały. Rozciąganie do „maksymalnego"
        // boku dałoby rozmyty plik cięższy od oryginału.
        $scale = min(1.0, $maxSide / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // Bez tej pary przezroczyste tło grafiki do graweru wyszłoby czarne.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagewebp($canvas, null, $quality);
        $binary = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $binary;
    }
}
