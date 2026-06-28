<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Zdjęcia produktu: dodawanie (z optymalizacją), ustawianie głównego i usuwanie.
 * Wszystko scope'owane do produktu sklepu zalogowanego sprzedawcy. Limit 5 zdjęć.
 */
class ProductImageController extends Controller
{
    private const MAX_IMAGES = 5;

    public function store(Request $request, Product $product, ProductImageService $images): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $request->validate([
            'images' => ['required', 'array'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ], [
            'images.required' => 'Wybierz przynajmniej jedno zdjęcie.',
            'images.*.image' => 'Każdy plik musi być obrazem (PNG, JPG lub WebP).',
            'images.*.mimes' => 'Dozwolone formaty: PNG, JPG, WebP.',
            'images.*.max' => 'Zdjęcie może mieć maksymalnie 4 MB.',
        ]);

        $files = $request->file('images', []);

        if ($product->images()->count() + count($files) > self::MAX_IMAGES) {
            return back()->with('error', 'Produkt może mieć maksymalnie '.self::MAX_IMAGES.' zdjęć.');
        }

        $position = (int) $product->images()->max('position');
        foreach ($files as $file) {
            $product->images()->create([
                'path' => $images->store($file, $product),
                'position' => ++$position,
            ]);
        }

        return back()->with('success', 'Dodano zdjęcia.');
    }

    public function main(Request $request, Product $product, ProductImage $image): RedirectResponse
    {
        $this->authorizeImage($request, $product, $image);

        $image->update(['position' => (int) $product->images()->min('position') - 1]);

        return back()->with('success', 'Ustawiono zdjęcie główne.');
    }

    public function destroy(Request $request, Product $product, ProductImage $image): RedirectResponse
    {
        $this->authorizeImage($request, $product, $image);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Zdjęcie zostało usunięte.');
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->shop_id === $request->user()->shop?->id, 403);
    }

    private function authorizeImage(Request $request, Product $product, ProductImage $image): void
    {
        $this->authorizeProduct($request, $product);
        abort_unless($image->product_id === $product->id, 404);
    }
}
