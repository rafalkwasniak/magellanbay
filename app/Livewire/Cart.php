<?php

namespace App\Livewire;

use App\Services\CartService;
use Livewire\Component;

/**
 * Strona koszyka: lista pozycji, zmiana ilości (+/−), usuwanie i suma. Stan
 * trzyma CartService (sesja); komponent tylko go modyfikuje i re-renderuje.
 * Każda zmiana rozgłasza `cart-updated`, żeby licznik w nagłówku nadążał.
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
        $cart = app(CartService::class);
        $cart->setQuantity($this->shopId, $productId, ($cart->raw($this->shopId)[$productId] ?? 0) + 1);
        $this->dispatch('cart-updated');
    }

    public function decrement(int $productId): void
    {
        $cart = app(CartService::class);
        $current = $cart->raw($this->shopId)[$productId] ?? 0;

        // „−" schodzi tylko do 1 — usuwanie jest osobne (kosz + potwierdzenie),
        // żeby dwuklik nie skasował pozycji przez przypadek.
        if ($current > 1) {
            $cart->setQuantity($this->shopId, $productId, $current - 1);
            $this->dispatch('cart-updated');
        }
    }

    public function remove(int $productId): void
    {
        app(CartService::class)->remove($this->shopId, $productId);
        $this->dispatch('cart-updated');
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
