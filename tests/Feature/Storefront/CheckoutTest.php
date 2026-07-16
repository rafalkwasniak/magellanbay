<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Checkout;
use App\Models\Customer;
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

    public function test_phone_must_be_a_valid_pl_mobile(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        // Za dużo cyfr — po normalizacji to nie jest „48 + 9 cyfr", więc odpada.
        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789012345')
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->call('place')
            ->assertHasErrors('buyer_phone')
            ->assertSee('Podaj numer w formacie 48 i 9 cyfr, np. 48 500 600 700.');

        $this->assertSame(0, Order::count());
    }

    public function test_phone_stored_in_canonical_form(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        // Zapis „ludzki" (spacje, +48) → w bazie kanoniczne „48" + 9 cyfr.
        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '+48 500 600 700')
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $this->assertSame('48500600700', Order::first()->buyer_phone);
    }

    public function test_validation_messages_use_polish_field_names(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_phone', '')
            ->set('accept_terms', true)
            ->call('place')
            ->assertHasErrors('buyer_phone')
            // Czytelna nazwa pola zamiast surowej właściwości „buyer phone".
            ->assertSee('Pole telefon jest wymagane.')
            ->assertDontSee('buyer phone');
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
            ->set('accept_privacy', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame('jan@example.com', $order->buyer_email);
        $this->assertSame(1, $order->number);
        $this->assertSame($order->id, session()->get('recent_order_id'));
        // Gość bez „załóż konto" — brak powiązania z kontem.
        $this->assertNull($order->customer_id);
    }

    private function fillValidCheckout(Shop $shop): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->set('accept_privacy', true);
    }

    public function test_create_account_makes_unactivated_customer_and_links_order(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        $this->fillValidCheckout($shop)
            ->set('create_account', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $customer = Customer::where('shop_id', $shop->id)->where('email', 'jan@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertFalse($customer->isActivated());
        $this->assertSame('Jan', $customer->name);
        // Telefon zapisany w formie kanonicznej (48 + 9 cyfr), jak w zamówieniu.
        $this->assertSame('48123456789', $customer->phone);
        $this->assertSame($customer->id, Order::first()->customer_id);

        // Link aktywacyjny „od sklepu" w kolejce.
        $this->assertDatabaseHas('email_messages', [
            'to_email' => 'jan@example.com',
            'subject' => 'Aktywuj swoje konto — '.$shop->name,
        ]);
    }

    public function test_order_silently_links_to_existing_account_without_activation(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);
        $existing = Customer::factory()->for($shop)->create(['email' => 'jan@example.com']);

        // Nawet z zaznaczonym „załóż konto" — konto istnieje, więc tylko dopięcie.
        $this->fillValidCheckout($shop)
            ->set('create_account', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $this->assertSame($existing->id, Order::first()->customer_id);
        $this->assertSame(1, Customer::where('shop_id', $shop->id)->count());
        $this->assertDatabaseMissing('email_messages', [
            'to_email' => 'jan@example.com',
            'subject' => 'Aktywuj swoje konto — '.$shop->name,
        ]);
    }

    public function test_matching_is_case_insensitive(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);
        $existing = Customer::factory()->for($shop)->create(['email' => 'jan@example.com']);

        $this->fillValidCheckout($shop)
            ->set('buyer_email', 'JAN@Example.com')
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $this->assertSame($existing->id, Order::first()->customer_id);
        $this->assertSame(1, Customer::where('shop_id', $shop->id)->count());
    }

    public function test_logged_in_customer_order_links_to_their_account(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);
        $customer = Customer::factory()->for($shop)->create([
            'name' => 'Ala', 'surname' => 'Nowak', 'email' => 'ala@example.com', 'phone' => '48500600700',
        ]);

        $this->actingAs($customer, 'customer');

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            // dane wypełnione z konta w mount() — dokładamy tylko metody i zgody
            ->assertSet('buyer_email', 'ala@example.com')
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $this->assertSame($customer->id, Order::first()->customer_id);
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

    public function test_courier_option_requires_shipping_address(): void
    {
        $shop = $this->shopReadyForOrders();
        $shop->update(['courier_enabled' => true, 'courier_cost' => 15.00]);
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->assertSee('Kurier')
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'courier')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place')
            ->assertHasErrors(['ship_street', 'ship_building_number', 'ship_postal_code', 'ship_city']);

        $this->assertSame(0, Order::count());
    }

    public function test_choosing_courier_drops_pay_on_pickup_payment(): void
    {
        $shop = $this->shopReadyForOrders();
        $shop->update(['courier_enabled' => true, 'courier_cost' => 15.00]);
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'pay_on_pickup')
            // Przełączenie na kuriera zdejmuje „płatność przy odbiorze" i przełącza
            // wybór na pierwszą dostępną metodę (przelew).
            ->set('delivery_method', 'courier')
            ->assertDontSee('Płatność przy odbiorze')
            ->assertSet('payment_method', 'bank_transfer');
    }

    public function test_guest_places_courier_order_with_cost_and_address(): void
    {
        $shop = $this->shopReadyForOrders();
        $shop->update(['courier_enabled' => true, 'courier_cost' => 15.00]);
        $this->cartProduct($shop);   // produkt 40 zł

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'courier')
            ->set('payment_method', 'bank_transfer')
            ->set('ship_street', 'Leśna')
            ->set('ship_building_number', '12')
            ->set('ship_postal_code', '30-001')
            ->set('ship_city', 'Kraków')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $order = Order::first();
        $this->assertSame(\App\Enums\DeliveryMethod::Courier, $order->delivery_method);
        $this->assertSame('15.00', $order->delivery_cost);
        $this->assertSame('55.00', $order->total_gross);   // 40 produkty + 15 dostawa
        $this->assertSame('Leśna', $order->ship_street);
        $this->assertSame('Kraków', $order->ship_city);
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
