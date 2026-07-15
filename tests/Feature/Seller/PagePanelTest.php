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

    public function test_seller_can_promote_a_page_on_the_homepage(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.pages.store'), [
                'title' => 'Wywiady',
                'content' => '<div>Rozmowa dla „Wydawcy".</div>',
                'published' => '1',
                'show_on_homepage' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($shop->pages()->where('title', 'Wywiady')->firstOrFail()->show_on_homepage);
    }

    public function test_promoting_within_homepage_limit_succeeds(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Page::factory()->count(config('pages.homepage_promoted_limit') - 1)
            ->for($shop)->create(['show_on_homepage' => true]);

        $this->actingAs($seller)
            ->post(route('seller.pages.store'), [
                'title' => 'Ostatnia wyróżniona',
                'content' => '<div>Treść.</div>',
                'published' => '1',
                'show_on_homepage' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            $shop->pages()->where('title', 'Ostatnia wyróżniona')->where('show_on_homepage', true)->exists(),
        );
    }

    public function test_cannot_promote_more_than_homepage_limit(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $limit = config('pages.homepage_promoted_limit');
        Page::factory()->count($limit)->for($shop)->create(['show_on_homepage' => true]);

        $this->actingAs($seller)
            ->post(route('seller.pages.store'), [
                'title' => 'Za dużo',
                'content' => '<div>Treść.</div>',
                'published' => '1',
                'show_on_homepage' => '1',
            ])
            ->assertSessionHasErrors('show_on_homepage');

        // Walidacja zablokowała zapis — liczba wyróżnionych bez zmian.
        $this->assertSame($limit, $shop->pages()->where('show_on_homepage', true)->count());
    }

    public function test_already_promoted_page_can_be_saved_when_at_limit(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $limit = config('pages.homepage_promoted_limit');
        $target = Page::factory()->count($limit)->for($shop)
            ->create(['show_on_homepage' => true])->first();

        // Ponowny zapis już-wyróżnionej przy pełnym limicie — pomija samą siebie.
        $this->actingAs($seller)
            ->post(route('seller.pages.update', $target), [
                'title' => $target->title,
                'content' => '<div>Poprawiona treść.</div>',
                'published' => '1',
                'show_on_homepage' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->show_on_homepage);
    }

    /**
     * Flaga to zamiar sprzedawcy, `published` to widoczność — slot zajmuje flaga.
     * Inaczej dałoby się obejść sufit: wyróżnić szkice i opublikować je później.
     */
    public function test_unpublished_promoted_page_still_consumes_a_slot(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Page::factory()->count(config('pages.homepage_promoted_limit'))->for($shop)
            ->create(['show_on_homepage' => true, 'published' => false]);

        $this->actingAs($seller)
            ->post(route('seller.pages.store'), [
                'title' => 'Ponad sufit',
                'content' => '<div>Treść.</div>',
                'published' => '1',
                'show_on_homepage' => '1',
            ])
            ->assertSessionHasErrors('show_on_homepage');
    }

    /**
     * Regulamin jako zajawka-witryna na głównej nie ma sensu — formularz nie
     * pokazuje checkboxa, a kontroler i tak pola nie przyjmuje.
     */
    public function test_system_page_cannot_be_promoted(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $regulamin = $shop->pages()->where('is_system', true)->firstOrFail();

        $this->actingAs($seller)->post(route('seller.pages.update', $regulamin), [
            'content' => '<div>Treść regulaminu.</div>',
            'show_on_homepage' => '1',
        ]);

        $this->assertFalse($regulamin->fresh()->show_on_homepage);
    }

    /**
     * Pustej strony nie blokujemy — co sprzedawca pisze, to jego sprawa. Kafelka
     * i tak nie dostanie (Page::hasContent), więc dziury w siatce nie będzie.
     */
    public function test_page_without_content_can_be_promoted(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.pages.store'), [
                'title' => 'Pusta',
                'content' => '',
                'published' => '1',
                'show_on_homepage' => '1',
            ])
            ->assertSessionHasNoErrors();

        $page = $shop->pages()->where('title', 'Pusta')->firstOrFail();
        $this->assertTrue($page->show_on_homepage);
        $this->assertFalse($page->hasContent());
    }

    public function test_form_shows_taken_homepage_slots(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Page::factory()->for($shop)->create(['show_on_homepage' => true]);

        $this->actingAs($seller)
            ->get(route('seller.pages.create'))
            ->assertOk()
            ->assertSee('Zajęte 1 z '.config('pages.homepage_promoted_limit').' miejsc')
            ->assertSee('Wyróżnij na stronie głównej')
            ->assertSee('wyróżnij na stronie głównej'); // wskazówka w prawej kolumnie
    }

    /**
     * Regulaminu nie da się wyróżnić, więc jego formularz nie może o tym nawet
     * wspominać — ani checkboxem, ani wskazówką.
     */
    public function test_system_page_form_hides_promotion_entirely(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $regulamin = $shop->pages()->where('is_system', true)->firstOrFail();

        $this->actingAs($seller)
            ->get(route('seller.pages.edit', $regulamin))
            ->assertOk()
            ->assertDontSee('Wyróżnij na stronie głównej')
            ->assertDontSee('wyróżnij na stronie głównej')
            ->assertDontSee('miejsc na stronie głównej');
    }
}
