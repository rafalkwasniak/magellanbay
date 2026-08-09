<?php

namespace Tests\Feature\Discount;

use App\Enums\VatRate;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\DiscountResolver;
use App\Services\OrderTotals;
use App\Support\DiscountAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Silnik kodów rabatowych: sprawdzenie kodu dla konkretnego koszyka i rozbicie
 * rabatu na pozycje (a przez to na stawki VAT). Bez UI — tu pilnujemy reguł i
 * groszy, na których stanie koszyk, kasa i faktura.
 */
class DiscountEngineTest extends TestCase
{
    use RefreshDatabase;

    private function cartWith(Shop $shop, Product ...$products): \Illuminate\Support\Collection
    {
        $cart = app(CartService::class);
        $cart->clear($shop->id);

        foreach ($products as $product) {
            // Fabryka losuje stan 0–50, a przy zerze koszyk słusznie wyrzuca
            // pozycję — tu badamy rabaty, nie dostępność, więc stan jest pewny.
            $product->forceFill(['stock' => 10])->save();
            $cart->add($product, 1);
        }

        return $cart->lines($shop->id);
    }

    public function test_percent_code_takes_its_share_of_the_cart(): void
    {
        $shop = Shop::factory()->sellable()->withDiscountCodes()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'price_gross' => 200]);
        $code = DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10', 'value' => 10]);

        $result = app(DiscountResolver::class)->resolve($shop, 'lato10', $this->cartWith($shop, $product));

        $this->assertTrue($result->accepted());
        $this->assertSame(20.0, $result->itemsDiscount);
        $this->assertFalse($result->freeShipping);
        $this->assertSame($code->id, $result->code->id);
    }

    public function test_amount_code_never_exceeds_the_cart(): void
    {
        $shop = Shop::factory()->sellable()->withDiscountCodes()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'price_gross' => 30]);
        DiscountCode::factory()->amount(50)->create(['shop_id' => $shop->id, 'code' => 'ZANADTO']);

        $result = app(DiscountResolver::class)->resolve($shop, 'ZANADTO', $this->cartWith($shop, $product));

        $this->assertTrue($result->accepted());
        $this->assertSame(30.0, $result->itemsDiscount);
    }

    public function test_free_shipping_code_leaves_products_alone(): void
    {
        $shop = Shop::factory()->sellable()->withDiscountCodes()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'price_gross' => 100]);
        DiscountCode::factory()->freeShipping()->create(['shop_id' => $shop->id, 'code' => 'DARMOWA']);

        $result = app(DiscountResolver::class)->resolve($shop, 'DARMOWA', $this->cartWith($shop, $product));

        $this->assertTrue($result->accepted());
        $this->assertSame(0.0, $result->itemsDiscount);
        $this->assertTrue($result->freeShipping);
    }

    public function test_product_code_counts_only_its_own_line(): void
    {
        $shop = Shop::factory()->sellable()->withDiscountCodes()->create();
        $target = Product::factory()->create(['shop_id' => $shop->id, 'price_gross' => 100]);
        $other = Product::factory()->create(['shop_id' => $shop->id, 'price_gross' => 400]);
        DiscountCode::factory()->forProduct($target)->create(['code' => 'NATEN', 'value' => 10]);

        $result = app(DiscountResolver::class)->resolve($shop, 'NATEN', $this->cartWith($shop, $target, $other));

        // 10% ze 100 zł (tej pozycji), nie z 500 zł całego koszyka.
        $this->assertSame(10.0, $result->itemsDiscount);
    }

    public function test_product_code_is_refused_when_its_product_is_missing(): void
    {
        $shop = Shop::factory()->sellable()->withDiscountCodes()->create();
        $target = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Bukiet wiosenny']);
        $other = Product::factory()->create(['shop_id' => $shop->id]);
        DiscountCode::factory()->forProduct($target)->create(['code' => 'NATEN']);

        $result = app(DiscountResolver::class)->resolve($shop, 'NATEN', $this->cartWith($shop, $other));

        $this->assertFalse($result->accepted());
        $this->assertStringContainsString('Bukiet wiosenny', $result->error);
    }

    public function test_threshold_is_measured_without_shipping(): void
    {
        $shop = Shop::factory()->sellable()->withDiscountCodes()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'price_gross' => 150]);
        DiscountCode::factory()->minimum(200)->create(['shop_id' => $shop->id, 'code' => 'ODDWUSTU']);

        $result = app(DiscountResolver::class)->resolve($shop, 'ODDWUSTU', $this->cartWith($shop, $product));

        $this->assertFalse($result->accepted());
        $this->assertStringContainsString('200,00 zł', $result->error);
    }

    public function test_states_are_explained_in_the_customers_language(): void
    {
        $shop = Shop::factory()->sellable()->withDiscountCodes()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $lines = $this->cartWith($shop, $product);
        $resolver = app(DiscountResolver::class);

        DiscountCode::factory()->expired()->create(['shop_id' => $shop->id, 'code' => 'POTERMINIE']);
        DiscountCode::factory()->scheduled()->create(['shop_id' => $shop->id, 'code' => 'ZAWCZESNIE']);
        DiscountCode::factory()->inactive()->create(['shop_id' => $shop->id, 'code' => 'WYLACZONY']);

        $this->assertSame('Ten kod stracił ważność.', $resolver->resolve($shop, 'POTERMINIE', $lines)->error);
        $this->assertSame('Ten kod jeszcze nie obowiązuje.', $resolver->resolve($shop, 'ZAWCZESNIE', $lines)->error);
        $this->assertSame('Ten kod jest nieaktywny.', $resolver->resolve($shop, 'WYLACZONY', $lines)->error);
        $this->assertSame('Nie znamy takiego kodu.', $resolver->resolve($shop, 'NIEMATAKIEGO', $lines)->error);
    }

    public function test_exhausted_code_is_refused(): void
    {
        $shop = Shop::factory()->sellable()->withDiscountCodes()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $code = DiscountCode::factory()->limitedTo(1)->create(['shop_id' => $shop->id, 'code' => 'JEDNORAZ']);
        Order::factory()->create(['shop_id' => $shop->id, 'discount_code_id' => $code->id]);

        $result = app(DiscountResolver::class)->resolve($shop, 'JEDNORAZ', $this->cartWith($shop, $product));

        $this->assertSame('Ten kod został już wykorzystany.', $result->error);
    }

    public function test_personal_code_needs_its_owner_logged_in(): void
    {
        $shop = Shop::factory()->sellable()->withDiscountCodes()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $owner = Customer::factory()->create(['shop_id' => $shop->id]);
        $someoneElse = Customer::factory()->create(['shop_id' => $shop->id]);
        DiscountCode::factory()->forCustomer($owner)->create(['code' => 'IMIENNY']);

        $lines = $this->cartWith($shop, $product);
        $resolver = app(DiscountResolver::class);

        $this->assertStringContainsString('zaloguj się', $resolver->resolve($shop, 'IMIENNY', $lines)->error);
        $this->assertStringContainsString('innemu klientowi', $resolver->resolve($shop, 'IMIENNY', $lines, $someoneElse)->error);
        $this->assertTrue($resolver->resolve($shop, 'IMIENNY', $lines, $owner)->accepted());
    }

    public function test_code_of_another_shop_is_unknown_here(): void
    {
        $shop = Shop::factory()->sellable()->withDiscountCodes()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        DiscountCode::factory()->create(['code' => 'OBCY']);

        $result = app(DiscountResolver::class)->resolve($shop, 'OBCY', $this->cartWith($shop, $product));

        $this->assertSame('Nie znamy takiego kodu.', $result->error);
    }

    public function test_allocation_splits_to_the_last_grosz(): void
    {
        // 10 zł na trzy równe linie: 3,34 + 3,33 + 3,33 — nie 9,99. Przy równych
        // resztach grosz idzie do pierwszej pozycji (sort stabilny = wynik powtarzalny).
        $shares = DiscountAllocation::spread(10.0, [100.0, 100.0, 100.0]);

        $this->assertSame(10.0, round(array_sum($shares), 2));
        $this->assertSame([3.34, 3.33, 3.33], array_values(array_map(fn ($v) => round($v, 2), $shares)));
    }

    public function test_allocation_is_proportional_to_line_value(): void
    {
        $shares = DiscountAllocation::spread(30.0, [300.0, 100.0]);

        $this->assertSame([22.5, 7.5], array_values($shares));
    }

    public function test_allocation_of_nothing_gives_nothing(): void
    {
        $this->assertSame([0.0, 0.0], array_values(DiscountAllocation::spread(0.0, [10.0, 20.0])));
        $this->assertSame([0.0], array_values(DiscountAllocation::spread(5.0, [0.0])));
    }

    public function test_order_totals_split_the_discount_across_vat_rates(): void
    {
        $order = Order::factory()->create(['delivery_cost' => 15, 'discount_amount' => 100]);
        // Dwie stawki w jednym koszyku — sedno problemu: rabat odjęty dopiero od
        // sumy dałby zły podział VAT na fakturze.
        $order->items()->create([
            'name' => 'Towar 23%', 'unit_price_gross' => 300, 'vat_rate' => VatRate::R23->value,
            'quantity' => 1, 'line_total_gross' => 300,
        ]);
        $order->items()->create([
            'name' => 'Towar 5%', 'unit_price_gross' => 100, 'vat_rate' => VatRate::R5->value,
            'quantity' => 1, 'line_total_gross' => 100,
        ]);

        app(OrderTotals::class)->recalculate($order->load('items'));
        $order->refresh();

        // Rabat 100 zł z 400 zł produktów: 75 zł z linii 23%, 25 zł z linii 5%.
        $this->assertSame(400.00, (float) $order->items_total);
        $this->assertSame(100.00, (float) $order->discount_amount);
        $this->assertSame(315.00, (float) $order->total_gross);   // 400 − 100 + 15 dostawy

        $expectedNet = round(225 / 1.23, 2) + round(75 / 1.05, 2);
        $this->assertSame(round($expectedNet, 2), (float) $order->total_net);
        $this->assertSame(round(300 - $expectedNet, 2), (float) $order->total_vat);
    }

    public function test_discount_cannot_outgrow_the_items_after_an_edit(): void
    {
        $order = Order::factory()->create(['delivery_cost' => 20, 'discount_amount' => 500]);
        $order->items()->create([
            'name' => 'Doniczka', 'unit_price_gross' => 100, 'vat_rate' => VatRate::R23->value,
            'quantity' => 1, 'line_total_gross' => 100,
        ]);

        app(OrderTotals::class)->recalculate($order->load('items'));
        $order->refresh();

        // Rabat przycięty do wartości produktów: zamówienie schodzi do samej dostawy.
        $this->assertSame(100.00, (float) $order->discount_amount);
        $this->assertSame(20.00, (float) $order->total_gross);
        $this->assertSame(0.00, (float) $order->total_net);
    }

    public function test_order_without_a_discount_counts_exactly_as_before(): void
    {
        $order = Order::factory()->create(['delivery_cost' => 10]);
        $order->items()->create([
            'name' => 'Doniczka', 'unit_price_gross' => 123.45, 'vat_rate' => VatRate::R23->value,
            'quantity' => 2, 'line_total_gross' => 246.90,
        ]);

        app(OrderTotals::class)->recalculate($order->load('items'));
        $order->refresh();

        $this->assertSame(246.90, (float) $order->items_total);
        $this->assertSame(0.00, (float) $order->discount_amount);
        $this->assertSame(256.90, (float) $order->total_gross);
        $this->assertSame(round(246.90 / 1.23, 2), (float) $order->total_net);
    }
}
