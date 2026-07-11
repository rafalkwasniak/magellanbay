<?php

namespace Tests\Feature\Seller;

use App\Livewire\Seller\OrderEditor;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tryb edycji zamówienia w panelu (komponent OrderEditor): zmiana ilości/ceny,
 * usunięcie pozycji i dodanie produktu zapisują się od razu i przeliczają sumy;
 * błąd stanu wraca jako komunikat; sprzedawca rusza wyłącznie własne zamówienia.
 * Głęboka logika stanu i kwot ma własny zestaw (OrderEditorTest) — tu wiring UI.
 */
class OrderEditComponentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);

        return [$seller, $shop];
    }

    private function itemFor(Shop $shop, Product $product, float $quantity): OrderItem
    {
        $order = Order::factory()->for($shop)->create();

        return $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price_gross' => (float) $product->price_gross,
            'vat_rate' => $product->vat_rate->value,
            'quantity' => $quantity,
            'sale_unit' => $product->sale_unit->value,
            'line_total_gross' => round((float) $product->price_gross * $quantity, 2),
        ]);
    }

    public function test_seller_changes_quantity_and_totals_refresh(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'track_stock' => true, 'stock' => 1, 'price_gross' => 12.00]);
        $item = $this->itemFor($shop, $product, 4);

        Livewire::actingAs($seller)
            ->test(OrderEditor::class, ['order' => $item->order])
            ->call('setQuantity', $item->id, '5')
            ->assertOk()
            ->assertSet('errorMessage', null);

        $this->assertSame('5.00', $item->fresh()->quantity);
        $this->assertSame('0.00', $product->fresh()->stock);
        $this->assertSame('60.00', $item->order->fresh()->total_gross);
    }

    public function test_over_stock_surfaces_error_and_keeps_item(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'track_stock' => true, 'stock' => 1, 'price_gross' => 12.00]);
        $item = $this->itemFor($shop, $product, 4);

        Livewire::actingAs($seller)
            ->test(OrderEditor::class, ['order' => $item->order])
            ->call('toggleEditing')                 // linia błędu renderuje się w trybie edycji
            ->call('setQuantity', $item->id, '6')   // sufit to 5
            ->assertOk()
            ->assertSee('Dostępny stan');           // komunikat w miejscu podpowiedzi (nie górny baner)

        $this->assertSame('4.00', $item->fresh()->quantity);
    }

    public function test_seller_changes_price(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'track_stock' => false, 'stock' => null, 'price_gross' => 12.00]);
        $item = $this->itemFor($shop, $product, 4);

        Livewire::actingAs($seller)
            ->test(OrderEditor::class, ['order' => $item->order])
            ->call('setPrice', $item->id, '11,00');

        $this->assertSame('11.00', $item->fresh()->unit_price_gross);
        $this->assertSame('44.00', $item->order->fresh()->total_gross);
    }

    public function test_seller_removes_item_and_stock_returns(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'track_stock' => true, 'stock' => 1, 'price_gross' => 12.00]);
        $item = $this->itemFor($shop, $product, 4);

        Livewire::actingAs($seller)
            ->test(OrderEditor::class, ['order' => $item->order])
            ->call('removeItem', $item->id);

        $this->assertNull($item->fresh());
        $this->assertSame('5.00', $product->fresh()->stock);
    }

    public function test_seller_adds_product(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $first = Product::factory()->create(['shop_id' => $shop->id, 'track_stock' => false, 'stock' => null, 'price_gross' => 10.00]);
        $item = $this->itemFor($shop, $first, 1);
        $added = Product::factory()->create(['shop_id' => $shop->id, 'track_stock' => true, 'stock' => 3, 'price_gross' => 20.00]);

        Livewire::actingAs($seller)
            ->test(OrderEditor::class, ['order' => $item->order])
            ->set('addProductId', (string) $added->id)
            ->set('addQuantity', '2')
            ->call('addProduct')
            ->assertSet('addProductId', '')
            ->assertSet('addQuantity', '');

        $order = $item->order->fresh();
        $this->assertCount(2, $order->items);
        $this->assertSame('1.00', $added->fresh()->stock);
        $this->assertSame('50.00', $order->total_gross);
    }

    public function test_editing_mode_renders_controls_and_product_picker(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'track_stock' => true, 'stock' => 2, 'price_gross' => 15.00]);
        $item = $this->itemFor($shop, $product, 1);
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Inny produkt', 'is_active' => true]);

        Livewire::actingAs($seller)
            ->test(OrderEditor::class, ['order' => $item->order])
            ->assertSet('editing', false)
            ->call('toggleEditing')
            ->assertSet('editing', true)
            ->assertOk()
            ->assertSee('Dodaj produkt')
            ->assertSee('Inny produkt')
            ->assertSee('dostępne max');   // podpowiedź sufitu stanu
    }

    public function test_seller_cannot_edit_foreign_order(): void
    {
        [$seller] = $this->sellerWithShop();
        $foreign = Product::factory()->create(['track_stock' => true, 'stock' => 5, 'price_gross' => 10.00]);
        $item = $this->itemFor($foreign->shop, $foreign, 2);

        Livewire::actingAs($seller)
            ->test(OrderEditor::class, ['order' => $item->order])
            ->call('setQuantity', $item->id, '3')
            ->assertForbidden();

        $this->assertSame('2.00', $item->fresh()->quantity);
    }
}
