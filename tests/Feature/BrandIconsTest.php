<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ikonki marki w `<head>`. Test-strażnik: brakujący `apple-touch-icon` nie daje
 * ŻADNEGO objawu na desktopie — widać go dopiero na ekranie startowym iPhone'a,
 * czyli tam, gdzie nikt nie zagląda przy każdym wdrożeniu.
 */
class BrandIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_apple_touch_icon_file_exists_and_is_opaque(): void
    {
        $path = public_path('apple-touch-icon.png');

        $this->assertFileExists($path);

        // iOS ignoruje kanał alfa i podkłada CZARNE tło. Plik z przezroczystością
        // dałby czarne narożniki wokół ikonki na ekranie startowym.
        $info = getimagesize($path);

        $this->assertSame([180, 180], [$info[0], $info[1]]);
        $this->assertSame(IMAGETYPE_PNG, $info[2]);
    }

    public function test_landing_links_both_icons(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('rel="icon"', false)
            ->assertSee('apple-touch-icon.png', false);
    }

    public function test_storefront_links_the_apple_touch_icon(): void
    {
        $shop = Shop::factory()->active()->create();
        Product::factory()->for($shop)->create();

        $this->get('http://'.$shop->slug.'.'.config('tenancy.central_domain'))
            ->assertOk()
            ->assertSee('apple-touch-icon.png', false);
    }

    public function test_login_screen_links_the_apple_touch_icon(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('apple-touch-icon.png', false);
    }
}
