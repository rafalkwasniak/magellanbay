<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ProductRequest;
use App\Models\Product;
use App\Services\SlugService;
use App\Services\TagNormalizer;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Zarządzanie produktami sklepu (krok podstawowy: bez zdjęć i tagów). Wszystko
 * scope'owane do sklepu zalogowanego sprzedawcy; edycja/usuwanie tylko własnych
 * produktów. Limit liczby produktów wynika z pakietu (config).
 */
class ProductController extends Controller
{
    public function index(Request $request): Renderable
    {
        $shop = $request->user()->shop;

        return view('seller.products.index', [
            'shop' => $shop,
            'products' => $shop ? $shop->products()->with('images')->latest()->get() : collect(),
            'max' => (int) config('shop.packages.free.max_products'),
        ]);
    }

    public function create(Request $request): Renderable|RedirectResponse
    {
        if ($this->limitReached($request)) {
            return redirect()->route('seller.products.index')
                ->with('error', $this->limitMessage());
        }

        return view('seller.products.form', [
            'product' => new Product,
            'tagSuggestions' => $this->tagSuggestions($request),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        if ($this->limitReached($request)) {
            return redirect()->route('seller.products.index')->with('error', $this->limitMessage());
        }

        $product = $request->user()->shop->products()->create($this->data($request));
        $this->syncTags($product, $request);

        return redirect()->route('seller.products.index')->with('success', 'Produkt został dodany.');
    }

    public function edit(Request $request, Product $product): Renderable
    {
        $this->authorizeProduct($request, $product);

        return view('seller.products.form', [
            'product' => $product,
            'tagSuggestions' => $this->tagSuggestions($request),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $product->update($this->data($request));
        $this->syncTags($product, $request);

        return redirect()->route('seller.products.index')->with('success', 'Zapisano zmiany w produkcie.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $product->delete();

        return redirect()->route('seller.products.index')->with('success', 'Produkt został usunięty.');
    }

    /**
     * Dane do zapisu: stan ma znaczenie tylko przy włączonej kontroli stanu.
     *
     * @return array<string, mixed>
     */
    private function data(ProductRequest $request): array
    {
        $data = $request->safe()->except('tags');
        $data['stock'] = $data['track_stock'] ? ($data['stock'] ?? 0) : null;

        return $data;
    }

    /**
     * Parsuje tagi z pola (po przecinku), tworzy brakujące w obrębie sklepu
     * (deduplikacja po slug) i przypina do produktu.
     */
    private function syncTags(Product $product, ProductRequest $request): void
    {
        $shop = $product->shop;

        $ids = collect(app(TagNormalizer::class)->parse($request->input('tags')))
            ->map(fn (string $name): int => $shop->tags()->firstOrCreate(
                ['name' => $name],
                ['slug' => app(SlugService::class)->make($name)],
            )->id)
            ->all();

        $product->tags()->sync($ids);
    }

    /**
     * Istniejące tagi sklepu — podpowiedzi do pola tagów.
     *
     * @return array<int, string>
     */
    private function tagSuggestions(Request $request): array
    {
        return $request->user()->shop?->tags()->orderBy('name')->pluck('name')->all() ?? [];
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->shop_id === $request->user()->shop?->id, 403);
    }

    private function limitReached(Request $request): bool
    {
        $shop = $request->user()->shop;

        return $shop !== null && $shop->products()->count() >= (int) config('shop.packages.free.max_products');
    }

    private function limitMessage(): string
    {
        return 'Pakiet Free pozwala na maksymalnie '.config('shop.packages.free.max_products').' produktów.';
    }
}
