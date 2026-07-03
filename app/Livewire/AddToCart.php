<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Przycisk „Do koszyka". Zna stan magazynowy produktu i ile jego sztuk jest już
 * w koszyku, dzięki czemu: pokazuje dostępną ilość, blokuje się po dodaniu
 * wszystkich dostępnych sztuk i odróżnia „wyprzedane" od „masz już wszystko".
 */
class AddToCart extends Component
{
    public int $productId = 0;

    public int $shopId = 0;

    public bool $active = true;

    public bool $trackStock = false;

    public ?int $stock = null;

    /** Wariant na kafel: pełna szerokość, bez linii „Dostępne: N szt.". */
    public bool $compact = false;

    public function mount(Product $product, bool $compact = false): void
    {
        $this->productId = $product->id;
        $this->shopId = (int) $product->shop_id;
        $this->active = (bool) $product->is_active;
        $this->trackStock = (bool) $product->track_stock;
        $this->stock = $product->stock;
        $this->compact = $compact;
    }

    public function add(CartService $cart): void
    {
        $product = Product::where('is_active', true)->find($this->productId);

        if ($product !== null) {
            $cart->add($product);
            $this->dispatch('cart-updated');
        }
    }

    /** Utrzymuje przycisk w zgodzie z koszykiem, gdy zmieni go inny komponent. */
    #[On('cart-updated')]
    public function refresh(): void {}

    public function render()
    {
        $inCart = app(CartService::class)->raw($this->shopId)[$this->productId] ?? 0;
        $limited = $this->trackStock && $this->stock !== null;
        $remaining = $limited ? max(0, $this->stock - $inCart) : null;

        return view('livewire.add-to-cart', [
            'limited' => $limited,
            'stock' => $this->stock,
            'inCart' => $inCart,
            'remaining' => $remaining,
            'canAdd' => $this->active && (! $limited || $remaining > 0),
        ]);
    }
}
