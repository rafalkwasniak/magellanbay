<?php

namespace Tests\Feature\Storefront;

use App\Enums\UserRole;
use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Publiczna strona „Informacje" na storefroncie: URL {id}-{slug}, 301 na
 * kanoniczny slug, scope do sklepu z subdomeny i bramka widoczności (ukryta
 * strona / sklep-szkic → 404 dla publiki, podgląd dla właściciela/admina).
 */
class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    public function test_published_page_renders(): void
    {
        $shop = Shop::factory()->active()->create();
        $page = Page::factory()->for($shop)->create([
            'title' => 'Dostawa i zwroty',
            'content' => '<p>Wysyłamy w 24 godziny.</p>',
        ]);

        $this->get($this->host($shop).$page->storefrontPath())
            ->assertOk()
            ->assertSee('Dostawa i zwroty')
            ->assertSee('Wysyłamy w 24 godziny');
    }

    public function test_system_regulamin_page_renders(): void
    {
        $shop = Shop::factory()->active()->create();
        $regulamin = $shop->pages()->where('is_system', true)->first();

        $this->get($this->host($shop).$regulamin->storefrontPath())
            ->assertOk()
            ->assertSee('Regulamin');
    }

    public function test_wrong_slug_redirects_to_canonical(): void
    {
        $shop = Shop::factory()->active()->create();
        $page = Page::factory()->for($shop)->create(['slug' => 'o-sklepie']);

        $this->get($this->host($shop).'/informacje/'.$page->id.'-zupelnie-zly-slug')
            ->assertStatus(301)
            ->assertRedirect($page->storefrontPath());
    }

    public function test_canonical_redirect_preserves_query(): void
    {
        $shop = Shop::factory()->active()->create();
        $page = Page::factory()->for($shop)->create(['slug' => 'kontakt']);

        $this->get($this->host($shop).'/informacje/'.$page->id.'-zly?utm_source=fb')
            ->assertStatus(301)
            ->assertRedirect($page->storefrontPath().'?utm_source=fb');
    }

    public function test_page_of_another_shop_returns_404(): void
    {
        $shopA = Shop::factory()->active()->create();
        $shopB = Shop::factory()->active()->create();
        $page = Page::factory()->for($shopA)->create();

        // Adres strony sklepu A wywołany na subdomenie sklepu B.
        $this->get($this->host($shopB).$page->storefrontPath())
            ->assertNotFound();
    }

    public function test_unpublished_page_is_hidden_from_guests(): void
    {
        $shop = Shop::factory()->active()->create();
        $page = Page::factory()->for($shop)->unpublished()->create();

        $this->get($this->host($shop).$page->storefrontPath())
            ->assertNotFound();
    }

    public function test_owner_can_preview_unpublished_page(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Seller]);
        $shop = Shop::factory()->active()->create(['owner_id' => $owner->id]);
        $page = Page::factory()->for($shop)->unpublished()->create(['title' => 'Szkic strony']);

        $this->actingAs($owner)
            ->get($this->host($shop).$page->storefrontPath())
            ->assertOk()
            ->assertSee('Szkic strony')
            ->assertSee('Podgląd'); // baner „ukryta dla klientów"
    }

    public function test_page_of_draft_shop_is_hidden_from_guests(): void
    {
        $shop = Shop::factory()->create(); // status Draft → sklep niewidoczny
        $page = $shop->pages()->where('is_system', true)->first();

        $this->get($this->host($shop).$page->storefrontPath())
            ->assertNotFound();
    }

    public function test_page_renders_breadcrumbs(): void
    {
        $shop = Shop::factory()->active()->create(['name' => 'Srebrny Kram']);
        $page = Page::factory()->for($shop)->create(['title' => 'O nas']);

        $this->get($this->host($shop).$page->storefrontPath())
            ->assertOk()
            ->assertSee('aria-label="Ścieżka nawigacji"', false)
            ->assertSee('Srebrny Kram')
            ->assertSee('O nas');
    }
}
