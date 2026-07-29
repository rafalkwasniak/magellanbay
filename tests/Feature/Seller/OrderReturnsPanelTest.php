<?php

namespace Tests\Feature\Seller;

use App\Livewire\Seller\OrderReturns;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\OrderReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sekcja „Zwroty" na szczególe zamówienia u sprzedawcy (Faza C): pokazuje, co
 * klient oddał i za ile, a jedyną akcją jest odnotowanie zwrotu pieniędzy —
 * odstąpienia się nie akceptuje ani nie odrzuca, bo działa z mocy prawa.
 */
class OrderReturnsPanelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: OrderItem}
     */
    private function sellerWithReturnedOrder(bool $withReturn = true): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'price_gross' => 40.00, 'vat_rate' => '23']);

        $order = Order::factory()->create(['shop_id' => $shop->id]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => 'Wazon szklany',
            'unit_price_gross' => 40.00,
            'vat_rate' => '23',
            'quantity' => 3,
            'sale_unit' => $product->sale_unit->value,
            'line_total_gross' => 120.00,
        ]);

        app(\App\Services\OrderTotals::class)->recalculate($order->load('items'));

        if ($withReturn) {
            app(OrderReturnService::class)->register($order->fresh()->load('items'), [$item->id => 1], [
                'customer_name' => 'Anna Kowalska',
                'customer_address' => 'ul. Polna 7, 00-001 Warszawa',
                'bank_account' => '12345678901234567890123456',
                'note' => 'Wazon okazał się za mały.',
            ]);
        }

        return [$seller, $item->fresh()];
    }

    public function test_returns_card_shows_what_came_back(): void
    {
        [$seller, $item] = $this->sellerWithReturnedOrder();

        $this->actingAs($seller)->get(route('seller.orders.show', $item->order))
            ->assertOk()
            ->assertSee('Zwroty')
            ->assertSee('Wazon szklany')
            ->assertSee('Anna Kowalska')
            ->assertSee('12345678901234567890123456')
            ->assertSee('Wazon okazał się za mały.')
            ->assertSee('Do zwrotu klientowi:')
            // Pozycja w edytorze musi tłumaczyć, czemu kwota jest niższa niż 3 × 40 zł.
            ->assertSee('Klient zwrócił 1 szt. z 3 szt.');
    }

    public function test_card_states_the_deadline_for_refunding_the_money(): void
    {
        [$seller, $item] = $this->sellerWithReturnedOrder();
        $deadline = $item->order->returns()->first()->refundDeadline();

        // Art. 32 ust. 1: 14 dni od otrzymania oświadczenia. Sprzedawca ma
        // zobaczyć KONKRETNĄ datę, nie „w ciągu 14 dni".
        $this->assertSame(now()->addDays(14)->format('d.m.Y'), $deadline->format('d.m.Y'));

        $this->actingAs($seller)->get(route('seller.orders.show', $item->order))
            ->assertOk()
            ->assertSee('Pieniądze oddaj do')
            ->assertSee($deadline->format('d.m.Y'));
    }

    public function test_overdue_refund_is_called_out(): void
    {
        [$seller, $item] = $this->sellerWithReturnedOrder();
        $return = $item->order->returns()->first();
        $return->forceFill(['created_at' => now()->subDays(20)])->save();

        $this->assertTrue($return->fresh()->isRefundOverdue());

        $this->actingAs($seller)->get(route('seller.orders.show', $item->order))
            ->assertOk()
            ->assertSee('Termin zwrotu pieniędzy minął');
    }

    public function test_refunded_return_is_never_overdue(): void
    {
        [, $item] = $this->sellerWithReturnedOrder();
        $return = $item->order->returns()->first();
        $return->forceFill(['created_at' => now()->subDays(20)])->save();
        $return->markRefunded();

        $this->assertFalse($return->fresh()->isRefundOverdue());
    }

    public function test_fully_returned_item_says_so_plainly(): void
    {
        [$seller, $item] = $this->sellerWithReturnedOrder(withReturn: false);

        // Zwrot CAŁEJ pozycji: „kwota dotyczy 0 szt." nic nie znaczy, więc
        // komunikat musi mówić wprost, co się stało.
        app(OrderReturnService::class)->register($item->order->fresh()->load('items'), [$item->id => 3], [
            'customer_name' => 'Anna Kowalska',
            'customer_address' => 'ul. Polna 7',
        ]);

        $this->actingAs($seller)->get(route('seller.orders.show', $item->order))
            ->assertOk()
            ->assertSee('Klient zwrócił tę pozycję w całości')
            ->assertDontSee('kwota dotyczy 0 szt.');
    }

    public function test_partially_returned_item_shows_both_quantities(): void
    {
        [$seller, $item] = $this->sellerWithReturnedOrder();

        $this->actingAs($seller)->get(route('seller.orders.show', $item->order))
            ->assertOk()
            ->assertSee('Klient zwrócił 1 szt. z 3 szt.')
            ->assertSee('kwota obok dotyczy pozostałych 2 szt.');
    }

    public function test_card_is_hidden_when_there_are_no_returns(): void
    {
        [$seller, $item] = $this->sellerWithReturnedOrder(withReturn: false);

        $this->actingAs($seller)->get(route('seller.orders.show', $item->order))
            ->assertOk()
            ->assertDontSee('Do zwrotu klientowi:');
    }

    public function test_seller_marks_the_money_as_refunded(): void
    {
        [$seller, $item] = $this->sellerWithReturnedOrder();
        $return = $item->order->returns()->first();

        $this->actingAs($seller);

        Livewire::test(OrderReturns::class, ['order' => $item->order])
            ->call('markRefunded', $return->id)
            ->assertOk();

        $this->assertNotNull($return->fresh()->refunded_at);
        $this->assertTrue($return->fresh()->isRefunded());
    }

    public function test_seller_cannot_touch_a_return_from_another_shop(): void
    {
        [, $item] = $this->sellerWithReturnedOrder();
        $return = $item->order->returns()->first();

        $intruder = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $intruder->id]);

        $this->actingAs($intruder);

        Livewire::test(OrderReturns::class, ['order' => $item->order])
            ->call('markRefunded', $return->id)
            ->assertForbidden();

        $this->assertNull($return->fresh()->refunded_at);
    }
}
