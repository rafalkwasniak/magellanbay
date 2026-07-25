<?php

namespace Tests\Feature\Discount;

use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fundament kodów rabatowych: normalizacja kodu, wyliczany stan, licznik użyć
 * i matematyka rabatu. Panel i koszyk dochodzą w kolejnych krokach — tu pilnujemy
 * reguł, na których obie warstwy będą stać.
 */
class DiscountCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_code_is_stored_uppercase(): void
    {
        $code = DiscountCode::factory()->create(['code' => ' lato10 ']);

        $this->assertSame('LATO10', $code->fresh()->code);
    }

    public function test_same_code_can_exist_in_two_shops_but_not_twice_in_one(): void
    {
        $first = Shop::factory()->create();
        $second = Shop::factory()->create();

        DiscountCode::factory()->create(['shop_id' => $first->id, 'code' => 'LATO10']);
        DiscountCode::factory()->create(['shop_id' => $second->id, 'code' => 'LATO10']);

        $this->expectException(QueryException::class);
        DiscountCode::factory()->create(['shop_id' => $first->id, 'code' => 'LATO10']);
    }

    public function test_percent_discount_is_taken_from_the_given_base(): void
    {
        $code = DiscountCode::factory()->create(['type' => DiscountType::Percent, 'value' => 10]);

        $this->assertSame(12.35, $code->discountOn(123.45));
    }

    public function test_amount_discount_never_exceeds_the_base(): void
    {
        $code = DiscountCode::factory()->amount(50)->create();

        $this->assertSame(50.0, $code->discountOn(120.0));
        // Rabat większy niż koszyk obcinamy — zamówienie nie może zejść poniżej zera
        // ani pożreć kosztu wysyłki.
        $this->assertSame(30.0, $code->discountOn(30.0));
    }

    public function test_free_shipping_does_not_touch_products(): void
    {
        $code = DiscountCode::factory()->freeShipping()->create();

        $this->assertSame(0.0, $code->discountOn(200.0));
        $this->assertFalse($code->type->appliesToItems());
        $this->assertTrue($code->type->appliesToShipping());
    }

    public function test_minimum_looks_at_products_only(): void
    {
        $code = DiscountCode::factory()->minimum(100)->create();

        $this->assertFalse($code->meetsMinimum(99.99));
        $this->assertTrue($code->meetsMinimum(100.0));

        $withoutMinimum = DiscountCode::factory()->create();
        $this->assertTrue($withoutMinimum->meetsMinimum(0.0));
    }

    public function test_status_reflects_switch_dates_and_pool(): void
    {
        $this->assertSame(DiscountStatus::Active, DiscountCode::factory()->create()->status());
        $this->assertSame(DiscountStatus::Inactive, DiscountCode::factory()->inactive()->create()->status());
        $this->assertSame(DiscountStatus::Expired, DiscountCode::factory()->expired()->create()->status());
        $this->assertSame(DiscountStatus::Scheduled, DiscountCode::factory()->scheduled()->create()->status());
    }

    public function test_status_becomes_exhausted_when_the_pool_runs_out(): void
    {
        $code = DiscountCode::factory()->limitedTo(1)->create();

        Order::factory()->create(['shop_id' => $code->shop_id, 'discount_code_id' => $code->id]);

        $this->assertSame(DiscountStatus::Exhausted, $code->fresh()->status());
        $this->assertSame(0, $code->fresh()->remainingUses());
    }

    public function test_cancelled_order_gives_the_use_back(): void
    {
        $code = DiscountCode::factory()->limitedTo(1)->create();

        Order::factory()->create([
            'shop_id' => $code->shop_id,
            'discount_code_id' => $code->id,
            'status' => OrderStatus::Cancelled,
        ]);

        // Za anulowane zamówienie nikt nie zapłacił — kod wraca do puli.
        $this->assertSame(0, $code->fresh()->usedCount());
        $this->assertSame(DiscountStatus::Active, $code->fresh()->status());
    }

    public function test_unlimited_code_has_no_remaining_uses_number(): void
    {
        $code = DiscountCode::factory()->create();

        Order::factory()->create(['shop_id' => $code->shop_id, 'discount_code_id' => $code->id]);

        $this->assertSame(1, $code->fresh()->usedCount());
        $this->assertNull($code->fresh()->remainingUses());
    }

    public function test_personal_code_works_only_for_its_owner(): void
    {
        $shop = Shop::factory()->create();
        $owner = Customer::factory()->create(['shop_id' => $shop->id]);
        $someoneElse = Customer::factory()->create(['shop_id' => $shop->id]);

        $code = DiscountCode::factory()->forCustomer($owner)->create();

        $this->assertTrue($code->isPersonal());
        $this->assertTrue($code->isUsableBy($owner));
        $this->assertFalse($code->isUsableBy($someoneElse));
        // Gość nie ma jak być właścicielem kodu imiennego.
        $this->assertFalse($code->isUsableBy(null));
    }

    public function test_general_code_works_for_guests(): void
    {
        $code = DiscountCode::factory()->create();

        $this->assertFalse($code->isPersonal());
        $this->assertTrue($code->isUsableBy(null));
    }

    public function test_product_scoped_code_points_at_its_product(): void
    {
        $product = Product::factory()->create();

        $code = DiscountCode::factory()->forProduct($product)->create();

        $this->assertSame(DiscountScope::Product, $code->scope);
        $this->assertTrue($code->scope->requiresProduct());
        $this->assertSame($product->id, $code->product->id);
    }

    public function test_deleted_product_still_resolves_on_the_code(): void
    {
        $product = Product::factory()->create();
        $code = DiscountCode::factory()->forProduct($product)->create();

        $product->delete();

        // Produkty kasujemy logicznie — kod ma dalej umieć pokazać, czego dotyczył.
        $this->assertSame($product->id, $code->fresh()->product->id);
    }

    public function test_usable_scope_skips_switched_off_and_out_of_window_codes(): void
    {
        $shop = Shop::factory()->create();
        $active = DiscountCode::factory()->create(['shop_id' => $shop->id]);
        DiscountCode::factory()->inactive()->create(['shop_id' => $shop->id]);
        DiscountCode::factory()->expired()->create(['shop_id' => $shop->id]);
        DiscountCode::factory()->scheduled()->create(['shop_id' => $shop->id]);

        $usable = $shop->discountCodes()->usable()->get();

        $this->assertCount(1, $usable);
        $this->assertSame($active->id, $usable->first()->id);
    }

    public function test_random_code_is_readable(): void
    {
        $code = DiscountCode::randomCode();

        $this->assertSame(8, mb_strlen($code));
        // Bez znaków mylących przy przepisywaniu z maila lub dyktowaniu.
        $this->assertDoesNotMatchRegularExpression('/[0O1IL]/', $code);
    }

    public function test_code_suggestion_comes_from_the_name(): void
    {
        $this->assertSame('KWIATYWIOSENNE', DiscountCode::suggestFrom('Kwiaty wiosenne'));
        $this->assertSame(8, mb_strlen(DiscountCode::suggestFrom('   ')));
    }
}
