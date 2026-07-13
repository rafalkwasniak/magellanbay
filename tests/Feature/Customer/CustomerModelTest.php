<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Fundament kont klientów (Krok 1): tabela/model `Customer` per-sklep, relacje,
 * unikalność e-maila w obrębie sklepu (ale nie między sklepami), klucz obcy
 * zamówień oraz osobny guard `customer`. UI (rejestracja/logowanie) dochodzi
 * w kolejnych krokach.
 */
class CustomerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_belongs_to_a_shop(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        $this->assertTrue($customer->shop->is($shop));
    }

    public function test_same_email_allowed_in_different_shops(): void
    {
        $email = 'klient@example.com';

        $first = Customer::factory()->create(['email' => $email]);
        $second = Customer::factory()->create(['email' => $email]);

        $this->assertNotSame($first->shop_id, $second->shop_id);
        $this->assertSame($email, $first->email);
        $this->assertSame($email, $second->email);
    }

    public function test_same_email_rejected_within_one_shop(): void
    {
        $shop = Shop::factory()->create();
        Customer::factory()->for($shop)->create(['email' => 'klient@example.com']);

        $this->expectException(QueryException::class);
        Customer::factory()->for($shop)->create(['email' => 'klient@example.com']);
    }

    public function test_orders_relation_links_by_customer_id(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        $mine = Order::factory()->for($shop)->create(['customer_id' => $customer->id]);
        $guest = Order::factory()->for($shop)->create(['customer_id' => null]);

        $this->assertTrue($customer->orders->contains($mine));
        $this->assertFalse($customer->orders->contains($guest));
        $this->assertTrue($mine->customer->is($customer));
        $this->assertNull($guest->customer);
    }

    public function test_deleting_customer_detaches_orders_to_guest(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->create(['customer_id' => $customer->id]);

        $customer->delete();

        $this->assertNull($order->fresh()->customer_id);
    }

    public function test_customer_guard_resolves_to_customer_model(): void
    {
        $customer = Customer::factory()->create();

        Auth::guard('customer')->login($customer);

        $this->assertTrue(Auth::guard('customer')->check());
        $this->assertInstanceOf(Customer::class, Auth::guard('customer')->user());
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_activation_state_helpers(): void
    {
        $active = Customer::factory()->create();
        $pending = Customer::factory()->unactivated()->create();

        $this->assertTrue($active->isActivated());
        $this->assertFalse($pending->isActivated());
        $this->assertNull($pending->password);
        $this->assertNull($pending->email_verified_at);
    }
}
