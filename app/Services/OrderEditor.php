<?php

namespace App\Services;

use App\Exceptions\OrderEditException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Edycja złożonego zamówienia w panelu sprzedawcy: zmiana ilości i ceny pozycji,
 * dodanie produktu, usunięcie pozycji. Każda operacja jest atomowa (transakcja +
 * blokada wiersza produktu) i przelicza sumy przez OrderTotals — edytowane
 * zamówienie liczy się jak nowo złożone.
 *
 * Reguła stanu przy edycji: dostępny SUFIT na pozycji = bieżący stan produktu +
 * ilość już zapisana na tej pozycji (bo tę ilość zdjęto ze stanu przy składaniu).
 * Zejście z ilości oddaje różnicę na stan, wejście — pobiera. Produkt bez kontroli
 * stanu (`track_stock=false` lub `stock=null`) nie ma sufitu.
 *
 * Ceny są zamrożone: zmiana ceny pozycji NIE dotyka ceny produktu w sklepie.
 * Dodany produkt bierze bieżącą cenę sklepu jako migawkę (bez możliwości zmiany tu).
 */
class OrderEditor
{
    public function __construct(private OrderTotals $totals) {}

    /**
     * Zmienia ilość pozycji z poszanowaniem stanu (sufit = stan + ilość na pozycji).
     * Różnica ilości wędruje na/ze stanu. Ilość ≤ 0 → wyjątek (usuwanie osobno).
     */
    public function changeQuantity(OrderItem $item, float $newQuantity): void
    {
        DB::transaction(function () use ($item, $newQuantity): void {
            $this->guardEditable($item->order()->first());

            $product = $this->lockedProduct($item->product_id);
            $newQuantity = $item->sale_unit->normalizeQuantity($newQuantity);

            if ($newQuantity <= 0) {
                throw new OrderEditException('Podaj ilość większą od zera — aby usunąć pozycję, użyj „Usuń".');
            }

            // Nie wolno zejść poniżej tego, co klient już oddał — zamówienie
            // pokazywałoby wtedy więcej zwróconych sztuk, niż w ogóle kupionych,
            // a kwota zwrotu przestałaby mieć pokrycie w pozycji.
            if ($newQuantity < (float) $item->returned_quantity) {
                throw new OrderEditException(
                    'Z tej pozycji zwrócono już '.$item->sale_unit->formatQuantity((float) $item->returned_quantity).' — nie można zejść poniżej tej ilości.',
                );
            }

            $this->guardStock($product, $newQuantity, (float) $item->quantity);
            $this->applyStockDelta($product, (float) $item->quantity, $newQuantity);

            $item->quantity = $newQuantity;
            $item->save();

            $this->totals->recalculate($item->order()->first()->load('items'));
        });
    }

    /**
     * Zmienia cenę jednostkową (brutto) pozycji i przelicza całe zamówienie.
     * Nie dotyka stanu ani ceny produktu w sklepie — cena zamówienia jest odpięta.
     */
    public function changePrice(OrderItem $item, float $newUnitGross): void
    {
        if ($newUnitGross < 0) {
            throw new OrderEditException('Cena nie może być ujemna.');
        }

        DB::transaction(function () use ($item, $newUnitGross): void {
            $this->guardEditable($item->order()->first());

            $item->unit_price_gross = round($newUnitGross, 2);
            $item->save();

            $this->totals->recalculate($item->order()->first()->load('items'));
        });
    }

