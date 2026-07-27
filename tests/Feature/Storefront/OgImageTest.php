<?php

namespace Tests\Feature\Storefront;

use App\Jobs\GenerateShopOgImage;
use App\Models\Shop;
use App\Services\OgImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Grafika sklepu do social mediów (Open Graph, 1200×630). Powód istnienia:
 * logo sprzedawcy bywa wąskie albo przezroczyste i jako `og:image` wygląda na
 * Facebooku jak zgubiony znaczek. Tu dostaje własne płótno z marginesami.
 *
 * Liczona RAZ i zapisana — składanie obrazka przy każdym żądaniu byłoby na
 * shared hoście absurdem.
 */
class OgImageTest extends TestCase
{
    use RefreshDatabase;

    private function logo(Shop $shop, int $size = 200): void
    {
        Storage::fake('public');

        $path = UploadedFile::fake()->image('logo.png', $size, $size)
            ->store('shops/'.$shop->id, 'public');

        $shop->forceFill(['logo_path' => $path])->save();
    }

    public function test_generated_card_has_social_media_dimensions(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create(['name' => 'I like my bike']);
        $this->logo($shop);

        $path = app(OgImageGenerator::class)->generate($shop->fresh());

        $image = imagecreatefromstring((string) Storage::disk('public')->get($path));
        $this->assertSame(OgImageGenerator::WIDTH, imagesx($image));
        $this->assertSame(OgImageGenerator::HEIGHT, imagesy($image));
    }

    public function test_shop_without_a_logo_still_gets_a_card(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create(['name' => 'Kwiaciarnia Zażółć Gęślą Jaźń', 'logo_path' => null]);

        // Wariant zastępczy to nazwa na kolorze marki — ma wyglądać jak decyzja,
        // nie jak brak grafiki. Polskie znaki muszą się narysować (krój z repo).
        $path = app(OgImageGenerator::class)->generate($shop);

        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertGreaterThan(1000, Storage::disk('public')->size($path));
    }

    public function test_changing_the_logo_changes_the_file_name(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create(['name' => 'Sklep']);
        $this->logo($shop);

        $first = app(OgImageGenerator::class)->generate($shop->fresh());
        $shop->fresh()->forceFill(['name' => 'Sklep pod różą'])->save();
        $second = app(OgImageGenerator::class)->generate($shop->fresh());

        // Facebook cache'uje grafikę PO ADRESIE — bez zmiany nazwy pliku
        // sprzedawca tygodniami widziałby starą wersję.
        $this->assertNotSame($first, $second);
        // Stara wersja nie zostaje jako sierota.
        $this->assertFalse(Storage::disk('public')->exists($first));
    }

    public function test_new_shop_gets_its_card_queued(): void
    {
        Bus::fake();

        $shop = Shop::factory()->create();

        Bus::assertDispatched(GenerateShopOgImage::class, fn ($job) => $job->shop->is($shop));
    }

    public function test_card_is_redrawn_only_when_something_visible_changes(): void
    {
        $shop = Shop::factory()->create();
        Bus::fake();

        // Nazwa jest na grafice → przerysowujemy.
        $shop->update(['name' => 'Nowa nazwa']);
        Bus::assertDispatched(GenerateShopOgImage::class);

        Bus::fake();

        // Numer konta nie jest → nie ma czego przerysowywać.
        $shop->update(['bank_account_number' => '11111111111111111111111111']);
        Bus::assertNotDispatched(GenerateShopOgImage::class);
    }

    public function test_storefront_uses_the_generated_card(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create(['status' => 'active']);
        $shop->forceFill(['og_image_path' => 'og/'.$shop->id.'/abc123.png'])->save();

        $this->get('http://'.$shop->slug.'.'.config('tenancy.central_domain'))
            ->assertOk()
            ->assertSee('og/'.$shop->id.'/abc123.png', escape: false);
    }
}
