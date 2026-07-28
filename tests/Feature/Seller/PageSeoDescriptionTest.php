<?php

namespace Tests\Feature\Seller;

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opis SEO stron tekstowych („Informacje") — WYŁĄCZNIE ręczny.
 *
 * Świadomie bez AI: to głównie Regulamin i Polityka prywatności, dokumenty
 * długie i niewyszukiwane, więc generowanie byłoby przepalaniem tokenów bez
 * zwrotu. Sprzedawca, któremu zależy na stronie „O nas" czy „Dostawa", wpisze
 * opis sam; reszta stron zostaje przy skrócie własnej treści.
 */
class PageSeoDescriptionTest extends TestCase
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

    public function test_seller_can_write_the_pages_seo_description(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $page = Page::factory()->create(['shop_id' => $shop->id, 'title' => 'Dostawa']);

        $this->actingAs($seller)->post(route('seller.pages.update', $page), [
            'title' => 'Dostawa',
            'content' => '<p>Wysyłamy kurierem i do paczkomatów.</p>',
            'meta_description' => 'Kurier i paczkomat — wysyłka w 24 godziny.',
            'published' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Kurier i paczkomat — wysyłka w 24 godziny.', $page->fresh()->meta_description);
    }

    public function test_manual_description_wins_over_the_page_content(): void
    {
        $shop = Shop::factory()->create();
        $page = Page::factory()->create([
            'shop_id' => $shop->id,
            'content' => '<p>Niniejszy regulamin określa zasady sprzedaży w sklepie internetowym.</p>',
            'meta_description' => 'Zasady zakupów, zwrotów i reklamacji.',
        ]);

        $this->assertSame('Zasady zakupów, zwrotów i reklamacji.', Seo::pageDescription($page, $shop));
    }

    public function test_empty_field_falls_back_to_the_content(): void
    {
        $shop = Shop::factory()->create();
        $page = Page::factory()->create([
            'shop_id' => $shop->id,
            'content' => '<p>Niniejszy regulamin określa zasady sprzedaży w sklepie internetowym.</p>',
            'meta_description' => null,
        ]);

        $this->assertSame(
            'Niniejszy regulamin określa zasady sprzedaży w sklepie internetowym.',
            Seo::pageDescription($page, $shop),
        );
    }

    public function test_form_has_the_seo_box_but_no_ai_button(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $page = Page::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)->get(route('seller.pages.edit', $page))
            ->assertOk()
            ->assertSee('name="meta_description"', escape: false)
            // Brak przycisku to decyzja, nie przeoczenie — patrz migracja.
            ->assertDontSee('Wygeneruj z AI');
    }

    public function test_terms_page_can_also_get_a_description(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        // Regulamin powstaje automatycznie przy zakładaniu sklepu (is_system).
        $terms = $shop->pages()->where('is_system', true)->sole();

        $this->actingAs($seller)->post(route('seller.pages.update', $terms), [
            'title' => 'Cokolwiek',                       // tytuł systemowej strony jest stały
            'content' => '<p>Treść regulaminu.</p>',
            'meta_description' => 'Regulamin sklepu — zasady zakupów i zwrotów.',
        ])->assertRedirect();

        $terms->refresh();
        $this->assertSame('Regulamin sklepu — zasady zakupów i zwrotów.', $terms->meta_description);
        $this->assertNotSame('Cokolwiek', $terms->title);
    }
}
