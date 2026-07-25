<?php

namespace Tests\Feature\Seller;

use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wyszukiwarka kodów (jedno pole: kod / produkt / klient) i stronicowanie listy.
 * Sprzedawca zwykle pamięta „coś" o kodzie, ale nie wie, w której kolumnie to
 * siedzi — dlatego jedna fraza przeszukuje wszystkie trzy tropy.
 */
class DiscountCodeSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();

        return [$seller, Shop::factory()->withDiscountCodes()->create(['owner_id' => $seller->id])];
    }

    public function test_search_finds_a_code_by_its_own_text(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10']);
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'ZIMA20']);

        $this->actingAs($seller)->get(route('seller.discounts.index', ['szukaj' => 'lato']))
            ->assertOk()
            ->assertSee('LATO10')
            ->assertDontSee('ZIMA20');
    }

    public function test_search_finds_a_code_by_the_product_it_covers(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Bukiet wiosenny']);
        DiscountCode::factory()->forProduct($product)->create(['code' => 'NAPRODUKT']);
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'NAKOSZYK']);

        $this->actingAs($seller)->get(route('seller.discounts.index', ['szukaj' => 'bukiet']))
            ->assertOk()
            ->assertSee('NAPRODUKT')
            ->assertDontSee('NAKOSZYK');
    }

    public function test_search_finds_a_personal_code_by_customer_name_or_surname(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $customer = Customer::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Anna',
            'surname' => 'Kowalska',
            'email_verified_at' => now(),
        ]);
        DiscountCode::factory()->forCustomer($customer)->create(['code' => 'DLAANNY']);
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'DLAWSZYSTKICH']);

        $this->actingAs($seller)->get(route('seller.discounts.index', ['szukaj' => 'Kowalska']))
            ->assertOk()
            ->assertSee('DLAANNY')
            ->assertDontSee('DLAWSZYSTKICH');

        $this->actingAs($seller)->get(route('seller.discounts.index', ['szukaj' => 'anna']))
            ->assertOk()
            ->assertSee('DLAANNY');
    }

    public function test_search_combines_with_the_view_filter(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATOAKTYWNE']);
        DiscountCode::factory()->inactive()->create(['shop_id' => $shop->id, 'code' => 'LATOWYLACZONE']);

        $this->actingAs($seller)->get(route('seller.discounts.index', ['szukaj' => 'lato', 'stan' => 'aktywne']))
            ->assertOk()
            ->assertSee('LATOAKTYWNE')
            ->assertDontSee('LATOWYLACZONE');
    }

    public function test_empty_result_offers_to_clear_the_search(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10']);

        $this->actingAs($seller)->get(route('seller.discounts.index', ['szukaj' => 'nieistnieje']))
            ->assertOk()
            ->assertSee('Wyczyść wyszukiwanie')
            ->assertDontSee('LATO10');
    }

    public function test_list_is_paginated_by_ten(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        DiscountCode::factory()->count(25)->create(['shop_id' => $shop->id]);

        $first = $this->actingAs($seller)->get(route('seller.discounts.index'))->assertOk();
        $this->assertCount(10, $first->viewData('codes'));
        $this->assertTrue($first->viewData('codes')->hasPages());

        $last = $this->actingAs($seller)->get(route('seller.discounts.index', ['page' => 3]))->assertOk();
        $this->assertCount(5, $last->viewData('codes'));
    }

    public function test_pagination_keeps_the_search_and_the_view(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        foreach (range(1, 25) as $i) {
            DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'PROMO'.$i]);
        }

        $response = $this->actingAs($seller)
            ->get(route('seller.discounts.index', ['stan' => 'aktywne', 'szukaj' => 'PROMO']))
            ->assertOk();

        $nextPage = $response->viewData('codes')->url(2);
        $this->assertStringContainsString('stan=aktywne', $nextPage);
        $this->assertStringContainsString('szukaj=PROMO', $nextPage);
    }
}
