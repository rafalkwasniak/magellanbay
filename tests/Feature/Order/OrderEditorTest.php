<?php

namespace Tests\Feature\Order;

use App\Enums\SaleUnit;
use App\Exceptions\OrderEditException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Services\OrderEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Edycja zamówienia w panelu sprzedawcy (OrderEditor): zmiana ilości z sufitem
 * stanu (stan + ilość na pozycji), zmiana ceny (zamrożonej, bez ruszania
 * produktu), dodanie produktu w bieżącej cenie oraz usunięcie pozycji ze zwrotem
 * na stan. Sumy zawsze przez wspólny OrderTotals — jak przy składaniu zamówienia.
 */
class OrderEditorTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): OrderEditor
    {
        return app(OrderEditor::class);
    }

    /**
     * Zamówienie z jedną pozycją-migawką. `stock` produktu odzwierciedla stan już
     * PO zdjęciu tej pozycji (jak w produkcji), więc sufit = stock + quantity.
     */
    private function orderWithItem(Product $product, float $quantity, ?float $unit = null): OrderItem
    {
        $unit ??= (float) $product->price_gross;

        $order = Order::factory()->create(['shop_id' => $product->shop_id]);

        return $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price_gross' => $unit,
            'vat_rate' => $product->vat_rate->value,
            'quantity' => $quantity,
            'sale_unit' => $product->sale_unit->value,
            'line_total_gross' => round($unit * $quantity, 2),
        ]);
    }

    public function test_increase_quantity_within_ceiling_takes_from_stock(): void
    {
        // Pierwotnie 5 na stanie, kupiono 4 → został 1. Sufit = 1 + 4 = 5.
        $product = Product::factory()->create(['track_stock' => true, 'stock' => 1, 'price_gross' => 12.00, 'vat_rate' => '23']);
        $item = $this->orderWithItem($product, 4);

        $this->editor()->changeQuantity($item, 5);

        $this->assertSame('5.00', $item->fresh()->quantity);
        $this->assertSame('60.00', $item->fresh()->line_total_gross);
        $this->assertSame('0.00', $product->fresh()->stock);          // 1 zdjęte
        $this->assertSame('60.00', $item->order->fresh()->total_gross);
    }

    public function test_increase_quantity_over_ceiling_is_rejected(): void
    {
        $product = Product::factory()->create(['track_stock' => true, 'stock' => 1, 'price_gross' => 12.00]);
        $item = $this->orderWithItem($product, 4);

        try {
            $this->editor()->changeQuantity($item, 6);   // sufit to 5
            $this->fail('Spodziewano się OrderEditException.');
        } catch (OrderEditException $e) {
            $this->assertStringContainsString('Dostępny stan', $e->getMessage());
        }

        // Nic się nie zmieniło (transakcja wycofana).
        $this->assertSame('4.00', $item->fresh()->quantity);
        $this->assertSame('1.00', $product->fresh()->stock);
    }

    public function test_decrease_quantity_returns_to_stock(): void
    {
        $product = Product::factory()->create(['track_stock' => true, 'stock' => 1, 'price_gross' => 12.00]);
        $item = $this->orderWithItem($product, 4);

        $this->editor()->changeQuantity($item, 3);   // 1 wraca na stan

        $this->assertSame('3.00', $item->fresh()->quantity);
        $this->assertSame('2.00', $product->fresh()->stock);
        $this->assertSame('36.00', $item->order->fresh()->total_gross);
    }

    public function test_quantity_without_stock_tracking_has_no_ceiling(): void
    {
        $product = Product::factory()->create(['track_stock' => false, 'stock' => null, 'price_gross' => 10.00]);
        $item = $this->orderWithItem($product, 2);

        $this->editor()->changeQuantity($item, 100);

        $this->assertSame('100.00', $item->fresh()->quantity);
        $this->assertSame('1000.00', $item->order->fresh()->total_gross);
    }

    public function test_change_price_recalculates_order_and_leaves_product_untouched(): void
    {
        $product = Product::factory()->create(['track_stock' => false, 'stock' => null, 'price_gross' => 12.00, 'vat_rate' => '23']);
        $item = $this->orderWithItem($product, 4, 12.00);

        $this->editor()->changePrice($item, 11.00);

        $order = $item->order->fresh();
        $this->assertSame('11.00', $item->fresh()->unit_price_gross);
        $this->assertSame('44.00', $item->fresh()->line_total_gross);
        // 44 brutto @ 23% → netto 35.77, VAT 8.23.
        $this->assertSame('44.00', $order->total_gross);
        $this->assertSame('35.77', $order->total_net);
        $this->assertSame('8.23', $order->total_vat);
        // Cena produktu w sklepie nietknięta — cena zamówienia jest odpięta.
        $this->assertSame('12.00', $product->fresh()->price_gross);
    }

    public function test_add_product_snapshots_current_price_and_takes_stock(): void
    {
        $shop = Shop::factory()->create();
        $first = Product::factory()->create(['shop_id' => $shop->id, 'track_stock' => false, 'stock' => null, 'price_gross' => 10.00]);
        $item = $this->orderWithItem($first, 1);
        $order = $item->order;

        $added = Product::factory()->create(['shop_id' => $shop->id, 'track_stock' => true, 'stock' => 3, 'price_gross' => 20.00, 'vat_rate' => '23']);

        $this->editor()->addProduct($order, $added->id, 2);

        $this->assertCount(2, $order->fresh()->items);
        $line = $order->items()->where('product_id', $added->id)->firstOrFail();
        $this->assertSame('20.00', $line->unit_price_gross);   // migawka bieżącej ceny
        $this->assertSame('2.00', $line->quantity);
        $this->assertSame('40.00', $line->line_total_gross);
        $this->assertSame('1.00', $added->fresh()->stock);     // 2 zdjęte z 3
        $this->assertSame('50.00', $order->fresh()->total_gross);   // 10 + 40
    }

    public function test_add_product_over_stock_is_rejected(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'track_stock' => true, 'stock' => 3, 'price_gross' => 20.00]);
        $order = Order::factory()->create(['shop_id' => $shop->id]);

        $this->expectException(OrderEditException::class);
        $this->editor()->addProduct($order, $product->id, 5);
    }

    public function test_add_existing_product_merges_into_line(): void
    {
        $shop = Shop::factory()->create();
        // Pierwotnie 5, kupiono 2 → został 3. Dosypanie 2 → pozycja 4, stan 1.
        $product = Product::factory()->create(['shop_id' => $shop->id, 'track_stock' => true, 'stock' => 3, 'price_gross' => 15.00]);
        $item = $this->orderWithItem($product, 2);
        $order = $item->order;

        $this->editor()->addProduct($order, $product->id, 2);

        $this->assertCount(1, $order->fresh()->items);   // bez duplikatu
        $this->assertSame('4.00', $item->fresh()->quantity);
        $this->assertSame('1.00', $product->fresh()->stock);
    }

    public function test_add_inactive_product_is_rejected(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->hidden()->create(['shop_id' => $shop->id]);
        $order = Order::factory()->create(['shop_id' => $shop->id]);

        $this->expectException(OrderEditException::class);
        $this->editor()->addProduct($order, $product->id, 1);
    }

    public function test_remove_item_restores_stock_and_recalculates(): void
    {
        $product = Product::factory()->create(['track_stock' => true, 'stock' => 1, 'price_gross' => 12.00]);
        $item = $this->orderWithItem($product, 4);
        $order = $item->order;

        $this->editor()->removeItem($item);

        $this->assertCount(0, $order->fresh()->items);
        $this->assertSame('5.00', $product->fresh()->stock);   // 4 wróciły do 1
        $this->assertSame('0.00', $order->fresh()->total_gross);
    }

    public function test_weight_quantity_is_normalized_and_ceiled(): void
    {
        // Waga: został 1,00 kg, na pozycji 2,00 kg → sufit 3,00 kg.
        $product = Product::factory()->weight()->create(['track_stock' => true, 'stock' => 1.0, 'price_gross' => 30.00]);
        $item = $this->orderWithItem($product, 2.0);

        $this->editor()->changeQuantity($item, 2.5);

        $this->assertSame('2.50', $item->fresh()->quantity);
        $this->assertSame('0.50', $product->fresh()->stock);
        $this->assertSame(SaleUnit::Weight, $item->fresh()->sale_unit);
    }

    public function test_weight_below_minimum_is_rejected(): void
    {
        $product = Product::factory()->weight()->create(['track_stock' => true, 'stock' => 1.0, 'price_gross' => 30.00]);
        $item = $this->orderWithItem($product, 2.0);

        // 0,3 kg < minimum 0,5 kg → normalizacja do 0 → odrzucone.
        $this->expectException(OrderEditException::class);
        $this->editor()->changeQuantity($item, 0.3);
    }
}
