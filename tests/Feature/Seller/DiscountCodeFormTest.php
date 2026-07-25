<?php

namespace Tests\Feature\Seller;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Livewire\Seller\DiscountCodeForm;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Formularz kodu rabatowego: zapis, walidacja i to, co formularz sam prostuje
 * (darmowa wysyłka bez wartości, tryb limitu zamiast gołej liczby). Osobno
 * pilnujemy bramki — bez uprawnienia `discount_codes` narzędzie jest zamknięte,
 * nie tylko ukryte.
 */
class DiscountCodeFormTest extends TestCase
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

    public function test_new_code_page_is_closed_without_the_entitlement(): void
    {
        [$seller] = $this->sellerWithShop(allowed: false);

        $this->actingAs($seller)->get(route('seller.discounts.create'))->assertForbidden();
    }

    public function test_seller_cannot_open_a_foreign_code(): void
    {
        [$seller] = $this->sellerWithShop();
        $foreign = DiscountCode::factory()->create();

        $this->actingAs($seller)->get(route('seller.discounts.edit', $foreign))->assertNotFound();
    }

    public function test_pages_render_for_a_shop_with_the_entitlement(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $code = DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10']);

        $this->actingAs($seller)->get(route('seller.discounts.create'))
            ->assertOk()
            ->assertSee('Jak zadziała');

        $this->actingAs($seller)->get(route('seller.discounts.edit', $code))
            ->assertOk()
            ->assertSee('LATO10');
    }

    public function test_clearing_the_pickers_does_not_break_typed_properties(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $customer = Customer::factory()->create(['shop_id' => $shop->id, 'email_verified_at' => now()]);

        // Puste opcje selectów wysyłają '' — nie może to wywrócić typowanych pól.
        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('scope', DiscountScope::Product->value)
            ->set('product_id', $product->id)
            ->set('customer_id', $customer->id)
            ->set('product_id', '')
            ->set('customer_id', '')
            ->assertSet('product_id', null)
            ->assertSet('customer_id', null);
    }

    public function test_new_form_starts_with_a_generated_code(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $component = Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop]);

        $this->assertSame(8, mb_strlen($component->get('codeValue')));
    }

    public function test_percent_cart_code_is_saved(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'lato10')
            ->set('type', DiscountType::Percent->value)
            ->set('value', '10')
            ->call('save')
            ->assertHasNoErrors();

        $code = $shop->discountCodes()->sole();
        $this->assertSame('LATO10', $code->code);              // wersaliki mimo wpisania małych liter
        $this->assertSame(DiscountType::Percent, $code->type);
        $this->assertSame(DiscountScope::Cart, $code->scope);
        $this->assertSame(10.0, (float) $code->value);
        $this->assertNull($code->max_uses);
        $this->assertTrue($code->is_active);
    }

    public function test_amount_code_accepts_a_comma_decimal(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'ZIMA')
            ->set('type', DiscountType::Amount->value)
            ->set('value', '19,90')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(19.90, (float) $shop->discountCodes()->sole()->value);
    }

    public function test_percent_above_hundred_is_rejected(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'ZADUZO')
            ->set('type', DiscountType::Percent->value)
            ->set('value', '120')
            ->call('save')
            ->assertHasErrors(['value' => 'max']);

        $this->assertSame(0, $shop->discountCodes()->count());
    }

    public function test_product_scope_requires_a_product_of_this_shop(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $foreignProduct = Product::factory()->create();

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'BEZPRODUKTU')
            ->set('scope', DiscountScope::Product->value)
            ->set('value', '10')
            ->call('save')
            ->assertHasErrors(['product_id' => 'required'])
            ->set('product_id', $foreignProduct->id)
            ->call('save')
            ->assertHasErrors('product_id');
    }

    public function test_switching_to_free_shipping_clears_value_and_product(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'DARMOWA')
            ->set('scope', DiscountScope::Product->value)
            ->set('product_id', $product->id)
            ->set('value', '15')
            ->set('type', DiscountType::FreeShipping->value)
            ->assertSet('value', '')
            ->assertSet('product_id', null)
            ->assertSet('scope', DiscountScope::Cart->value)
            ->call('save')
            ->assertHasNoErrors();

        $code = $shop->discountCodes()->sole();
        $this->assertSame(DiscountType::FreeShipping, $code->type);
        $this->assertNull($code->value);
        $this->assertNull($code->product_id);
    }

    public function test_one_off_mode_saves_a_single_use_limit(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'JEDNORAZOWY')
            ->set('value', '10')
            ->set('uses_mode', 'jednorazowy')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $shop->discountCodes()->sole()->max_uses);
    }

    public function test_limit_mode_requires_a_number(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'LIMIT')
            ->set('value', '10')
            ->set('uses_mode', 'limit')
            ->call('save')
            ->assertHasErrors(['max_uses' => 'required']);
    }

    public function test_end_date_covers_the_whole_day(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'TERMIN')
            ->set('value', '10')
            ->set('starts_at', '2026-08-01')
            ->set('ends_at', '2026-08-31')
            ->call('save')
            ->assertHasNoErrors();

        $code = $shop->discountCodes()->sole();
        $this->assertSame('2026-08-01 00:00:00', $code->starts_at->format('Y-m-d H:i:s'));
        // Klient ma prawo użyć kodu do północy ostatniego dnia, nie do 00:00 rano.
        $this->assertSame('2026-08-31 23:59:59', $code->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_end_date_before_start_is_rejected(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'ODWROTNIE')
            ->set('value', '10')
            ->set('starts_at', '2026-08-31')
            ->set('ends_at', '2026-08-01')
            ->call('save')
            ->assertHasErrors(['ends_at' => 'after_or_equal']);
    }

    public function test_duplicate_code_in_the_same_shop_is_rejected(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10']);

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'lato10')
            ->set('value', '10')
            ->call('save')
            ->assertHasErrors(['codeValue' => 'unique']);
    }

    public function test_editing_keeps_its_own_code_valid(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $code = DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10']);

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop, 'code' => $code])
            ->assertSet('codeValue', 'LATO10')
            ->assertSet('value', '10')
            ->set('value', '15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(15.0, (float) $code->fresh()->value);
        $this->assertSame(1, $shop->discountCodes()->count());
    }

    public function test_personal_code_must_point_at_a_customer_of_this_shop(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $foreignCustomer = Customer::factory()->create();

        Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'IMIENNY')
            ->set('value', '10')
            ->set('customer_id', $foreignCustomer->id)
            ->call('save')
            ->assertHasErrors('customer_id');
    }

    public function test_summary_describes_the_code_in_plain_polish(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Bukiet wiosenny']);

        $summary = Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'WIOSNA')
            ->set('value', '15')
            ->set('scope', DiscountScope::Product->value)
            ->set('product_id', $product->id)
            ->set('min_items_total', '100')
            ->set('uses_mode', 'jednorazowy')
            ->instance()->summary();

        $text = implode(' ', $summary);
        $this->assertStringContainsString('Kod WIOSNA obniża cenę produktu „Bukiet wiosenny" o 15%.', $text);
        $this->assertStringContainsString('co najmniej 100,00 zł', $text);
        $this->assertStringContainsString('Do użycia tylko raz.', $text);
        $this->assertStringContainsString('Nie obejmuje kosztu wysyłki.', $text);
    }

    public function test_free_shipping_summary_does_not_mention_product_discount(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $summary = Livewire::actingAs($seller)->test(DiscountCodeForm::class, ['shop' => $shop])
            ->set('codeValue', 'DARMOWA')
            ->set('type', DiscountType::FreeShipping->value)
            ->instance()->summary();

        $text = implode(' ', $summary);
        $this->assertStringContainsString('daje klientowi darmową wysyłkę', $text);
        $this->assertStringNotContainsString('Nie obejmuje kosztu wysyłki', $text);
    }
}
