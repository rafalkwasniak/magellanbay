<?php

namespace Tests\Feature\Seller;

use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Akcje na liście kodów: włącz/wyłącz, usuwanie i zakładki stanu. Kasowanie
 * kodu nie może zacierać historii — zamówienie trzyma własną migawkę rabatu.
 */
class DiscountCodeActionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(bool $allowed = true): array
    {
        $seller = User::factory()->consented()->create();
        $factory = $allowed ? Shop::factory()->withDiscountCodes() : Shop::factory();

        return [$seller, $factory->create(['owner_id' => $seller->id])];
    }

    public function test_toggle_switches_the_code_off_and_back_on(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $code = DiscountCode::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)->post(route('seller.discounts.toggle', $code))->assertRedirect();
        $this->assertFalse($code->fresh()->is_active);

        $this->actingAs($seller)->post(route('seller.discounts.toggle', $code))->assertRedirect();
        $this->assertTrue($code->fresh()->is_active);
    }

    public function test_toggle_is_closed_without_the_entitlement(): void
    {
        [$seller, $shop] = $this->sellerWithShop(allowed: false);
        $code = DiscountCode::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)->post(route('seller.discounts.toggle', $code))->assertForbidden();
        $this->assertTrue($code->fresh()->is_active);
    }

    public function test_seller_cannot_touch_a_foreign_code(): void
    {
        [$seller] = $this->sellerWithShop();
        $foreign = DiscountCode::factory()->create();

        $this->actingAs($seller)->post(route('seller.discounts.toggle', $foreign))->assertNotFound();
        $this->actingAs($seller)->post(route('seller.discounts.destroy', $foreign))->assertNotFound();
        $this->assertModelExists($foreign);
    }

    public function test_deleting_a_used_code_keeps_the_order_record(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $code = DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10']);
        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'discount_code_id' => $code->id,
            'discount_code' => 'LATO10',
            'discount_amount' => 12.34,
        ]);

        $this->actingAs($seller)->post(route('seller.discounts.destroy', $code))
            ->assertRedirect(route('seller.discounts.index'));

        $this->assertModelMissing($code);

        // Relacja gaśnie, ale zamówienie pamięta, co klient dostał.
        $order->refresh();
        $this->assertNull($order->discount_code_id);
        $this->assertSame('LATO10', $order->discount_code);
        $this->assertSame(12.34, (float) $order->discount_amount);
    }

    public function test_filter_splits_active_from_the_rest(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'DZIALA']);
        DiscountCode::factory()->expired()->create(['shop_id' => $shop->id, 'code' => 'POTERMINIE']);
        DiscountCode::factory()->inactive()->create(['shop_id' => $shop->id, 'code' => 'WYLACZONY']);

        $this->actingAs($seller)->get(route('seller.discounts.index', ['stan' => 'aktywne']))
            ->assertOk()
            ->assertSee('DZIALA')
            ->assertDontSee('POTERMINIE')
            ->assertDontSee('WYLACZONY');

        $this->actingAs($seller)->get(route('seller.discounts.index', ['stan' => 'nieaktywne']))
            ->assertOk()
            ->assertDontSee('DZIALA')
            ->assertSee('POTERMINIE')
            ->assertSee('WYLACZONY');
    }

    public function test_unknown_filter_falls_back_to_all(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'DZIALA']);
        DiscountCode::factory()->inactive()->create(['shop_id' => $shop->id, 'code' => 'WYLACZONY']);

        $response = $this->actingAs($seller)->get(route('seller.discounts.index', ['stan' => 'cokolwiek']))->assertOk();

        $this->assertSame('wszystkie', $response->viewData('filter'));
        $response->assertSee('DZIALA')->assertSee('WYLACZONY');
    }
}
