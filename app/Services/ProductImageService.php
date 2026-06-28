<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Optymalizacja i zapis zdjęć produktu. Skaluje dłuższy bok do maks. 1600 px i
 * ponownie koduje przez GD — co usuwa metadane (EXIF). Oryginały nie są
 * przechowywane. Zapis na dysku `public` w katalogu produktu.
 */
class ProductImageService
{
    private const MAX_SIDE = 1600;

    /**
     * Zapisuje zoptymalizowane zdjęcie i zwraca jego ścieżkę na dysku `public`.
     */
    public function store(UploadedFile $file, Product $product): string
    {
        [$binary, $ext] = $this->optimize($file);
        $path = 'products/'.$product->id.'/'.Str::uuid()->toString().'.'.$ext;

        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /**
     * @return array{0:string, 1:string} zoptymalizowany obraz (binarnie) + rozszerzenie
     */
    private function optimize(UploadedFile $file): array
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('Nie udało się odczytać obrazu.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, self::MAX_SIDE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $mime = (string) $file->getMimeType();

        ob_start();
        if (str_contains($mime, 'png')) {
            imagepng($canvas, null, 6);
            $ext = 'png';
        } elseif (str_contains($mime, 'webp')) {
            imagewebp($canvas, null, 82);
            $ext = 'webp';
        } else {
            imagejpeg($canvas, null, 82);
            $ext = 'jpg';
        }
        $binary = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return [$binary, $ext];
    }
}
