<?php

namespace Tests\Feature\Seller;

use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lista kodów rabatowych w panelu sprzedawcy. Funkcja płatna (`discount_codes`,
 * Pawilon): stronę widzą wszyscy, ale bez uprawnienia zamiast narzędzia jest
 * zachęta — kodów nie ma nawet czego wyświetlić.
 */
class DiscountCodeListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(bool $allowed = true): array
    {
        $seller = User::factory()->consented()->create();
        $factory = Shop::factory();

        if ($allowed) {
            $factory = $factory->withDiscountCodes();
        }

        return [$seller, $factory->create(['owner_id' => $seller->id])];
    }

    public function test_shop_with_entitlement_sees_its_codes(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10']);

        $this->actingAs($seller)->get(route('seller.discounts.index'))
            ->assertOk()
            ->assertSee('LATO10')
            ->assertSee('Cały koszyk')
            ->assertDontSee('Kody rabatowe w pakiecie Pawilon');
    }

    public function test_shop_without_entitlement_sees_the_upsell_instead(): void
    {
        [$seller, $shop] = $this->sellerWithShop(allowed: false);
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10']);

        $response = $this->actingAs($seller)->get(route('seller.discounts.index'))->assertOk();

        $response->assertSee('Kody rabatowe w pakiecie Pawilon');
        // Uprawnienia nie ma → kodów nie pokazujemy, nawet gdyby zostały po downgrade.
        $response->assertDontSee('LATO10');
        $this->assertFalse($response->viewData('allowed'));
    }

    public function test_codes_of_another_shop_are_not_visible(): void
    {
        [$seller] = $this->sellerWithShop();
        $foreign = Shop::factory()->withDiscountCodes()->create();
        DiscountCode::factory()->create(['shop_id' => $foreign->id, 'code' => 'OBCY']);

        $this->actingAs($seller)->get(route('seller.discounts.index'))
            ->assertOk()
            ->assertDontSee('OBCY');
    }

    public function test_list_shows_usage_against_the_limit(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $code = DiscountCode::factory()->limitedTo(5)->create(['shop_id' => $shop->id]);
        Order::factory()->count(2)->create(['shop_id' => $shop->id, 'discount_code_id' => $code->id]);

        $this->actingAs($seller)->get(route('seller.discounts.index'))
            ->assertOk()
            ->assertSee('Użyto: 2 / 5');
    }

    public function test_list_counts_uses_without_a_query_per_row(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        DiscountCode::factory()->count(3)->create(['shop_id' => $shop->id]);

        $response = $this->actingAs($seller)->get(route('seller.discounts.index'))->assertOk();

        // Licznik użyć dociągany przez withCount — na liście nie może dochodzić
        // zapytanie na wiersz (lista rośnie, hosting jest współdzielony).
        foreach ($response->viewData('codes') as $code) {
            $this->assertNotNull($code->getAttribute('used_count'));
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('seller.discounts.index'))->assertRedirect(route('login'));
    }
}
