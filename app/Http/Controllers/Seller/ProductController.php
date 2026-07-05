<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ProductRequest;
use App\Models\Product;
use App\Services\ProductImageService;
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
            'products' => $shop
                ? $shop->products()->with('images', 'priceHistory')->latest()->paginate(12)->withQueryString()
                : null,
            'total' => $shop ? $shop->products()->count() : 0,
            'max' => $shop ? (int) $shop->entitlement('max_products') : 0,
        ]);
    }

    public function create(Request $request): Renderable|RedirectResponse
    {
        if ($this->limitReached($request)) {
            return redirect()->route('seller.products.index')
                ->with('error', $this->limitMessage($request));
        }

        return view('seller.products.form', [
            'product' => new Product,
            'tagSuggestions' => $this->tagSuggestions($request),
            'defaultVat' => $this->defaultVat($request),
            'defaultSaleUnit' => $this->defaultSaleUnit($request),
            'homepage' => $this->homepageInfo($request),
        ]);
    }

    public function store(ProductRequest $request, ProductImageService $images): RedirectResponse
    {
        if ($this->limitReached($request)) {
            return redirect()->route('seller.products.index')->with('error', $this->limitMessage($request));
        }

        $product = $request->user()->shop->products()->create($this->data($request));
        $this->syncTags($product, $request);

        // Zdjęcia wybrane przy tworzeniu — zapis w kolejności dodania (0,1,2,…).
        foreach ($request->file('images', []) as $position => $file) {
            $product->images()->create([
                'path' => $images->store($file, $product),
                'position' => $position,
            ]);
        }

        return redirect()->route('seller.products.edit', $product)->with('success', 'Produkt został dodany.');
    }

    public function edit(Request $request, Product $product): Renderable
    {
        $this->authorizeProduct($request, $product);

        return view('seller.products.form', [
            'product' => $product,
            'tagSuggestions' => $this->tagSuggestions($request),
            'defaultVat' => $this->defaultVat($request),
            'defaultSaleUnit' => $this->defaultSaleUnit($request),
            'homepage' => $this->homepageInfo($request),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $product->update($this->data($request));
        $this->syncTags($product, $request);

        // Zachowujemy stronę listy, z której przyszedł sprzedawca (link „Wróć do listy").
        $params = ['product' => $product] + ($request->filled('page') ? ['page' => $request->input('page')] : []);

        return redirect()->route('seller.products.edit', $params)->with('success', 'Zapisano zmiany w produkcie.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        if ($product->hasBeenOrdered()) {
            // Zamówiony — zachowujemy dla historii zamówień i dokumentów (soft-delete).
            $product->delete();
        } else {
            // Nigdy niezamówiony — trwałe usunięcie i sprzątanie (rekordy + pliki).
            $product->purge();
        }

        return redirect()->route('seller.products.index')->with('success', 'Produkt został usunięty.');
    }

    /**
     * Dane do zapisu: stan ma znaczenie tylko przy włączonej kontroli stanu.
     *
     * @return array<string, mixed>
     */
    private function data(ProductRequest $request): array
    {
        $data = $request->safe()->except(['tags', 'images']);
        $data['stock'] = $data['track_stock'] ? ($data['stock'] ?? 0) : null;

        // Stan na sztuki to liczba całkowita; na wagę zostaje ułamkiem (2,50 kg).
        if ($data['stock'] !== null && ($data['sale_unit'] ?? 'piece') === \App\Enums\SaleUnit::Piece->value) {
            $data['stock'] = (int) round((float) $data['stock']);
        }

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

        // Edycja mogła odpiąć ostatni produkt od tagu — sprzątamy osierocone.
        $shop->pruneOrphanTags();
    }

    /**
     * Podpowiedzi tagów: własne tagi sklepu mające ≥1 produkt, najczęściej używane
     * na górze (przy remisie alfabetycznie). Osierocone tagi nie wchodzą.
     *
     * @return array<int, string>
     */
    private function tagSuggestions(Request $request): array
    {
        return $request->user()->shop
            ?->tags()
            ->has('products')
            ->withCount('products')
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->pluck('name')
            ->all() ?? [];
    }

    /**
     * Domyślna stawka VAT do prefillu nowego produktu — z ustawień sklepu.
     */
    private function defaultVat(Request $request): string
    {
        return $request->user()->shop?->default_vat_rate?->value ?? '23';
    }

    /**
     * Domyślna jednostka sprzedaży do prefillu nowego produktu — z ustawień sklepu.
     */
    private function defaultSaleUnit(Request $request): string
    {
        return $request->user()->shop?->default_sale_unit?->value ?? 'piece';
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->shop_id === $request->user()->shop?->id, 403);
    }

    /**
     * Zajętość miejsc na stronie głównej (ile wyróżnionych / sufit) — do wskazówki
     * na formularzu produktu. Sufit dla wszystkich pakietów (config/shop.php).
     *
     * @return array{count: int, limit: int}
     */
    private function homepageInfo(Request $request): array
    {
        $shop = $request->user()->shop;

        return [
            'count' => $shop ? $shop->products()->where('show_on_homepage', true)->count() : 0,
            'limit' => (int) config('shop.homepage_promoted_limit'),
        ];
    }

    private function limitReached(Request $request): bool
    {
        $shop = $request->user()->shop;

        return $shop !== null && $shop->products()->count() >= (int) $shop->entitlement('max_products');
    }

    private function limitMessage(Request $request): string
    {
        $shop = $request->user()->shop;

        return 'Pakiet '.$shop->packageName().' pozwala na maksymalnie '.(int) $shop->entitlement('max_products').' produktów.';
    }
}
