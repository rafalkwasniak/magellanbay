<?php

namespace App\Observers;

use App\Models\Product;

/**
 * Utrzymuje widoczność sklepu w zgodzie z jego produktami. Każda zmiana produktu,
 * która może wpłynąć na liczbę aktywnych produktów (utworzenie, edycja flagi
 * is_active, usunięcie, przywrócenie), przelicza status sklepu. Sklep staje się
 * widoczny (Aktywny), gdy ma ≥1 aktywny produkt, i znika (Szkic), gdy zostanie 0.
 * Dzięki temu auto-publikacja działa w obie strony bez ingerencji sprzedawcy.
 */
class ProductObserver
{
    public function saved(Product $product): void
    {
        $this->sync($product);
    }

    public function deleted(Product $product): void
    {
        $this->sync($product);
    }

    public function restored(Product $product): void
    {
        $this->sync($product);
    }

    public function forceDeleted(Product $product): void
    {
        $this->sync($product);
    }

    private function sync(Product $product): void
    {
        $product->shop?->refreshVisibility();
    }
}
