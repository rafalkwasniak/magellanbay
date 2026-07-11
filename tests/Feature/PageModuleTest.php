<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_shop_gets_a_system_regulamin_page(): void
    {
        $shop = Shop::factory()->create();

        $this->assertSame(1, $shop->pages()->count());

        $regulamin = $shop->pages()->first();
        $this->assertTrue($regulamin->is_system);
        $this->assertTrue($regulamin->published);
        $this->assertSame(0, $regulamin->position);
        $this->assertSame(config('pages.regulamin.title'), $regulamin->title);
        $this->assertSame(config('pages.regulamin.slug'), $regulamin->slug);
    }

    public function test_is_system_is_not_mass_assignable(): void
    {
        $shop = Shop::factory()->create();

        $page = $shop->pages()->create([
            'title' => 'O dostawie',
            'slug' => 'o-dostawie',
            'content' => '<p>Treść</p>',
            'position' => 5,
            'published' => true,
            'is_system' => true, // próba podszycia się pod stronę systemową
        ]);

        $this->assertFalse($page->fresh()->is_system);
    }

    public function test_storefront_path_follows_id_slug_convention(): void
    {
        $page = Page::factory()->create(['slug' => 'o-sklepie']);

        $this->assertSame('/informacje/'.$page->id.'-o-sklepie', $page->storefrontPath());
    }

    public function test_published_scope_returns_only_published_in_position_order(): void
    {
        $shop = Shop::factory()->create();
        // Sklep dostał już Regulamin (position 0) przez observer.
        Page::factory()->for($shop)->create(['position' => 3, 'title' => 'Trzecia']);
        Page::factory()->for($shop)->unpublished()->create(['position' => 1, 'title' => 'Ukryta']);
        Page::factory()->for($shop)->create(['position' => 2, 'title' => 'Druga']);

        $titles = $shop->pages()->published()->pluck('title')->all();

        $this->assertSame([config('pages.regulamin.title'), 'Druga', 'Trzecia'], $titles);
    }
}
