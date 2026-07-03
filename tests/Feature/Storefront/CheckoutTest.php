<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\CompanyLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kasa (Livewire): pokazuje tylko włączone metody, waliduje dane i składa
 * zamówienie gościa (spec „Zakup bez rejestracji").
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function shopReadyForOrders(): Shop
    {
        return Shop::factory()->create([
            'street' => 'Kwiatowa', 'building_number' => '5', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'province' => 'mazowieckie',
            'pickup_enabled' => true, 'pay_on_pickup_enabled' => true,
            'bank_account_number' => '12345678901234567890123456', 'bank_transfer_enabled' => true,
        ]);
    }

    private function cartProduct(Shop $shop): Product
    {
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 40.00,
        ]);
        app(CartService::class)->add($product, 1);

        return $product;
    }

    public function test_checkout_lists_only_enabled_methods(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->assertSee('Odbiór osobisty')
            ->assertSee('Przelew na konto')
            ->assertSee('Płatność przy odbiorze');
    }

    public function test_terms_must_be_accepted(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('accept_terms', false)
            ->call('place')
            ->assertHasErrors('accept_terms');

        $this->assertSame(0, Order::count());
    }

    public function test_guest_can_place_order(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame('jan@example.com', $order->buyer_email);
        $this->assertSame(1, $order->number);
        $this->assertSame($order->id, session()->get('recent_order_id'));
    }

    public function test_nip_lookup_fills_company_fields(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        $this->mock(CompanyLookup::class, function ($mock) {
            $mock->shouldReceive('byNip')->once()->andReturn([
                'company_name' => 'ACME sp. z o.o.',
                'street' => 'Kwiatowa',
                'building_number' => '5',
                'apartment_number' => null,
                'postal_code' => '00-001',
                'city' => 'Warszawa',
            ]);
        });

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('is_company', true)
            ->set('company_nip', '5252248481')
            ->call('lookupCompany')
            ->assertSet('company_name', 'ACME sp. z o.o.')
            ->assertSet('company_street', 'Kwiatowa')
            ->assertSet('company_city', 'Warszawa')
            ->assertHasNoErrors();
    }

    public function test_confirmation_page_shows_order_number(): void
    {
        $shop = $this->shopReadyForOrders();
        $product = $this->cartProduct($shop);
        $order = $shop->orders()->create([
            'number' => 7,
            'buyer_name' => 'Jan', 'buyer_surname' => 'Kowalski', 'buyer_email' => 'jan@example.com',
            'delivery_method' => 'pickup', 'payment_method' => 'pay_on_pickup', 'delivery_cost' => 0,
            'items_total' => 40, 'total_net' => 32.52, 'total_vat' => 7.48, 'total_gross' => 40,
        ]);

        $base = 'http://'.$shop->slug.'.'.config('tenancy.central_domain');

        $this->withSession(['recent_order_id' => $order->id])
            ->get($base.'/kasa/dziekujemy')
            ->assertOk()
            ->assertSee('#7');
    }
}
