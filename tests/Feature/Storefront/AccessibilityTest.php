<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dostępność storefrontu — poprawki wskazane przez audyt ursalogic (16.07.2026,
 * sekcja WCAG 88/100). Wszystkie trzy znaleziska były platformowe, więc naprawa
 * obejmuje każdy sklep na Kramio.
 */
class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function shop(): Shop
    {
        return Shop::factory()->create(['status' => 'active']);
    }

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    public function test_every_page_starts_with_a_skip_link(): void
    {
        $shop = $this->shop();

        // Audyt: brak linku „Przejdź do treści" na 100% stron. Osoba nawigująca
        // klawiaturą musiała przechodzić całe menu na każdej podstronie.
        $this->get($this->host($shop))
            ->assertOk()
            ->assertSee('<a href="#tresc" class="st-skip">Przejdź do treści</a>', escape: false)
            ->assertSee('<div id="tresc" tabindex="-1">', escape: false);
    }

    public function test_gallery_thumbnails_announce_what_they_do(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Trek Madone SL7']);

        foreach (range(1, 2) as $position) {
            // `product_id` nie jest mass-assignable — zdjęcia tworzymy przez relację.
            $product->images()->create([
                'path' => 'products/'.$product->id.'/zdjecie-'.$position.'.webp',
                'position' => $position,
            ]);
        }

        // Audyt: „przyciski bez tekstu" i „obrazy bez alt" na 8 stronach — obie
        // pozycje wskazywały to samo miejsce: miniatury galerii produktu.
        $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertSee('aria-label="Pokaż zdjęcie 1 z 2: Trek Madone SL7"', escape: false)
            ->assertSee('aria-label="Pokaż zdjęcie 2 z 2: Trek Madone SL7"', escape: false);
    }

    public function test_account_email_field_has_a_bound_label(): void
    {
        $shop = $this->shop();
        $customer = Customer::factory()->create(['shop_id' => $shop->id, 'email_verified_at' => now()]);

        // Audyt: „pola formularzy bez etykiet" na 2 stronach. Etykieta leżała
        // obok pola, ale bez `for`/`id`, więc czytnik ekranu jej nie wiązał.
        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto/dane')
            ->assertOk()
            ->assertSee('<label for="account-email"', escape: false)
            ->assertSee('<input id="account-email"', escape: false);
    }
}
