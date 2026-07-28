<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Box „SEO" w panelu: własny opis do wyników wyszukiwania dla produktu i sklepu.
 *
 * Reguła własności tekstu: opis wpisany ręcznie należy do sprzedawcy i automat
 * (docelowo AI) nigdy go nie nadpisze — o tym mówi znacznik
 * `meta_description_manual`. Wyczyszczenie pola oddaje kontrolę automatowi, więc
 * sprzedawca nie musi rozumieć pojęcia „tryb automatyczny".
 */
class SeoDescriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();

        return [$seller, Shop::factory()->create(['owner_id' => $seller->id])];
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'name' => $product->name,
            'price_gross' => '100,00',
            'vat_rate' => $product->vat_rate->value,
            'sale_unit' => $product->sale_unit->value,
            'stock' => '5',
            'track_stock' => '1',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_seller_can_write_the_products_seo_description(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)->post(
            route('seller.products.update', $product),
            $this->productPayload($product, ['meta_description' => 'Karbonowa szosa na długie dystanse. Wysyłka w 24 h.']),
        )->assertRedirect();

        $product->refresh();
        $this->assertSame('Karbonowa szosa na długie dystanse. Wysyłka w 24 h.', $product->meta_description);
        // Znacznik własności — automat ma trzymać ręce przy sobie.
        $this->assertTrue($product->meta_description_manual);
    }

    public function test_clearing_the_field_hands_control_back_to_automation(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'meta_description' => 'Stary opis',
            'meta_description_manual' => true,
        ]);

        $this->actingAs($seller)->post(
            route('seller.products.update', $product),
            $this->productPayload($product, ['meta_description' => '   ']),
        )->assertRedirect();

        $product->refresh();
        $this->assertNull($product->meta_description);
        $this->assertFalse($product->meta_description_manual);
    }

    public function test_manual_description_wins_over_the_product_text(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'description' => '<p>Długi, rozwlekły opis marketingowy, który sam w sobie nie jest zachętą.</p>',
            'meta_description' => 'Krótka zachęta pod Google.',
        ]);

        $this->assertSame('Krótka zachęta pod Google.', Seo::productDescription($product, $shop));
    }

    public function test_seller_can_write_the_shops_seo_description(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        // Formularz „Mój sklep" wymaga kompletu danych adresowych — bez nich
        // walidacja odesłałaby z błędami, a test i tak zobaczyłby przekierowanie.
        $this->actingAs($seller)->post(route('seller.shop.update'), [
            'name' => $shop->name,
            'contact_email' => 'kontakt@sklep.test',
            'contact_phone' => '500600700',
            'country' => 'Polska',
            'province' => config('shop.provinces')[0],
            'city' => 'Kraków',
            'postal_code' => '30-001',
            'street' => 'Kwiatowa',
            'building_number' => '5',
            'meta_description' => 'Komis rowerowy z pasją. Sprawdzone rowery szosowe i gravelowe.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $shop->refresh();
        $this->assertSame('Komis rowerowy z pasją. Sprawdzone rowery szosowe i gravelowe.', $shop->meta_description);
        $this->assertTrue($shop->meta_description_manual);
    }

    public function test_manual_description_wins_over_the_shop_about_text(): void
    {
        $shop = Shop::factory()->create([
            'description' => '<p>Opis sklepu widoczny na stronie głównej.</p>',
            'meta_description' => 'Zachęta pisana pod wyniki wyszukiwania.',
        ]);

        $this->assertSame('Zachęta pisana pod wyniki wyszukiwania.', Seo::shopDescription($shop));
    }

    public function test_long_manual_description_is_still_clipped_in_the_tag(): void
    {
        $shop = Shop::factory()->create([
            'meta_description' => str_repeat('Bardzo długi opis SEO napisany ręcznie. ', 10),
        ]);

        // W bazie mieści się do 255 znaków, ale w tagu wysyłamy tyle, ile pokaże
        // Google — reszta byłaby i tak ucięta.
        $this->assertLessThanOrEqual(Seo::MAX_DESCRIPTION + 1, mb_strlen(Seo::shopDescription($shop)));
    }

    public function test_box_shows_what_would_be_used_automatically(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Bidon',
            'description' => '<p>Bidon termiczny 600 ml, utrzymuje temperaturę przez 6 godzin.</p>',
        ]);

        // Podpowiedź w pustym polu = dokładnie to, co wystawimy bez ingerencji.
        $this->actingAs($seller)->get(route('seller.products.edit', $product))
            ->assertOk()
            ->assertSee('SEO')
            ->assertSee('Bidon termiczny 600 ml', escape: false);
    }
}
