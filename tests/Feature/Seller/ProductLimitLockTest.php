<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\ProductLimitLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Miękki zamek limitu produktów po wygaśnięciu abonamentu.
 *
 * Chowa NAJMNIEJ POPULARNE (decyzja Rafała) — bestseller zostaje w sklepie,
 * a leżak schodzi. Produktów ukrytych ręcznie przez sprzedawcę nie tyka ani
 * przy zamykaniu, ani przy przywracaniu: to jego decyzje, nie nasze.
 */
class ProductLimitLockTest extends TestCase
{
    use RefreshDatabase;

    private function lock(): ProductLimitLock
    {
        return app(ProductLimitLock::class);
    }

    /**
     * Sklep na Kramie (limit 24) — do testów podkręcamy limit w snapshocie,
     * żeby nie trzeba było tworzyć dziesiątek produktów.
     */
    private function shopWithLimit(int $limit): Shop
    {
        return Shop::factory()->create([
            'package' => 'stall',
            'entitlements' => array_merge(config('shop.packages.stall.entitlements'), ['max_products' => $limit]),
        ]);
    }

    private function sell(Product $product, float $quantity, array $orderAttributes = []): void
    {
        $order = Order::factory()->create(['shop_id' => $product->shop_id, ...$orderAttributes]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price_gross' => 10,
            'vat_rate' => '23',
            'quantity' => $quantity,
            'sale_unit' => $product->sale_unit->value,
            'line_total_gross' => 10 * $quantity,
        ]);
    }

    public function test_shop_within_the_limit_is_left_alone(): void
    {
        $shop = $this->shopWithLimit(3);
        Product::factory()->count(3)->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->assertSame(0, $this->lock()->enforce($shop));
        $this->assertSame(3, $shop->products()->where('is_active', true)->count());
    }

    public function test_least_popular_products_are_hidden_first(): void
    {
        $shop = $this->shopWithLimit(2);

        $bestseller = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Bestseller']);
        $sredni = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Średni']);
        $lezak = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'name' => 'Leżak']);

        $this->sell($bestseller, 10);
        $this->sell($sredni, 3);
        // Leżak: zero sprzedaży.

        $this->assertSame(1, $this->lock()->enforce($shop));

        // Zostają dwa najlepiej sprzedające się, mimo że leżak jest najnowszy.
        $this->assertFalse($lezak->fresh()->is_active);
        $this->assertNotNull($lezak->fresh()->auto_hidden_at);
        $this->assertTrue($bestseller->fresh()->is_active);
        $this->assertTrue($sredni->fresh()->is_active);
    }

    public function test_oldest_go_first_when_nothing_has_sold(): void
    {
        $shop = $this->shopWithLimit(1);

        $stary = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true]);
        $nowy = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->lock()->enforce($shop);

        // Remis na zerowej sprzedaży rozstrzyga wiek — deterministycznie.
        $this->assertFalse($stary->fresh()->is_active);
        $this->assertTrue($nowy->fresh()->is_active);
    }

    public function test_cancelled_and_returned_sales_do_not_protect_a_product(): void
    {
        $shop = $this->shopWithLimit(1);

        $pozornie = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true]);
        $realnie = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true]);

        // Anulowane zamówienie i zwrot to sprzedaż, która się NIE utrzymała.
        $this->sell($pozornie, 50, ['status' => OrderStatus::Cancelled]);
        $this->sell($realnie, 2);

        $this->lock()->enforce($shop);

        $this->assertFalse($pozornie->fresh()->is_active);
        $this->assertTrue($realnie->fresh()->is_active);
    }

    public function test_manually_hidden_products_are_not_touched(): void
    {
        $shop = $this->shopWithLimit(1);

        // Sprzedawca sam wyłączył produkt (koniec sezonu) — bez `auto_hidden_at`.
        $manual = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => false]);
        Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->lock()->enforce($shop);
        $this->assertNull($manual->fresh()->auto_hidden_at);

        // Po opłacie też nie wraca — to nie my go schowaliśmy.
        $shop->forceFill(['entitlements' => array_merge($shop->entitlements, ['max_products' => 10])])->save();
        $this->lock()->restore($shop->fresh());

        $this->assertFalse($manual->fresh()->is_active);
    }

    public function test_restore_brings_back_the_best_sellers_first(): void
    {
        $shop = $this->shopWithLimit(1);

        $mocny = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true]);
        $sredni = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true]);
        $slaby = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->sell($mocny, 10);
        $this->sell($sredni, 5);

        // Limit 1 → zamek chowa dwa słabsze.
        $this->assertSame(2, $this->lock()->enforce($shop));

        // Po opłacie limit rośnie do 2 — wraca TYLKO jeden, ten mocniejszy.
        $shop->forceFill(['entitlements' => array_merge($shop->entitlements, ['max_products' => 2])])->save();
        $this->assertSame(1, $this->lock()->restore($shop->fresh()));

        $this->assertTrue($sredni->fresh()->is_active);
        $this->assertFalse($slaby->fresh()->is_active);
        // Ten, który został ukryty, zachowuje znacznik — wróci przy większym limicie.
        $this->assertNotNull($slaby->fresh()->auto_hidden_at);
    }

    public function test_restore_is_a_no_op_without_room(): void
    {
        $shop = $this->shopWithLimit(1);
        Product::factory()->count(2)->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->lock()->enforce($shop);

        // Limit się nie zmienił, więc nie ma gdzie przywracać.
        $this->assertSame(0, $this->lock()->restore($shop->fresh()));
    }

    public function test_enforce_is_idempotent(): void
    {
        $shop = $this->shopWithLimit(1);
        Product::factory()->count(3)->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->assertSame(2, $this->lock()->enforce($shop));
        $this->assertSame(0, $this->lock()->enforce($shop->fresh()));
    }
}
