<?php

namespace Tests\Feature\Seller;

use App\Livewire\Seller\DiscountCodeForm;
use App\Models\DiscountCode;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Powrót na właściwą stronę listy po zapisie. Przy kilkudziesięciu kodach
 * odesłanie na stronę pierwszą po każdej poprawce jest karą — edycja ma wracać
 * tam, skąd sprzedawca wszedł. NOWY kod to wyjątek: ląduje na początku listy,
 * więc wracamy na stronę pierwszą bez filtrów.
 */
class DiscountCodeReturnToListTest extends TestCase
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

    public function test_edit_page_carries_the_list_context(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $code = DiscountCode::factory()->create(['shop_id' => $shop->id]);

        $response = $this->actingAs($seller)->get(route('seller.discounts.edit', [
            'discountCode' => $code,
            'page' => 3,
            'stan' => 'aktywne',
            'szukaj' => 'lato',
        ]))->assertOk();

        $this->assertSame(
            ['stan' => 'aktywne', 'szukaj' => 'lato', 'page' => 3],
            $response->viewData('listQuery'),
        );
    }

    public function test_saving_an_edit_returns_to_the_same_page(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $code = DiscountCode::factory()->create(['shop_id' => $shop->id]);

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, [
            'shop' => $shop,
            'code' => $code,
            'listQuery' => ['stan' => 'aktywne', 'page' => 3],
        ])
            ->set('value', '25')
            ->call('save')
            ->assertRedirect(route('seller.discounts.index', ['stan' => 'aktywne', 'page' => 3]));
    }

    public function test_saving_a_new_code_returns_to_the_first_page(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'NOWY')
            ->set('value', '10')
            ->call('save')
            ->assertRedirect(route('seller.discounts.index'));
    }

    public function test_new_code_page_never_carries_a_page_number(): void
    {
        [$seller] = $this->sellerWithShop();

        $response = $this->actingAs($seller)
            ->get(route('seller.discounts.create', ['page' => 3]))
            ->assertOk();

        $this->assertSame([], $response->viewData('listQuery'));
    }

    public function test_deleting_from_a_page_returns_to_that_page(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $code = DiscountCode::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)
            ->post(route('seller.discounts.destroy', ['discountCode' => $code, 'page' => 2]))
            ->assertRedirect(route('seller.discounts.index', ['page' => 2]));
    }
}
