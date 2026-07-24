<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zliczanie ruchu storefrontu (Poziom 2). Middleware `RecordStorefrontTraffic`
 * inkrementuje dzienny agregat: wizyta raz na sesję, wyświetlenie produktu na
 * karcie produktu. Boty i podgląd właściciela nie liczą się jako klient.
 */
class TrafficRecordingTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

    private function shopWithProduct(): array
    {
        $shop = Shop::factory()->active()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'show_on_homepage' => true]);

        return [$shop, $product];
    }

    private function url(Shop $shop, string $path = '/'): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain').$path;
    }

    private function visits(Shop $shop): int
    {
        return (int) ShopStat::where('shop_id', $shop->id)->sum('visits');
    }

    public function test_visit_is_counted_once_per_session(): void
    {
        [$shop] = $this->shopWithProduct();

        $this->withHeaders(['User-Agent' => self::BROWSER_UA])->get($this->url($shop))->assertOk();
        $this->withHeaders(['User-Agent' => self::BROWSER_UA])->get($this->url($shop))->assertOk();

        // Dwie odsłony w jednej sesji = jedna wizyta.
        $this->assertSame(1, $this->visits($shop));
    }

    public function test_product_view_is_counted(): void
    {
        [$shop, $product] = $this->shopWithProduct();

        $this->withHeaders(['User-Agent' => self::BROWSER_UA])
            ->get($this->url($shop, $product->storefrontPath()))
            ->assertOk();

        $stat = ShopStat::where('shop_id', $shop->id)->first();
        $this->assertSame(1, $stat->product_views);
        $this->assertSame(1, $stat->visits);
    }

    public function test_bot_is_not_counted(): void
    {
        [$shop] = $this->shopWithProduct();

        $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
            ->get($this->url($shop))
            ->assertOk();

        $this->assertSame(0, $this->visits($shop));
    }

    public function test_owner_preview_is_not_counted(): void
    {
        [$shop] = $this->shopWithProduct();
        $owner = User::find($shop->owner_id);

        $this->actingAs($owner)
            ->withHeaders(['User-Agent' => self::BROWSER_UA])
            ->get($this->url($shop))
            ->assertOk();

        $this->assertSame(0, $this->visits($shop));
    }
}
