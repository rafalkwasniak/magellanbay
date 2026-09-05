<?php

namespace Tests\Feature\Storefront;

use App\Enums\SaleUnit;
use App\Livewire\AddToCart;
use App\Livewire\Cart;
use App\Livewire\CartCounter;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Komponenty Livewire koszyka: przycisk „Do koszyka", licznik w nagłówku i
 * strona koszyka. Weryfikują reaktywność (zdarzenie cart-updated) i akcje.
 */
class CartLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function product(Shop $shop, array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'is_active' => true,
            'track_stock' => false,
            'stock' => null,
            'price_gross' => 10.00,
        ], $attrs));
    }

    public function test_add_to_cart_button_adds_and_broadcasts(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop);

        Livewire::test(AddToCart::class, ['product' => $product])
            ->assertSee('Do koszyka')
            ->call('add')
            ->assertDispatched('cart-updated');

        $this->assertSame(1, app(CartService::class)->count($shop->id));
    }

    public function test_add_to_cart_shows_available_stock(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop, ['track_stock' => true, 'stock' => 7]);

        Livewire::test(AddToCart::class, ['product' => $product])
            ->assertSee('Dostępne:')
            ->assertSee('7');
    }

    public function test_add_to_cart_unavailable_when_out_of_stock(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop, ['track_stock' => true, 'stock' => 0]);

        Livewire::test(AddToCart::class, ['product' => $product])
            ->assertDontSee('Do koszyka')
            ->assertSee('Chwilowo niedostępny');
    }

    public function test_add_to_cart_blocks_once_all_stock_is_in_cart(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop, ['track_stock' => true, 'stock' => 1]);

        Livewire::test(AddToCart::class, ['product' => $product])
            ->call('add')
            ->assertDontSee('Do koszyka')
            ->assertSee('Masz w koszyku wszystko, co dostępne');

        // Kolejne próby nie zwiększają ponad stan.
        $this->assertSame(1, app(CartService::class)->count($shop->id));
    }

    public function test_counter_is_hidden_when_cart_is_empty(): void
    {
        $shop = Shop::factory()->sellable()->create();

        // Pusty koszyk → licznik nic nie renderuje (nie zaśmieca winiety).
        Livewire::test(CartCounter::class, ['shopId' => $shop->id])
            ->assertDontSee('Koszyk')
            // …a po dodaniu pozycji pojawia się na zdarzenie cart-updated.
            ->tap(fn () => app(CartService::class)->add($this->product($shop), 1))
            ->dispatch('cart-updated')
            ->assertSee('Koszyk');
    }

    public function test_counter_reflects_cart_and_refreshes_on_event(): void
    {
        $shop = Shop::factory()->sellable()->create();
        // Licznik pokazuje liczbę POZYCJI — dwa różne produkty → „2".
        app(CartService::class)->add($this->product($shop), 3);
        app(CartService::class)->add($this->product($shop), 1);

        Livewire::test(CartCounter::class, ['shopId' => $shop->id])
            ->assertSee('2')
            ->dispatch('cart-updated')
            ->assertSee('2');
    }

    public function test_listing_sells_and_home_single_product_does_not(): void
    {
        $shop = Shop::factory()->sellable()->active()->create();
        $this->product($shop);
        $base = 'http://'.$shop->slug.'.'.config('tenancy.central_domain');

        // Wykaz sprzedaje — komponent add-to-cart na kaflach.
        $this->get($base.'/produkty')->assertOk()->assertSeeLivewire(AddToCart::class);

        // Strona główna (widok 1 produktu) NIE sprzedaje — zamiast koszyka
        // zachęta „Pokaż produkt" prowadząca do karty produktu.
        $this->get($base.'/')->assertOk()
            ->assertDontSeeLivewire(AddToCart::class)
            ->assertSee('Pokaż produkt');
    }

    public function test_cart_page_renders_with_livewire_component(): void
    {
        $shop = Shop::factory()->sellable()->active()->create();

        $this->get('http://'.$shop->slug.'.'.config('tenancy.central_domain').'/koszyk')
            ->assertOk()
            ->assertSee('Koszyk')
            ->assertSeeLivewire(Cart::class);
    }

    public function test_cart_page_increments_decrements_and_removes(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop, ['price_gross' => 10.00]);
        app(CartService::class)->add($product, 1);

        $component = Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->assertSee($product->name)
            ->call('increment', $this->cartKey($product));

        $this->assertSame(2.0, app(CartService::class)->quantityOf($shop->id, $this->cartKey($product)));

        $component->call('decrement', $this->cartKey($product));
        $this->assertSame(1.0, app(CartService::class)->quantityOf($shop->id, $this->cartKey($product)));

        $component->call('remove', $this->cartKey($product))
            ->assertDispatched('cart-updated')
            ->assertSee('Twój koszyk jest pusty');

        $this->assertSame(0, app(CartService::class)->count($shop->id));
    }

    public function test_single_quantity_line_shows_trash_not_minus(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop);
        app(CartService::class)->add($product, 1);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->assertSee('Usuń z koszyka')          // lewy przycisk = kosz
            ->assertDontSee('Zmniejsz ilość')      // brak „−" przy 1 szt.
            ->call('increment', $this->cartKey($product))
            ->assertSee('Zmniejsz ilość')          // od 2 szt. pojawia się „−"
            ->assertDontSee('Usuń z koszyka');
    }

    public function test_decrement_at_one_does_not_remove(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop);
        app(CartService::class)->add($product, 1);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->call('decrement', $this->cartKey($product))
            ->assertSee($product->name);

        $this->assertSame(1, app(CartService::class)->count($shop->id));
    }

    public function test_increment_never_exceeds_stock(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop, ['track_stock' => true, 'stock' => 2]);
        app(CartService::class)->add($product, 2);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->call('increment', $this->cartKey($product))
            ->assertSee('maks. 2 szt.');

        $this->assertSame(2.0, app(CartService::class)->quantityOf($shop->id, $this->cartKey($product)));
    }

    public function test_weight_stepper_moves_by_half_kilo(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop, ['sale_unit' => SaleUnit::Weight, 'track_stock' => false, 'stock' => null]);
        app(CartService::class)->add($product, 1.0);   // 1,00 kg

        $component = Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->call('increment', $this->cartKey($product));
        $this->assertSame(1.5, app(CartService::class)->quantityOf($shop->id, $this->cartKey($product)));

        $component->call('decrement', $this->cartKey($product));
        $this->assertSame(1.0, app(CartService::class)->quantityOf($shop->id, $this->cartKey($product)));
    }

    public function test_weight_quantity_can_be_typed_by_hand(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop, ['sale_unit' => SaleUnit::Weight, 'track_stock' => false, 'stock' => null]);
        app(CartService::class)->add($product, 0.5);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->call('updateQuantity', $this->cartKey($product), '1,20');

        $this->assertSame(1.2, app(CartService::class)->quantityOf($shop->id, $this->cartKey($product)));
    }

    public function test_weight_decrement_floors_at_half_kilo_and_shows_trash(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop, ['sale_unit' => SaleUnit::Weight, 'track_stock' => false, 'stock' => null]);
        app(CartService::class)->add($product, 0.5);   // już na minimum

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->assertSee('Usuń z koszyka')          // przy 0,5 kg lewy przycisk = kosz
            ->assertDontSee('Zmniejsz ilość')
            ->call('decrement', $this->cartKey($product));      // nie schodzi poniżej minimum

        $this->assertSame(0.5, app(CartService::class)->quantityOf($shop->id, $this->cartKey($product)));
    }

    public function test_cart_page_shows_banner_when_stock_dropped(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop, ['name' => 'Storczyk', 'track_stock' => true, 'stock' => 9]);
        app(CartService::class)->add($product, 8);

        $product->update(['stock' => 3]);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->assertSee('Zaktualizowaliśmy Twój koszyk')
            ->assertSee('dostosowana do dostępności');
    }

    public function test_cart_page_no_banner_when_nothing_changed(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = $this->product($shop, ['track_stock' => true, 'stock' => 10]);
        app(CartService::class)->add($product, 2);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->assertDontSee('Zaktualizowaliśmy Twój koszyk');
    }
}
