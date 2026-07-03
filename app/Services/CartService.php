<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Koszyk gościa trzymany w sesji (a ta w bazie — SESSION_DRIVER=database).
 * Zasada bezpieczeństwa: w sesji ląduje WYŁĄCZNIE `product_id => ilość`. Nazwę,
 * cenę i stan magazynowy pobieramy świeżo z bazy przy każdym renderze, więc
 * koszyka nie da się „zepsuć" starą ceną ani podmianą wartości w sesji.
 *
 * Koszyk jest kluczowany po `shop_id`: storefront to jeden sklep na subdomenę,
 * a klucz i tak zabezpiecza przed pomieszaniem, gdyby sesja była współdzielona.
 */
class CartService
{
    /** Sesyjny worek wszystkich koszyków: [shop_id => [product_id => qty]]. */
    private const KEY = 'carts';

    /**
     * Surowa zawartość koszyka sklepu: [product_id => qty]. Kolejność wstawiania
     * zachowana (PHP array).
     *
     * @return array<int, int>
     */
    public function raw(int $shopId): array
    {
        return session()->get(self::KEY.'.'.$shopId, []);
    }

    /**
     * Łączna liczba sztuk (do licznika w nagłówku). Liczona z samej sesji, bez
     * zapytań — badge może chwilowo policzyć produkt zdjęty przez sprzedawcę;
     * pełne uzgodnienie robi strona koszyka (lines()).
     */
    public function count(int $shopId): int
    {
        return array_sum($this->raw($shopId));
    }

    /**
     * Dodaje produkt do koszyka (zwiększa ilość o $qty). Ignoruje produkt spoza
     * sklepu lub nieaktywny. Ilość przycinana do stanu magazynowego, gdy śledzony.
     */
    public function add(Product $product, int $qty = 1): void
    {
        if ($product->shop_id === null || ! $product->is_active || $qty < 1) {
            return;
        }

        $current = $this->raw($product->shop_id);
        $current[$product->id] = ($current[$product->id] ?? 0) + $qty;

        $this->store($product->shop_id, $product->id, $current[$product->id], $product);
    }

    /**
     * Ustawia dokładną ilość produktu. 0 (lub mniej) usuwa pozycję.
     */
    public function setQuantity(int $shopId, int $productId, int $qty): void
    {
        if ($qty < 1) {
            $this->remove($shopId, $productId);

            return;
        }

        $product = Product::where('shop_id', $shopId)->where('is_active', true)->find($productId);

        if ($product === null) {
            $this->remove($shopId, $productId);

            return;
        }

        $cart = $this->raw($shopId);
        $cart[$productId] = $this->capToStock($product, $qty);
        session()->put(self::KEY.'.'.$shopId, $cart);
    }

    public function remove(int $shopId, int $productId): void
    {
        $cart = $this->raw($shopId);
        unset($cart[$productId]);
        session()->put(self::KEY.'.'.$shopId, $cart);
    }

    public function clear(int $shopId): void
    {
        session()->forget(self::KEY.'.'.$shopId);
    }

    /**
     * Uzgodnione pozycje koszyka: świeże produkty z bazy, ilości przycięte do
     * stanu, pominięte produkty nieistniejące/nieaktywne (i wyczyszczone z sesji).
     * Każda pozycja: product, quantity, unit_price (brutto), line_total.
     *
     * @return Collection<int, array{product: Product, quantity: int, unit_price: float, line_total: float}>
     */
    public function lines(int $shopId): Collection
    {
        $raw = $this->raw($shopId);

        if ($raw === []) {
            return collect();
        }

        $products = Product::where('shop_id', $shopId)
            ->where('is_active', true)
            ->whereIn('id', array_keys($raw))
            ->get()
            ->keyBy('id');

        $reconciled = [];

        $lines = collect(array_keys($raw))
            ->map(function (int $productId) use ($raw, $products, &$reconciled) {
                $product = $products->get($productId);

                if ($product === null) {
                    return null;    // zdjęty/usunięty — wypadnie z koszyka
                }

                $qty = $this->capToStock($product, $raw[$productId]);

                if ($qty < 1) {
                    return null;    // wyprzedany — wypada
                }

                $reconciled[$productId] = $qty;
                $unit = (float) $product->price_gross;

                return [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => $unit * $qty,
                ];
            })
            ->filter()
            ->values();

        // Zapisujemy uzgodnioną wersję z powrotem, żeby licznik i sesja nie
        // trzymały pozycji, których już nie ma.
        if ($reconciled !== $raw) {
            session()->put(self::KEY.'.'.$shopId, $reconciled);
        }

        return $lines;
    }

    public function total(int $shopId): float
    {
        return $this->lines($shopId)->sum('line_total');
    }

    /**
     * Zapis pojedynczej pozycji z przycięciem do stanu.
     */
    private function store(int $shopId, int $productId, int $qty, Product $product): void
    {
        $cart = $this->raw($shopId);
        $cart[$productId] = $this->capToStock($product, $qty);
        session()->put(self::KEY.'.'.$shopId, $cart);
    }

    /**
     * Ilość ograniczona stanem magazynowym, gdy produkt go śledzi. Bez śledzenia
     * (usługa/nielimitowany) ilość bez ograniczeń.
     */
    private function capToStock(Product $product, int $qty): int
    {
        if ($product->track_stock && $product->stock !== null) {
            return max(0, min($qty, $product->stock));
        }

        return max(0, $qty);
    }
}
