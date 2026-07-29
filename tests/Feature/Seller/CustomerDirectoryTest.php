<?php

namespace Tests\Feature\Seller;

use App\Enums\ConsentChannel;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use App\Services\CustomerDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kartoteka klientów sklepu. Kluczem jest ADRES E-MAIL, nie konto — większość
 * zamówień składają goście, więc kartoteka oparta o tabelę `customers`
 * pokazywałaby ułamek realnych klientów.
 */
class CustomerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function directory(): CustomerDirectory
    {
        return app(CustomerDirectory::class);
    }

    private function orderFor(Shop $shop, string $email, float $total, array $attributes = []): Order
    {
        return Order::factory()->create([
            'shop_id' => $shop->id,
            'buyer_email' => $email,
            'total_gross' => $total,
            ...$attributes,
        ]);
    }

    public function test_guests_appear_in_the_directory_next_to_account_holders(): void
    {
        $shop = Shop::factory()->create();

        $this->orderFor($shop, 'gosc@example.test', 100, ['buyer_name' => 'Jan', 'buyer_surname' => 'Nowak']);

        $account = Customer::factory()->create(['shop_id' => $shop->id, 'email' => 'anna@example.test', 'name' => 'Anna', 'surname' => 'Kowalska']);
        $this->orderFor($shop, 'anna@example.test', 200, ['customer_id' => $account->id]);

        $rows = $this->directory()->all($shop);

        $this->assertCount(2, $rows);
        $this->assertTrue($rows->firstWhere('email', 'anna@example.test')['has_account']);
        // Gość bez konta MUSI być widoczny — to realny klient z realnym zamówieniem.
        $this->assertFalse($rows->firstWhere('email', 'gosc@example.test')['has_account']);
        $this->assertSame('Jan Nowak', $rows->firstWhere('email', 'gosc@example.test')['name']);
    }

    public function test_orders_of_the_same_person_are_merged_regardless_of_letter_case(): void
    {
        $shop = Shop::factory()->create();

        $this->orderFor($shop, 'Anna@Example.test', 100);
        $this->orderFor($shop, 'anna@example.test', 150);

        $rows = $this->directory()->all($shop);

        // Ten sam człowiek raz wpisze adres wielką literą — to jedna pozycja.
        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows->first()['orders_count']);
        $this->assertSame(250.0, $rows->first()['total_spent']);
    }

    public function test_cancelled_orders_count_in_history_but_not_in_spending(): void
    {
        $shop = Shop::factory()->create();

        $this->orderFor($shop, 'anna@example.test', 100);
        $this->orderFor($shop, 'anna@example.test', 500, ['status' => OrderStatus::Cancelled]);

        $row = $this->directory()->all($shop)->first();

        // Historia kontaktu: dwa zamówienia. Pieniądze: tylko za jedno.
        $this->assertSame(2, $row['orders_count']);
        $this->assertSame(1, $row['cancelled_count']);
        $this->assertSame(100.0, $row['total_spent']);
    }

    public function test_profile_carries_orders_and_average_basket(): void
    {
        $shop = Shop::factory()->create();

        $this->orderFor($shop, 'anna@example.test', 100);
        $this->orderFor($shop, 'anna@example.test', 300);
        $this->orderFor($shop, 'anna@example.test', 900, ['status' => OrderStatus::Cancelled]);

        $profile = $this->directory()->profile($shop, 'ANNA@example.test');

        $this->assertSame(3, $profile['orders_count']);
        $this->assertSame(400.0, $profile['total_spent']);
        // Średnia z zapłaconych: 400 / 2. Anulowane nie mogą jej zaniżać.
        $this->assertSame(200.0, $profile['average_order']);
        $this->assertCount(3, $profile['orders']);
    }

    public function test_profile_of_an_unknown_address_is_null(): void
    {
        $shop = Shop::factory()->create();

        $this->assertNull($this->directory()->profile($shop, 'nikt@example.test'));
    }

    public function test_directory_never_leaks_customers_of_another_shop(): void
    {
        $shop = Shop::factory()->create();
        $other = Shop::factory()->create();

        $this->orderFor($shop, 'moj@example.test', 100);
        $this->orderFor($other, 'obcy@example.test', 100);

        $this->assertSame(['moj@example.test'], $this->directory()->all($shop)->pluck('email')->all());
        $this->assertNull($this->directory()->profile($shop, 'obcy@example.test'));
    }

    public function test_marketing_consent_is_visible_in_the_directory(): void
    {
        $shop = Shop::factory()->create();
        $account = Customer::factory()->create(['shop_id' => $shop->id, 'email' => 'anna@example.test']);
        $account->setConsent(ConsentChannel::Email, true, '127.0.0.1');
        $this->orderFor($shop, 'anna@example.test', 100, ['customer_id' => $account->id]);

        $this->assertTrue($this->directory()->all($shop)->first()['has_consent']);
    }

    public function test_search_finds_by_email_name_and_phone(): void
    {
        $shop = Shop::factory()->create();
        $this->orderFor($shop, 'anna@example.test', 100, ['buyer_name' => 'Anna', 'buyer_surname' => 'Kowalska', 'buyer_phone' => '600100200']);
        $this->orderFor($shop, 'jan@example.test', 100, ['buyer_name' => 'Jan', 'buyer_surname' => 'Nowak', 'buyer_phone' => '600300400']);

        $this->assertCount(1, $this->directory()->search($shop, 'kowalska'));
        $this->assertCount(1, $this->directory()->search($shop, 'jan@'));
        $this->assertCount(1, $this->directory()->search($shop, '600300'));
        $this->assertCount(2, $this->directory()->search($shop, ''));
    }

    public function test_sorting_by_spending_and_order_count(): void
    {
        $shop = Shop::factory()->create();
        $this->orderFor($shop, 'maly@example.test', 50);
        $this->orderFor($shop, 'maly@example.test', 50);
        $this->orderFor($shop, 'duzy@example.test', 900);

        $this->assertSame('duzy@example.test', $this->directory()->search($shop, '', 'wydatki')->first()['email']);
        $this->assertSame('maly@example.test', $this->directory()->search($shop, '', 'zamowienia')->first()['email']);
    }
}
