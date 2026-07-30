<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Miękki zamek limitu produktów: po wygaśnięciu abonamentu sklep spada na
 * uprawnienia pakietu darmowego, a nadwyżkę produktów trzeba schować. Po opłacie
 * wraca dokładnie to, co system ukrył — i nic więcej.
 *
 * DLACZEGO NIE KASUJEMY I NIE RUSZAMY DECYZJI SPRZEDAWCY: produkt ukryty przez
 * zamek dostaje `auto_hidden_at`. Produkt, który sprzedawca wyłączył sam (koniec
 * sezonu, brak towaru), tego znacznika nie ma — i zamek go nie tyka ani przy
 * ukrywaniu, ani przy przywracaniu. Bez tego rozróżnienia „wracają jednym
 * ruchem" cofałoby cudze decyzje.
 *
 * KOLEJNOŚĆ UKRYWANIA (decyzja Rafała): najpierw NAJMNIEJ POPULARNE, czyli te
 * o najmniejszej sprzedaży; przy remisie (np. wszystko po zerze) — najstarsze.
 * Sensowniej niż „po prostu najstarsze": bestseller z pierwszego tygodnia
 * zostaje w sklepie, a leżak z zeszłego miesiąca schodzi.
 *
 * Sprzedawca może potem sam przełączyć, które produkty trzyma w limicie —
 * zamek nie zabiera mu decyzji, tylko doprowadza sklep do zgodności.
 */
class ProductLimitLock
{
    /**
     * Doprowadza sklep do zgodności z bieżącym limitem: ukrywa nadwyżkę
     * aktywnych produktów. Zwraca liczbę ukrytych.
     *
     * Wołane po wygaśnięciu abonamentu. Idempotentne — przy sklepie mieszczącym
     * się w limicie nic nie robi.
     */
    public function enforce(Shop $shop): int
    {
        $limit = (int) $shop->entitlement('max_products');
        $active = $shop->products()->where('is_active', true)->count();
        $excess = $active - $limit;

        if ($excess <= 0) {
            return 0;
        }

        $toHide = $this->leastPopular($shop, $excess);

        DB::transaction(function () use ($toHide): void {
            foreach ($toHide as $product) {
                $product->forceFill([
                    'is_active' => false,
                    'auto_hidden_at' => now(),
                ])->save();
            }
        });

        Log::info('Zamek limitu: ukryto nadwyżkę produktów.', [
            'shop_id' => $shop->id,
            'limit' => $limit,
            'hidden' => $toHide->count(),
        ]);

        return $toHide->count();
    }

    /**
     * Przywraca produkty ukryte przez zamek — tyle, ile zmieści się w bieżącym
     * limicie, od NAJPOPULARNIEJSZYCH (odwrotnie niż przy ukrywaniu). Zwraca
     * liczbę przywróconych.
     *
     * Wołane po opłaceniu pakietu. Produktów ukrytych ręcznie nie dotyka.
     */
    public function restore(Shop $shop): int
    {
        $limit = (int) $shop->entitlement('max_products');
        $active = $shop->products()->where('is_active', true)->count();
        $room = $limit - $active;

        if ($room <= 0) {
            return 0;
        }

        $hidden = $shop->products()
            ->whereNotNull('auto_hidden_at')
            ->where('is_active', false)
            ->get();

        if ($hidden->isEmpty()) {
            return 0;
        }

        $sales = $this->salesByProduct($shop);

        // Wracają najpierw te, które sprzedawały się najlepiej — sklep od razu
        // pokazuje to, co realnie zarabia.
        $toRestore = $hidden
            ->sortByDesc(fn (Product $product): float => $sales[$product->id] ?? 0.0)
            ->take($room);

        DB::transaction(function () use ($toRestore): void {
            foreach ($toRestore as $product) {
                $product->forceFill([
                    'is_active' => true,
                    'auto_hidden_at' => null,
                ])->save();
            }
        });

        Log::info('Zamek limitu: przywrócono produkty po opłaceniu.', [
            'shop_id' => $shop->id,
            'limit' => $limit,
            'restored' => $toRestore->count(),
        ]);

        return $toRestore->count();
    }

    /**
     * Aktywne produkty do ukrycia: najmniej sprzedane, a przy równej sprzedaży
     * najstarsze (deterministycznie, żeby dwa przebiegi dały ten sam wynik).
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function leastPopular(Shop $shop, int $count): \Illuminate\Support\Collection
    {
        $sales = $this->salesByProduct($shop);

        return $shop->products()
            ->where('is_active', true)
            ->get()
            ->sortBy([
                fn (Product $a, Product $b): int => ($sales[$a->id] ?? 0.0) <=> ($sales[$b->id] ?? 0.0),
                fn (Product $a, Product $b): int => $a->id <=> $b->id,
            ])
            ->take($count);
    }

    /**
     * Sprzedaż per produkt: suma ilości z pozycji zamówień, BEZ anulowanych i
     * bez tego, co klienci zwrócili (`returned_quantity`) — zwrot znaczy, że ta
     * sprzedaż się nie utrzymała, więc nie może chronić produktu przed zamkiem.
     *
     * @return array<int, float>
     */
    private function salesByProduct(Shop $shop): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.shop_id', $shop->id)
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', \App\Enums\OrderStatus::Cancelled->value)
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity - order_items.returned_quantity) as sold')
            ->pluck('sold', 'product_id')
            ->map(fn ($sold): float => (float) $sold)
            ->all();
    }
}
