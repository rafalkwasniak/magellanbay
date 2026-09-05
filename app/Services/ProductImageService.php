<?php

namespace App\Services;

use App\Models\Product;
use App\Support\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Optymalizacja i zapis zdjęć produktu. Skaluje dłuższy bok do `shop.product_images.max_side`
 * px i ponownie koduje przez GD jako WebP — niezależnie od formatu wejściowego (jpg/png/webp).
 * WebP daje mniejsze pliki niż JPEG/PNG przy tej samej jakości i obsługuje przezroczystość, więc
 * trzymamy jeden format. Ponowne kodowanie usuwa metadane (EXIF). Oryginały nie są przechowywane.
 * Zapis na dysku `public` w katalogu produktu.
 */
class ProductImageService
{
    /**
     * Zapisuje zoptymalizowane zdjęcie (WebP) i zwraca jego ścieżkę na dysku `public`.
     */
    public function store(UploadedFile $file, Product $product): string
    {
        $binary = $this->optimize($file);
        $path = 'products/'.$product->id.'/'.Str::uuid()->toString().'.webp';

        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /**
     * Skaluje i koduje obraz do WebP.
     *
     * Sama obróbka mieszka w `App\Support\ImageOptimizer` — używa jej także
     * biblioteka grafik do graweru. Tutaj zostają tylko PARAMETRY właściwe
     * zdjęciom produktu.
     */
    private function optimize(UploadedFile $file): string
    {
        return ImageOptimizer::toWebp(
            $file,
            (int) config('shop.product_images.max_side', 1600),
            (int) config('shop.product_images.quality', 82),
        );
    }
}
