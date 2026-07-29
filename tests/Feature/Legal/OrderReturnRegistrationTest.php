<?php

namespace Tests\Feature\Legal;

use App\Enums\OrderStatus;
use App\Exceptions\OrderEditException;
use App\Exceptions\OrderReturnException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderEditor;
use App\Services\OrderReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fundament zwrotów konsumenckich (Faza B): rejestracja oświadczenia o
 * odstąpieniu pomniejsza zamówienie, akumulator `returned_quantity` pilnuje, by
 * nie dało się oddać tej samej sztuki dwa razy, a stan magazynowy pozostaje
 * nietknięty (zwrot to NIE anulowanie). Kwoty liczone od ceny po rabacie.
 */
class OrderReturnRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OrderReturnService
    {
        return app(OrderReturnService::class);
    }

    /**
     * @param  array<string, mixed>  $orderAttributes
     */
    private function orderWithItem(float $unit, float $quantity, array $orderAttributes = [], ?Product $product = null): OrderItem
    {
        $product ??= Product::factory()->create(['price_gross' => $unit, 'vat_rate' => '23']);

        $order = Order::factory()->create([
            'shop_id' => $product->shop_id,
            ...$orderAttributes,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price_gross' => $unit,
            'vat_rate' => '23',
            'quantity' => $quantity,
            'sale_unit' => $product->sale_unit->value,
            'line_total_gross' => round($unit * $quantity, 2),
        ]);

        app(\App\Services\OrderTotals::class)->recalculate($order->load('items'));

        return $item;
    }

    /**
     * @return array<string, string|null>
     */
    private function declaration(): array
    {
        return [
            'customer_name' => 'Anna Kowalska',
            'customer_address' => 'ul. Polna 1, 00-001 Warszawa',
            'bank_account' => null,
            'note' => null,
        ];
    }

    public function test_registered_return_shrinks_the_order_and_records_the_quantity(): void
    {
        $item = $this->orderWithItem(50.00, 3);

        $return = $this->service()->register($item->order, [$item->id => 1], $this->declaration());

        $this->assertSame('1.00', $item->fresh()->returned_quantity);
        $this->assertSame('3.00', $item->fresh()->quantity, 'kupiona ilość zostaje migawką');
        $this->assertSame('100.00', $item->fresh()->line_total_gross);
        $this->assertSame('100.00', $item->order->fresh()->total_gross);
        $this->assertSame('50.00', $return->refund_gross);
        $this->assertSame('Anna Kowalska', $return->customer_name);
        $this->assertCount(1, $return->items);
    }

    public function test_stock_is_not_touched_by_a_return(): void
    {
        $product = Product::factory()->create(['track_stock' => true, 'stock' => 2, 'price_gross' => 50.00, 'vat_rate' => '23']);
        $item = $this->orderWithItem(50.00, 3, product: $product);

        $this->service()->register($item->order, [$item->id => 2], $this->declaration());

        $this->assertSame('2.00', $product->fresh()->stock, 'towar wraca na półkę decyzją sprzedawcy, nie automatem');
    }

    public function test_second_return_only_sees_what_is_left(): void
    {
        $item = $this->orderWithItem(50.00, 3);

        $this->service()->register($item->order, [$item->id => 2], $this->declaration());

        $this->assertSame(1.0, $item->fresh()->returnableQuantity());

        $this->expectException(OrderReturnException::class);
        $this->service()->register($item->order->fresh(), [$item->id => 2], $this->declaration());
    }

    public function test_returning_everything_zeroes_the_products_but_keeps_delivery(): void
    {
        $item = $this->orderWithItem(50.00, 2, ['delivery_cost' => 15.00]);

        $this->service()->register($item->order, [$item->id => 2], $this->declaration());

        $order = $item->order->fresh()->load('items');

        $this->assertSame('0.00', $order->items_total);
        $this->assertSame('15.00', $order->total_gross, 'koszt dostawy sprzedawca rozlicza ręcznie');
        $this->assertTrue($order->isFullyReturned());
    }

    public function test_refund_is_calculated_from_the_discounted_price(): void
    {
        // 2 × 100 zł = 200 zł, rabat 20 zł → klient zapłacił 180 zł, więc za
        // jedną sztukę należy mu się 90 zł, a nie 100 zł.
        $item = $this->orderWithItem(100.00, 2, ['discount_amount' => 20.00, 'discount_code' => 'RABAT20']);

        $return = $this->service()->register($item->order, [$item->id => 1], $this->declaration());

        $this->assertSame('90.00', $return->refund_gross);
    }

    public function test_item_excluded_by_article_38_cannot_be_returned(): void
    {
        $product = Product::factory()->create(['withdrawal_excluded' => true, 'price_gross' => 50.00, 'vat_rate' => '23']);
        $item = $this->orderWithItem(50.00, 1, product: $product);

        $this->assertSame(0.0, $item->returnableQuantity());

        $this->expectException(OrderReturnException::class);
        $this->service()->register($item->order, [$item->id => 1], $this->declaration());
    }

    public function test_empty_selection_is_rejected(): void
    {
        $item = $this->orderWithItem(50.00, 2);

        $this->expectException(OrderReturnException::class);
        $this->service()->register($item->order, [$item->id => 0], $this->declaration());
    }

    public function test_cancelled_order_cannot_be_returned(): void
    {
        $item = $this->orderWithItem(50.00, 2, ['status' => OrderStatus::Cancelled]);

        $this->expectException(OrderReturnException::class);
        $this->service()->register($item->order, [$item->id => 1], $this->declaration());
    }

    public function test_seller_cannot_edit_quantity_below_what_was_returned(): void
    {
        $item = $this->orderWithItem(50.00, 3);
        $this->service()->register($item->order, [$item->id => 2], $this->declaration());

        $this->expectException(OrderEditException::class);
        app(OrderEditor::class)->changeQuantity($item->fresh(), 1);
    }

    public function test_seller_cannot_delete_an_item_with_a_return(): void
    {
        $item = $this->orderWithItem(50.00, 3);
        $this->service()->register($item->order, [$item->id => 1], $this->declaration());

        $this->expectException(OrderEditException::class);
        app(OrderEditor::class)->removeItem($item->fresh());
    }
}
