<?php

namespace Tests\Feature\Legal;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Publiczny formularz odstąpienia od umowy (`/zwrot/{token}`): dostęp po tokenie
 * bez logowania, scope do sklepu z subdomeny, przyjęcie oświadczenia i bramki —
 * termin, anulowane zamówienie, wyjątek art. 38.
 */
class OrderReturnFormTest extends TestCase
{
    use RefreshDatabase;

    private function base(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    /**
     * @param  array<string, mixed>  $orderAttributes
     */
    private function orderWithItem(array $orderAttributes = [], ?Product $product = null): OrderItem
    {
        $product ??= Product::factory()->create(['price_gross' => 50.00, 'vat_rate' => '23']);

        $order = Order::factory()->create([
            'shop_id' => $product->shop_id,
            'buyer_name' => 'Anna',
            'buyer_surname' => 'Kowalska',
            'ship_street' => 'Polna',
            'ship_building_number' => '7',
            'ship_postal_code' => '00-001',
            'ship_city' => 'Warszawa',
            ...$orderAttributes,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price_gross' => 50.00,
            'vat_rate' => '23',
            'quantity' => 3,
            'sale_unit' => $product->sale_unit->value,
            'line_total_gross' => 150.00,
        ]);

        app(\App\Services\OrderTotals::class)->recalculate($order->load('items'));

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(OrderItem $item, float $quantity = 1): array
    {
        return [
            'customer_name' => 'Anna Kowalska',
            'customer_address' => 'ul. Polna 7, 00-001 Warszawa',
            'quantities' => [$item->id => $quantity],
        ];
    }

    public function test_form_opens_with_a_valid_token_and_prefills_buyer_data(): void
    {
        $item = $this->orderWithItem();
        $shop = $item->order->shop;

        $this->get($this->base($shop).'/zwrot/'.$item->order->paymentToken())
            ->assertOk()
            ->assertSee('Zwrot z zamówienia #'.$item->order->number)
            ->assertSee($item->name)
            ->assertSee('Anna Kowalska')
            ->assertSee('Polna 7, 00-001 Warszawa');
    }

    public function test_broken_token_redirects_home(): void
    {
        $shop = Shop::factory()->create();

        $this->get($this->base($shop).'/zwrot/nonsens')->assertRedirect('/');
    }

    public function test_token_of_another_shop_does_not_reach_the_order(): void
    {
        $item = $this->orderWithItem();
        $other = Shop::factory()->create();

        $this->get($this->base($other).'/zwrot/'.$item->order->paymentToken())->assertRedirect('/');
    }

    public function test_submitting_the_declaration_registers_the_return(): void
    {
        $item = $this->orderWithItem();
        $shop = $item->order->shop;
        $token = $item->order->paymentToken();

        $this->post($this->base($shop).'/zwrot/'.$token, $this->payload($item, 2))
            ->assertRedirect($this->base($shop).'/zwrot/'.$token)
            ->assertSessionHas('status');

        $this->assertSame('2.00', $item->fresh()->returned_quantity);
        $this->assertSame('50.00', $item->order->fresh()->total_gross);

        $return = $item->order->returns()->first();
        $this->assertSame('Anna Kowalska', $return->customer_name);
        $this->assertSame('100.00', $return->refund_gross);
    }

    public function test_declaration_without_a_name_is_rejected(): void
    {
        $item = $this->orderWithItem();
        $shop = $item->order->shop;

        $this->post($this->base($shop).'/zwrot/'.$item->order->paymentToken(), [
            'customer_address' => 'ul. Polna 7',
            'quantities' => [$item->id => 1],
        ])->assertSessionHasErrors('customer_name');

        $this->assertSame('0.00', $item->fresh()->returned_quantity);
    }

    public function test_empty_selection_comes_back_with_a_message(): void
    {
        $item = $this->orderWithItem();
        $shop = $item->order->shop;

        $this->post($this->base($shop).'/zwrot/'.$item->order->paymentToken(), $this->payload($item, 0))
            ->assertSessionHas('error');

        $this->assertSame('0.00', $item->fresh()->returned_quantity);
    }

    public function test_after_the_deadline_the_form_is_closed(): void
    {
        $days = (int) config('legal.withdrawal.days') + (int) config('legal.withdrawal.delivery_buffer_days');
        $item = $this->orderWithItem(['created_at' => now()->subDays($days + 1)]);
        $shop = $item->order->shop;
        // Token nie jest deterministyczny (szyfrowanie z losowym IV), więc do
        // porównania adresu przekierowania trzeba użyć tego samego ciągu.
        $token = $item->order->paymentToken();

        $this->get($this->base($shop).'/zwrot/'.$token)
            ->assertOk()
            ->assertSee('Termin na odstąpienie minął')
            ->assertDontSee('Wyślij oświadczenie o odstąpieniu');

        $this->post($this->base($shop).'/zwrot/'.$token, $this->payload($item))
            ->assertRedirect($this->base($shop).'/zwrot/'.$token);

        $this->assertSame('0.00', $item->fresh()->returned_quantity);
    }

    public function test_cancelled_order_shows_no_form(): void
    {
        $item = $this->orderWithItem(['status' => OrderStatus::Cancelled]);
        $shop = $item->order->shop;

        $this->get($this->base($shop).'/zwrot/'.$item->order->paymentToken())
            ->assertOk()
            ->assertSee('Zamówienie zostało anulowane')
            ->assertDontSee('Wyślij oświadczenie o odstąpieniu');
    }

    public function test_item_excluded_by_article_38_has_no_input(): void
    {
        $product = Product::factory()->create(['withdrawal_excluded' => true, 'price_gross' => 50.00, 'vat_rate' => '23']);
        $item = $this->orderWithItem(product: $product);
        $shop = $item->order->shop;

        $this->get($this->base($shop).'/zwrot/'.$item->order->paymentToken())
            ->assertOk()
            ->assertSee('nie podlega zwrotowi', false)
            ->assertDontSee('name="quantities['.$item->id.']"', false);
    }

    public function test_already_reported_returns_are_listed(): void
    {
        $item = $this->orderWithItem();
        $shop = $item->order->shop;
        $token = $item->order->paymentToken();

        $this->post($this->base($shop).'/zwrot/'.$token, $this->payload($item, 1));

        $this->get($this->base($shop).'/zwrot/'.$token)
            ->assertOk()
            ->assertSee('Zgłoszone zwroty')
            ->assertSee('czeka na rozliczenie');
    }
}