    /**
     * Dodaje produkt sklepu do zamówienia w bieżącej cenie (migawka), respektując
     * stan. Gdy produkt już jest na zamówieniu — dokłada do istniejącej pozycji
     * (bez duplikatu), licząc sufit względem tej pozycji.
     */
    public function addProduct(Order $order, int $productId, float $quantity): void
    {
        DB::transaction(function () use ($order, $productId, $quantity): void {
            $this->guardEditable($order);

            $product = Product::where('shop_id', $order->shop_id)
                ->where('is_active', true)
                ->whereKey($productId)
                ->lockForUpdate()
                ->first();

            if ($product === null) {
                throw new OrderEditException('Produkt jest niedostępny.');
            }

            $quantity = $product->sale_unit->normalizeQuantity($quantity);

            if ($quantity <= 0) {
                throw new OrderEditException('Podaj ilość większą od zera.');
            }

            $existing = $order->items()->where('product_id', $product->id)->first();

            if ($existing !== null) {
                // Produkt już na zamówieniu → dokładamy do jego pozycji.
                $target = round((float) $existing->quantity + $quantity, 2);
                $this->guardStock($product, $target, (float) $existing->quantity);
                $this->applyStockDelta($product, (float) $existing->quantity, $target);

                $existing->quantity = $target;
                $existing->save();
            } else {
                // Nowa pozycja → sufit = bieżący stan (nic jeszcze nie zdjęto dla tej linii).
                $this->guardStock($product, $quantity, 0.0);
                $this->applyStockDelta($product, 0.0, $quantity);

                [$lineGross] = $this->totals->lineAmounts((float) $product->price_gross, $quantity, $product->vat_rate);

                $order->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'unit_price_gross' => (float) $product->price_gross,
                    'vat_rate' => $product->vat_rate->value,
                    'quantity' => $quantity,
                    'sale_unit' => $product->sale_unit->value,
                    'line_total_gross' => $lineGross,
                ]);
            }

            $this->totals->recalculate($order->load('items'));
        });
    }

    /**
     * Usuwa pozycję zamówienia i oddaje jej ilość na stan (jeśli produkt śledzony).
     */
    public function removeItem(OrderItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $product = $this->lockedProduct($item->product_id);
            $order = $item->order()->first();

            $this->guardEditable($order);

            // Pozycja ze zgłoszonym zwrotem zostaje: skasowanie jej wymazałoby
            // oświadczenie konsumenta razem z kwotą, którą sklep ma mu oddać.
            if ($item->hasReturns()) {
                throw new OrderEditException('Ta pozycja ma zgłoszony zwrot — nie można jej usunąć z zamówienia.');
            }

            $this->applyStockDelta($product, (float) $item->quantity, 0.0);

            $item->delete();

            $this->totals->recalculate($order->load('items'));
        });
    }

    /**
     * Anulowane zamówienie jest zamrożone — zostaje w systemie wyłącznie
     * informacyjnie. To nie jest kosmetyka UI: anulowanie oddało już towar na
     * stan, więc usunięcie pozycji po fakcie oddałoby go DRUGI raz i rozjechało
     * magazyn. Bramka siedzi w serwisie, żeby złapać każde wejście.
     */
    private function guardEditable(?Order $order): void
    {
        if ($order === null) {
            return;
        }

        if ($order->status->isTerminal()) {
            throw new OrderEditException('Zamówienie jest anulowane — nie można go już edytować.');
        }

        // Pobranie po nadaniu jest ZAMROŻONE. Kwota do zainkasowania poszła do
        // InPostu razem z przesyłką i nie da się jej już ruszyć: `PUT` odpowiada
        // 400 `shipment_status_incorrect`, anulowanie przesyłki tak samo
        // (sprawdzone na sandboxie 17.08).
        //
        // Bez tej bramki edycja rozjeżdżałaby zamówienie z rzeczywistością w
        // sposób, którego nikt nie zdąży naprawić: klient zapłaciłby STARĄ kwotę
        // przy skrytce albo kurierowi, sprzedawca dostałby od InPostu tyle samo,
        // a zamówienie i faktura mówiłyby co innego. Rozbieżność wychodzi wtedy
        // u klienta, nie w panelu.
        //
        // Granicą jest NADANIE, nie metoda płatności — przed nadaniem edycja
        // zamówienia pobraniowego jest bezpieczniejsza niż opłaconego, bo nie ma
        // czego zwracać.
        if ($order->delivery_method?->isCashOnDelivery() === true && $order->hasShipment()) {
            throw new OrderEditException(
                'Przesyłka jest już nadana, a kwota pobrania przekazana do InPostu — nie da się jej zmienić. '
                .'Edycja tego zamówienia jest zablokowana, żeby kurier nie pobrał od klienta innej kwoty, niż mówi zamówienie.',
            );
        }
    }

    /**
     * Wiersz produktu pod blokadą do bezpiecznej korekty stanu. `null`, gdy pozycja
     * jest osierocona (produkt twardo usunięty) — wtedy edycja bez kontroli stanu.
     */
    private function lockedProduct(?int $productId): ?Product
    {
        if ($productId === null) {
            return null;
        }

        return Product::whereKey($productId)->lockForUpdate()->first();
    }

    /**
     * Czy produkt faktycznie śledzi stan (ta sama bramka co w koszyku/składaniu).
     * Pozycja osierocona (produkt usunięty) — brak kontroli stanu.
     */
    private function tracksStock(?Product $product): bool
    {
        return $product !== null && $product->tracksStock();
    }

    /**
     * Pilnuje sufitu: stan + ilość już na tej pozycji. Przekroczenie → wyjątek.
     * Produkt bez kontroli stanu — brak sufitu.
     */
    private function guardStock(?Product $product, float $newQuantity, float $currentOnLine): void
    {
        if (! $this->tracksStock($product)) {
            return;
        }

        $ceiling = round((float) $product->stock + $currentOnLine, 2);

        if ($newQuantity > $ceiling) {
            throw new OrderEditException('Dostępny stan to '.$product->sale_unit->formatQuantity($ceiling).' (magazyn + ta pozycja).');
        }
    }

    /**
     * Przenosi różnicę ilości między stanem a pozycją: wejście pobiera ze stanu,
     * zejście oddaje. Bez kontroli stanu — nic nie robimy.
     */
    private function applyStockDelta(?Product $product, float $oldQuantity, float $newQuantity): void
    {
        if (! $this->tracksStock($product)) {
            return;
        }

        $delta = round($newQuantity - $oldQuantity, 2);

        if ($delta > 0) {
            $product->decrement('stock', $delta);
        } elseif ($delta < 0) {
            $product->increment('stock', abs($delta));
        }
    }
}
