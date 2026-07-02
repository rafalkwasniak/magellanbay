<?php

namespace Tests\Feature\Storefront;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Strona produktu na storefroncie: URL {id}-{slug}, 301 na kanoniczny slug,
 * scope do sklepu z subdomeny i bramka widoczności (szkic/ukryty → 404 dla
 * publiki, podgląd dla właściciela/admina).
 */
class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    private function product(Shop $shop, array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'is_active' => true,
        ], $attrs));
    }

    public function test_product_page_renders(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->product($shop, ['name' => 'Kubek Ceramiczny', 'price_gross' => 29.90]);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertSee('Kubek Ceramiczny')
            ->assertSee('29,90 zł')
            ->assertSee('Dodaj do koszyka');
    }

    public function test_wrong_slug_redirects_to_canonical(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->product($shop, ['slug' => 'kubek-ceramiczny']);

        $this->get($this->host($shop).'/produkt/'.$product->id.'-zupelnie-zly-slug')
            ->assertStatus(301)
            ->assertRedirect($product->storefrontPath());
    }

    public function test_product_of_another_shop_returns_404(): void
    {
        $shopA = Shop::factory()->active()->create();
        $shopB = Shop::factory()->active()->create();
        $product = $this->product($shopA);

        // Adres produktu sklepu A wywołany na subdomenie sklepu B.
        $this->get($this->host($shopB).$product->storefrontPath())
            ->assertNotFound();
    }

    public function test_inactive_product_is_hidden_from_guests(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->product($shop, ['is_active' => false]);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertNotFound();
    }

    public function test_owner_can_preview_inactive_product(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Seller]);
        $shop = Shop::factory()->active()->create(['owner_id' => $owner->id]);
        $product = $this->product($shop, ['is_active' => false, 'name' => 'Ukryty Skarb']);

        $this->actingAs($owner)
            ->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertSee('Ukryty Skarb');
    }
}
