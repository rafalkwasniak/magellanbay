<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\CartService;
use Livewire\Component;

/**
 * Strona koszyka: lista pozycji, zmiana ilości (+/−), wpisywanie z palca,
 * usuwanie i suma. Stan trzyma CartService (sesja); komponent tylko go
 * modyfikuje i re-renderuje. Krok +/− zależy od jednostki produktu (1 szt.
 * albo 0,5 kg). Każda zmiana rozgłasza `cart-updated`, by licznik nadążał.
 */
class Cart extends Component
{
    public int $shopId;

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;
    }

    public function increment(int $productId): void
    {
        $product = $this->product($productId);

        if ($product === null) {
            return;
        }

        $cart = app(CartService::class);
        $current = (float) ($cart->raw($this->shopId)[$productId] ?? 0);
        $cart->setQuantity($this->shopId, $productId, $current + $product->sale_unit->step());
        $this->dispatch('cart-updated');
    }

    public function decrement(int $productId): void
    {
        $product = $this->product($productId);

        if ($product === null) {
            return;
        }

        $cart = app(CartService::class);
        $current = (float) ($cart->raw($this->shopId)[$productId] ?? 0);
        $step = $product->sale_unit->step();

        // „−" schodzi tylko do minimum (1 szt. / 0,5 kg) — poniżej jest KOSZ,
        // żeby dwuklik nie skasował pozycji przez przypadek.
        if ($current - $step >= $product->sale_unit->minQuantity()) {
            $cart->setQuantity($this->shopId, $productId, $current - $step);
            $this->dispatch('cart-updated');
        }
    }

    /**
     * Ilość wpisana z palca (pole w koszyku). Parsujemy polski zapis (przecinek,
     * spacje); CartService normalizuje wg jednostki, przycina do stanu i usuwa
     * pozycję, gdy zejdzie poniżej minimum.
     */
    public function updateQuantity(int $productId, string $value): void
    {
        $qty = (float) str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim($value));

        app(CartService::class)->setQuantity($this->shopId, $productId, $qty);
        $this->dispatch('cart-updated');
    }

    public function remove(int $productId): void
    {
        app(CartService::class)->remove($this->shopId, $productId);
        $this->dispatch('cart-updated');
    }

    /**
     * Aktywny produkt tego sklepu (dla kroku/jednostki), lub null gdy zdjęty.
     */
    private function product(int $productId): ?Product
    {
        return Product::where('shop_id', $this->shopId)->where('is_active', true)->find($productId);
    }

    public function render()
    {
        $lines = app(CartService::class)->lines($this->shopId);

        return view('livewire.cart', [
            'lines' => $lines,
            'total' => $lines->sum('line_total'),
        ]);
    }
}
