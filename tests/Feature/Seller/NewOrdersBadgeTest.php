<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Badge „nowe zamówienia": powiadomienie „coś wpadło, odkąd nie zaglądałeś".
 * +1 przy złożeniu zamówienia, zero przy wejściu na listę Zamówień. Licznik na
 * sklepie (kolumna), widoczny w nawigacji panelu i jako kafelek na Pulpicie.
 */
class NewOrdersBadgeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(array $shopAttributes = []): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(array_merge([
            'owner_id' => $seller->id,
            'street' => 'Kwiatowa', 'building_number' => '5', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'province' => 'mazowieckie',
            'pickup_enabled' => true, 'pay_on_pickup_enabled' => true,
        ], $shopAttributes));

        return [$seller, $shop];
    }

    private function placeOrder(Shop $shop): void
    {
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true,
            'track_stock' => false, 'price_gross' => 100.00, 'vat_rate' => '23',
        ]);

        app(CartService::class)->add($product, 1);

        app(OrderService::class)->place($shop, [
            'buyer_name' => 'Jan',
            'buyer_surname' => 'Kowalski',
            'buyer_email' => 'jan@example.com',
            'buyer_phone' => '123456789',
            'is_company' => false,
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
            'note' => null,
        ]);
    }

    public function test_placing_order_increments_unseen_counter(): void
    {
        [, $shop] = $this->sellerWithShop();

        $this->assertSame(0, $shop->fresh()->unseen_orders_count);

        $this->placeOrder($shop);
        $this->placeOrder($shop);

        $this->assertSame(2, $shop->fresh()->unseen_orders_count);
    }

    public function test_visiting_orders_list_resets_counter(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $shop->forceFill(['unseen_orders_count' => 3])->save();

        $this->actingAs($seller)
            ->get(route('seller.orders.index'))
            ->assertOk();

        $this->assertSame(0, $shop->fresh()->unseen_orders_count);
    }

    public function test_badge_shows_in_navigation_when_unseen_present(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $shop->forceFill(['unseen_orders_count' => 5])->save();

        // Pulpit renderuje nawigację panelu, ale nie zeruje licznika.
        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Nowe zamówienia');

        $this->assertSame(5, $shop->fresh()->unseen_orders_count);
    }

    public function test_badge_caps_at_nine_plus(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $shop->forceFill(['unseen_orders_count' => 12])->save();

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('9+');
    }
}
