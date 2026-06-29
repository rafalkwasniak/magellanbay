<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Zdjęcia produktu: dodawanie (z optymalizacją), ustawianie głównego i usuwanie.
 * Wszystko scope'owane do produktu sklepu zalogowanego sprzedawcy. Limit 8 zdjęć.
 */
class ProductImageController extends Controller
{
    private const MAX_IMAGES = 8;

    public function store(Request $request, Product $product, ProductImageService $images): JsonResponse
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
            return response()->json(['message' => 'Produkt może mieć maksymalnie '.self::MAX_IMAGES.' zdjęć.'], 422);
        }

        $position = (int) $product->images()->max('position');
        $created = collect($files)->map(function ($file) use ($product, $images, &$position) {
            $image = $product->images()->create([
                'path' => $images->store($file, $product),
                'position' => ++$position,
            ]);

            return [
                'id' => $image->id,
                'url' => $image->url(),
                'deleteUrl' => route('seller.products.images.destroy', [$product, $image]),
            ];
        });

        return response()->json(['images' => $created->all()]);
    }

    public function reorder(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $ownIds = $product->images()->pluck('id')->all();
        $position = 0;
        foreach ($validated['order'] as $id) {
            if (in_array((int) $id, $ownIds, true)) {
                ProductImage::where('id', $id)->where('product_id', $product->id)->update(['position' => $position++]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Product $product, ProductImage $image): JsonResponse
    {
        $this->authorizeImage($request, $product, $image);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['ok' => true]);
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
