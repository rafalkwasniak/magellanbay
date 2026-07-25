<?php

namespace Tests\Feature\Legal;

use App\Enums\OrderStatus;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\OrderMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Prawo odstąpienia od umowy (14 dni, ustawa z 30 maja 2014 o prawach
 * konsumenta) — Faza A: flaga wyłączenia per produkt (art. 38), termin liczony
 * na zamówieniu i pouczenie w mailu potwierdzającym. Brak pouczenia wydłuża
 * termin odstąpienia do 12 miesięcy, więc to obowiązek, nie ozdoba.
 */
class WithdrawalNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function itemFor(Order $order, ?Product $product, string $name): OrderItem
    {
        return $order->items()->create([
            'product_id' => $product?->id,
            'name' => $name,
            'unit_price_gross' => 100,
            'vat_rate' => '23',
            'quantity' => 1,
            'line_total_gross' => 100,
        ]);
    }

    public function test_product_is_withdrawable_by_default(): void
    {
        $product = Product::factory()->create();

        // Domyślnie zwrot PRZYSŁUGUJE — wyjątek zaznacza sprzedawca, nie system.
        $this->assertFalse($product->withdrawal_excluded);
        $this->assertTrue($product->isWithdrawable());
    }

    public function test_seller_can_mark_a_product_as_excluded_from_returns(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)->post(route('seller.products.update', $product), [
            'name' => $product->name,
            'price_gross' => '100,00',
            'vat_rate' => $product->vat_rate->value,
            'sale_unit' => $product->sale_unit->value,
            'stock' => '5',
            'track_stock' => '1',
            'withdrawal_excluded' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertTrue($product->fresh()->withdrawal_excluded);
        $this->assertFalse($product->fresh()->isWithdrawable());
    }

    public function test_order_with_only_excluded_items_has_nothing_to_withdraw(): void
    {
        $shop = Shop::factory()->create();
        $flowers = Product::factory()->create(['shop_id' => $shop->id, 'withdrawal_excluded' => true]);
        $order = Order::factory()->for($shop)->create();
        $this->itemFor($order, $flowers, 'Bukiet z żywych kwiatów');

        $this->assertFalse($order->fresh()->hasWithdrawableItems());
    }

    public function test_mixed_order_counts_as_withdrawable(): void
    {
        $shop = Shop::factory()->create();
        $flowers = Product::factory()->create(['shop_id' => $shop->id, 'withdrawal_excluded' => true]);
        $pot = Product::factory()->create(['shop_id' => $shop->id]);
        $order = Order::factory()->for($shop)->create();
        $this->itemFor($order, $flowers, 'Bukiet');
        $this->itemFor($order, $pot, 'Doniczka');

        $this->assertTrue($order->fresh()->hasWithdrawableItems());
    }

    public function test_item_without_a_product_counts_as_withdrawable(): void
    {
        $order = Order::factory()->create();
        $this->itemFor($order, null, 'Produkt skasowany z katalogu');

        // Przy niepewności rozstrzygamy na korzyść konsumenta.
        $this->assertTrue($order->fresh()->hasWithdrawableItems());
    }

    public function test_deadline_runs_from_completion_plus_delivery_buffer(): void
    {
        config(['legal.withdrawal.days' => 14, 'legal.withdrawal.delivery_buffer_days' => 4]);

        $order = Order::factory()->create(['created_at' => Carbon::parse('2026-08-01 10:00')]);
        $order->statusEvents()->create([
            'from_status' => OrderStatus::Processing,
            'to_status' => OrderStatus::Completed,
        ]);
        $order->statusEvents()->first()->forceFill(['created_at' => Carbon::parse('2026-08-10 12:00')])->save();

        $deadline = $order->fresh()->withdrawalDeadline();

        // 10.08 + 14 dni + 4 dni zapasu na dostawę, do końca dnia.
        $this->assertSame('2026-08-28 23:59:59', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_deadline_falls_back_to_order_date_without_completion(): void
    {
        config(['legal.withdrawal.days' => 14, 'legal.withdrawal.delivery_buffer_days' => 4]);

        $order = Order::factory()->create(['created_at' => Carbon::parse('2026-08-01 10:00')]);

        $this->assertSame('2026-08-19 23:59:59', $order->withdrawalDeadline()->format('Y-m-d H:i:s'));
        $this->assertTrue($order->withinWithdrawalWindow());
    }

    public function test_window_closes_after_the_deadline(): void
    {
        $order = Order::factory()->create(['created_at' => now()->subMonths(2)]);

        $this->assertFalse($order->fresh()->withinWithdrawalWindow());
    }

    public function test_confirmation_email_carries_the_withdrawal_notice(): void
    {
        $shop = Shop::factory()->create(['contact_email' => 'kontakt@sklep.test']);
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $order = Order::factory()->for($shop)->create();
        $this->itemFor($order, $product, 'Doniczka');

        app(OrderMailer::class)->confirmToCustomer($order->fresh());

        $mail = EmailMessage::latest('id')->first();
        $text = implode(' ', array_merge(...array_map(fn ($b) => (array) $b, $mail->intro_lines)));

        $this->assertStringContainsString('Prawo odstąpienia od umowy', $text);
        $this->assertStringContainsString('14 dni', $text);
        $this->assertStringContainsString('kontakt@sklep.test', $text);
        $this->assertStringContainsString('Wzór oświadczenia', $text);
    }

    public function test_notice_names_the_excluded_items_in_a_mixed_order(): void
    {
        $shop = Shop::factory()->create();
        $flowers = Product::factory()->create(['shop_id' => $shop->id, 'withdrawal_excluded' => true]);
        $pot = Product::factory()->create(['shop_id' => $shop->id]);
        $order = Order::factory()->for($shop)->create();
        $this->itemFor($order, $flowers, 'Bukiet z żywych kwiatów');
        $this->itemFor($order, $pot, 'Doniczka ceramiczna');

        app(OrderMailer::class)->confirmToCustomer($order->fresh());

        $mail = EmailMessage::latest('id')->first();
        $text = implode(' ', array_merge(...array_map(fn ($b) => (array) $b, $mail->intro_lines)));

        $this->assertStringContainsString('Prawo odstąpienia nie obejmuje', $text);
        $this->assertStringContainsString('Bukiet z żywych kwiatów', $text);
        $this->assertStringNotContainsString('Doniczka ceramiczna** — ', $text);
    }

    public function test_order_of_excluded_goods_only_gets_no_notice(): void
    {
        $shop = Shop::factory()->create();
        $flowers = Product::factory()->create(['shop_id' => $shop->id, 'withdrawal_excluded' => true]);
        $order = Order::factory()->for($shop)->create();
        $this->itemFor($order, $flowers, 'Bukiet');

        app(OrderMailer::class)->confirmToCustomer($order->fresh());

        $mail = EmailMessage::latest('id')->first();
        $text = implode(' ', array_merge(...array_map(fn ($b) => (array) $b, $mail->intro_lines)));

        // Informowanie o prawie, które nie przysługuje, wprowadzałoby w błąd.
        $this->assertStringNotContainsString('Prawo odstąpienia', $text);
    }
}
