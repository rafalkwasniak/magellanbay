<?php

namespace Tests\Feature\Seller;

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagePanelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);

        return [$seller, $shop];
    }

    public function test_index_lists_the_shop_pages(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Page::factory()->for($shop)->create(['title' => 'Dostawa i zwroty']);

        $this->actingAs($seller)
            ->get(route('seller.pages.index'))
            ->assertOk()
            ->assertSee('Dostawa i zwroty')
            ->assertSee('Regulamin'); // strona systemowa z observera
    }

    public function test_seller_can_create_a_page_appended_at_the_end(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.pages.store'), [
                'title' => 'O sklepie',
                'content' => '<p>Robimy świece.</p>',
                'published' => '1',
            ])
            ->assertRedirect(route('seller.pages.index'))
            ->assertSessionHas('success');

        $page = $shop->pages()->where('title', 'O sklepie')->first();
        $this->assertNotNull($page);
        $this->assertFalse($page->is_system);
        $this->assertSame('o-sklepie', $page->slug);
        // Regulamin ma position 0 → nowa strona ląduje na 1.
        $this->assertSame(1, $page->position);
    }

    public function test_new_page_cannot_smuggle_is_system(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)->post(route('seller.pages.store'), [
            'title' => 'Podszywka',
            'content' => '<p>x</p>',
            'is_system' => '1',
        ]);

        $this->assertFalse($shop->pages()->where('title', 'Podszywka')->first()->is_system);
    }

    public function test_seller_can_update_a_regular_page(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $page = Page::factory()->for($shop)->create(['title' => 'Stary', 'published' => true]);

        $this->actingAs($seller)
            ->post(route('seller.pages.update', $page), [
                'title' => 'Nowy tytuł',
                'content' => '<p>Nowa treść.</p>',
                // brak `published` → odznaczone
            ])
            ->assertRedirect(route('seller.pages.edit', $page));

        $page->refresh();
        $this->assertSame('Nowy tytuł', $page->title);
        $this->assertSame('nowy-tytul', $page->slug);
        $this->assertFalse($page->published);
    }

    public function test_updating_the_system_page_changes_only_content(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $regulamin = $shop->pages()->where('is_system', true)->first();

        // Formularz strony systemowej NIE wysyła `title` (pole zablokowane) ani
        // `published` — dokładnie tak jak w przeglądarce. Zapis musi mimo to przejść.
        $this->actingAs($seller)->post(route('seller.pages.update', $regulamin), [
            'content' => '<p>Moja treść regulaminu.</p>',
        ])->assertRedirect(route('seller.pages.edit', $regulamin))
            ->assertSessionHas('success');

        $regulamin->refresh();
        $this->assertSame('Regulamin', $regulamin->title); // tytuł nietknięty
        $this->assertTrue($regulamin->published);          // wciąż opublikowany
        $this->assertStringContainsString('Moja treść regulaminu', $regulamin->content);
    }

    public function test_seller_can_delete_a_regular_page(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $page = Page::factory()->for($shop)->create();

        $this->actingAs($seller)
            ->post(route('seller.pages.destroy', $page))
            ->assertRedirect(route('seller.pages.index'));

        $this->assertModelMissing($page);
    }

    public function test_system_page_cannot_be_deleted(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $regulamin = $shop->pages()->where('is_system', true)->first();

        $this->actingAs($seller)
            ->post(route('seller.pages.destroy', $regulamin))
            ->assertForbidden();

        $this->assertModelExists($regulamin);
    }

    public function test_reorder_sets_positions_and_ignores_foreign_ids(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $regulamin = $shop->pages()->where('is_system', true)->first();
        $a = Page::factory()->for($shop)->create(['position' => 1]);
        $b = Page::factory()->for($shop)->create(['position' => 2]);

        // Strona obcego sklepu — nie może wejść w kolejność.
        $foreign = Page::factory()->create();

        $this->actingAs($seller)
            ->postJson(route('seller.pages.reorder'), [
                'order' => [$b->id, $foreign->id, $regulamin->id, $a->id],
            ])
            ->assertOk();

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $regulamin->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
        // Obca strona nietknięta.
        $this->assertSame($foreign->position, $foreign->fresh()->position);
    }

    public function test_seller_cannot_touch_another_shops_page(): void
    {
        [$seller] = $this->sellerWithShop();
        $foreign = Page::factory()->create();

        $this->actingAs($seller)->get(route('seller.pages.edit', $foreign))->assertNotFound();
        $this->actingAs($seller)->post(route('seller.pages.update', $foreign), [
            'title' => 'Przejęcie', 'content' => '<p>x</p>',
        ])->assertNotFound();
        $this->actingAs($seller)->post(route('seller.pages.destroy', $foreign))->assertNotFound();
    }
}
