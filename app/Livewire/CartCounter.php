<?php

namespace App\Livewire;

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Licznik sztuk w nagłówku storefrontu. Odświeża się na zdarzenie
 * `cart-updated` rozgłaszane przez AddToCart/Cart — bez przeładowania strony.
 */
class CartCounter extends Component
{
    public int $shopId;

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;
    }

    /** Pusty listener — samo wywołanie wymusza ponowny render z aktualnym stanem. */
    #[On('cart-updated')]
    public function refresh(): void {}

    public function render()
    {
        return view('livewire.cart-counter', [
            'count' => app(CartService::class)->count($this->shopId),
        ]);
    }
}
