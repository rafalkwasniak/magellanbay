<?php

namespace Tests\Feature\Storefront;

use App\Livewire\AddToCart;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sklep bez dostawy albo bez płatności nie zaprasza do zakupu, którego nie da
 * się dokończyć: przycisk „Do koszyka" znika z karty produktu i z wykazu, a
 * koszyk odrzuca dodanie także wtedy, gdy ktoś obejdzie widok. Strona zostaje
 * wykazem oferty (sprzedaż np. telefoniczna), a nie zepsutą kasą.
 *
 * Stan wynika WPROST z ustawień i liczy się na żywo — bez flagi w bazie i bez
 * deklaracji sprzedawcy. Sprzedawca odznacza metodę i przyciski znikają, włącza
 * — wracają. Dlatego testy ustawiają fiszki, a nie żaden tryb.
 */
class OrderAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    private function product(Shop $shop): Product
    {
        return Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true]);
    }

    public function test_shop_without_delivery_or_payment_does_not_accept_orders(): void
    {
        $this->assertFalse(Shop::factory()->create()->acceptsOrders());
    }

    public function test_delivery_without_payment_does_not_accept_orders(): void
    {
        // Odbiór osobisty gotowy (adres kompletny), ale nie ma czym zapłacić.
        $shop = Shop::factory()->sellable()->create(['pay_on_pickup_enabled' => false]);

        $this->assertNotSame([], $shop->availableDeliveryMethods());
        $this->assertSame([], $shop->availablePaymentMethods());
        $this->assertFalse($shop->acceptsOrders());
    }

    public function test_enabled_pickup_without_address_does_not_count_as_delivery(): void
    {
        // Pułapka z produkcji: sprzedawca zaznaczył odbiór, ale nie uzupełnił
        // adresu sklepu — nie ma dokąd przyjść, więc dostawy realnie nie ma.
        $shop = Shop::factory()->sellable()->create(['street' => null]);

        $this->assertSame([], $shop->availableDeliveryMethods());
        $this->assertFalse($shop->acceptsOrders());
    }

    public function test_shop_with_pickup_and_payment_accepts_orders(): void
    {
        $this->assertTrue(Shop::factory()->sellable()->create()->acceptsOrders());
    }

    public function test_product_page_hides_add_to_cart_when_shop_cannot_sell(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->product($shop);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertDontSeeLivewire(AddToCart::class);
    }

    public function test_listing_hides_add_to_cart_when_shop_cannot_sell(): void
    {
        $shop = Shop::factory()->active()->create();
        $this->product($shop);

        $this->get($this->host($shop).'/produkty')
            ->assertOk()
            ->assertDontSeeLivewire(AddToCart::class);
    }

    public function test_button_comes_back_when_seller_completes_the_settings(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->product($shop);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertDontSeeLivewire(AddToCart::class);

        // Sprzedawca uzupełnia ustawienia — nic nie przelicza się „w tle".
        $shop->forceFill([
            'street' => 'Kwiatowa',
            'building_number' => '12',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'province' => config('shop.provinces')[0],
            'pickup_enabled' => true,
            'pay_on_pickup_enabled' => true,
        ])->save();

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertSeeLivewire(AddToCart::class);
    }

    public function test_button_disappears_when_seller_switches_the_last_method_off(): void
    {
        $shop = Shop::factory()->active()->sellable()->create();
        $product = $this->product($shop);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertSeeLivewire(AddToCart::class);

        $shop->forceFill(['pay_on_pickup_enabled' => false])->save();

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertDontSeeLivewire(AddToCart::class);
    }

    public function test_cart_rejects_a_product_from_a_shop_that_cannot_sell(): void
    {
        // Ukrycie przycisku to UX, nie zabezpieczenie — komponent Livewire da się
        // wywołać z palca, więc zapora stoi też w samym koszyku.
        $shop = Shop::factory()->create();
        $product = $this->product($shop);

        app(CartService::class)->add($product);

        $this->assertSame([], app(CartService::class)->raw($shop->id));
    }
}
