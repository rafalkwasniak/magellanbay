<?php

namespace App\Observers;

use App\Jobs\GenerateSeoDescription;
use App\Models\Product;

/**
 * Utrzymuje widoczność sklepu w zgodzie z jego produktami. Każda zmiana produktu,
 * która może wpłynąć na liczbę aktywnych produktów (utworzenie, edycja flagi
 * is_active, usunięcie, przywrócenie), przelicza status sklepu. Sklep staje się
 * widoczny (Aktywny), gdy ma ≥1 aktywny produkt, i znika (Szkic), gdy zostanie 0.
 * Dzięki temu auto-publikacja działa w obie strony bez ingerencji sprzedawcy.
 *
 * Dodatkowo prowadzi historię cen (Omnibus): zapisuje wpis przy utworzeniu
 * produktu oraz przy każdej zmianie ceny brutto.
 */
class ProductObserver
{
    public function created(Product $product): void
    {
        $this->recordPrice($product);
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged('price_gross')) {
            $this->recordPrice($product);
        }
    }

    public function saved(Product $product): void
    {
        $this->sync($product);
        $this->refreshSeoDescription($product);
    }

    public function deleted(Product $product): void
    {
        $this->sync($product);
        $product->shop?->pruneOrphanTags();
    }

    public function restored(Product $product): void
    {
        $this->sync($product);
    }

    public function forceDeleted(Product $product): void
    {
        $this->sync($product);
        $product->shop?->pruneOrphanTags();
    }

    private function sync(Product $product): void
    {
        $product->shop?->refreshVisibility();
    }

    /**
     * Zleca napisanie opisu SEO, gdy zmieniła się treść, z której powstaje.
     *
     * Warunek `wasChanged` jest tu kluczowy: bez niego każde zapisanie produktu
     * (zmiana ceny, stanu, wyróżnienia) paliłoby wywołanie AI za tekst, który
     * się nie zmienił. Ręcznie napisanego opisu nie ruszamy — job to sprawdza
     * jeszcze raz, ale nie ma po co go nawet kolejkować.
     */
    private function refreshSeoDescription(Product $product): void
    {
        if ($product->meta_description_manual) {
            return;
        }

        if ($product->wasChanged(['description', 'name'])) {
            GenerateSeoDescription::dispatch($product);
        }
    }

    private function recordPrice(Product $product): void
    {
        $product->priceHistory()->create([
            'price_gross' => $product->price_gross,
            'recorded_at' => now(),
        ]);
    }
}
