<?php

namespace Tests\Feature\Storefront;

use App\Jobs\GenerateShopOgImage;
use App\Models\Product;
use App\Models\Shop;
use App\Services\OgImageGenerator;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Karta sklepu do social mediów w nowej postaci: scena z monitorem, w którym
 * widać produkty sklepu, na gradiencie z jego palety, a obok logo albo nazwa,
 * zdanie o sklepie, zachęta i adres.
 *
 * Testy pilnują trzech rzeczy, na których łatwo się przewrócić:
 *  - że skrót w nazwie pliku reaguje na to, co WIDAĆ, i tylko na to (dodanie
 *    kolejnego produktu w tym samym układzie nie może unieważniać adresu, który
 *    ktoś już wkleił na Facebooka);
 *  - że składanie nie powtarza się, gdy nic się nie zmieniło — bo zlecamy je
 *    przy każdej edycji katalogu;
 *  - że zdanie o sklepie schodzi przez kaskadę źródeł aż do miasta.
 */
class ShopCardTest extends TestCase
{
    use RefreshDatabase;

    private function productWithPhoto(Shop $shop, string $name = 'Lemoniada'): Product
    {
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => $name,
            'is_active' => true,
        ]);

        $product->images()->create([
            'path' => UploadedFile::fake()->image('produkt.jpg', 800, 800)->store('products/'.$product->id, 'public'),
            'position' => 0,
        ]);

        return $product->fresh();
    }

    public function test_card_is_a_jpeg_in_social_media_dimensions(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create();
        $this->productWithPhoto($shop);

        $path = app(OgImageGenerator::class)->generate($shop->fresh());

        $this->assertStringEndsWith('.jpg', $path);

        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame(OgImageGenerator::WIDTH, $width);
        $this->assertSame(OgImageGenerator::HEIGHT, $height);
        $this->assertSame(IMAGETYPE_JPEG, $type);
    }

    public function test_adding_a_product_beyond_the_screen_keeps_the_address(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create();

        // Ekran mieści sześć kafli i tyle już jest — siódmy towar nie wchodzi
        // do kadru, więc adres karty musi zostać ten sam. Inaczej każda edycja
        // katalogu psułaby link udostępniony wcześniej na Facebooku.
        foreach (range(1, (int) config('seo.shop_card.max_products')) as $i) {
            $this->productWithPhoto($shop, 'Produkt '.$i);
        }

        $before = app(OgImageGenerator::class)->generate($shop->fresh());

        $this->productWithPhoto($shop, 'Produkt spoza kadru');

        $this->assertSame($before, app(OgImageGenerator::class)->generate($shop->fresh()));
    }

    public function test_adding_a_visible_product_gives_a_new_address(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create();
        $this->productWithPhoto($shop, 'Pierwszy');

        $single = app(OgImageGenerator::class)->generate($shop->fresh());

        // Drugi produkt wchodzi na ekran, więc karta wygląda inaczej i MUSI
        // dostać nowy adres.
        $this->productWithPhoto($shop, 'Drugi');

        $this->assertNotSame($single, app(OgImageGenerator::class)->generate($shop->fresh()));
    }

    public function test_swapping_a_photo_gives_a_new_address(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create();
        $product = $this->productWithPhoto($shop);

        $before = app(OgImageGenerator::class)->generate($shop->fresh());

        // Sprzedawca podmienia zdjęcie na ładniejsze. Liczba produktów się nie
        // zmienia, więc gdyby skrót patrzył tylko na nią, karta zostałaby stara
        // NA ZAWSZE — ręcznego odświeżania świadomie nie ma.
        $product->images()->delete();
        $product->images()->create([
            'path' => UploadedFile::fake()->image('lepsze.jpg', 900, 900)->store('products/'.$product->id, 'public'),
            'position' => 0,
        ]);

        $this->assertNotSame($before, app(OgImageGenerator::class)->generate($shop->fresh()));
    }

    public function test_repeated_generation_does_not_rebuild_the_file(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create();
        $this->productWithPhoto($shop);

        $generator = app(OgImageGenerator::class);
        $path = $generator->generate($shop->fresh());
        $stamp = Storage::disk('public')->lastModified($path);

        $generator->generate($shop->fresh());

        $this->assertSame($stamp, Storage::disk('public')->lastModified($path));
    }

    public function test_changing_the_theme_gives_a_new_address(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create(['template' => 'velvet_cloud', 'theme' => 'sky']);
        $this->productWithPhoto($shop);

        $before = app(OgImageGenerator::class)->generate($shop->fresh());

        $shop->forceFill(['template' => 'green_nook', 'theme' => 'forest'])->save();

        $this->assertNotSame($before, app(OgImageGenerator::class)->generate($shop->fresh()));
    }

    public function test_shop_without_photos_still_gets_a_card(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create(['name' => 'Sklep bez zdjęć']);

        // Ekran schodzi wtedy do koloru marki z nazwą — karta ma powstać zawsze,
        // bo link do sklepu może zostać udostępniony niezależnie od katalogu.
        $path = app(OgImageGenerator::class)->generate($shop);

        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_editing_a_product_asks_for_a_fresh_card(): void
    {
        Bus::fake();
        $shop = Shop::factory()->create();

        Product::factory()->create(['shop_id' => $shop->id]);

        Bus::assertDispatched(GenerateShopOgImage::class, fn ($job) => $job->shop->is($shop));
    }

    public function test_changing_the_description_asks_for_a_fresh_card(): void
    {
        $shop = Shop::factory()->create();
        Bus::fake();

        // Opis „O sklepie" trafia na kartę jako zdanie pod nazwą, więc jego
        // zmiana musi ją przerysować.
        $shop->forceFill(['description' => '<p>Zupełnie nowy opis sklepu.</p>'])->save();

        Bus::assertDispatched(GenerateShopOgImage::class);
    }

    public function test_tagline_prefers_a_whole_sentence_over_a_cut_off_fragment(): void
    {
        $shop = Shop::factory()->create([
            'meta_description' => 'Ręcznie robione lemoniady w małych partiach. Owoce wyciskane na zimno każdego ranka.',
        ]);

        // Całe zdanie czyta się lepiej niż urwany fragment z wielokropkiem.
        $this->assertSame('Ręcznie robione lemoniady w małych partiach.', Seo::shopTagline($shop));
    }

    public function test_tagline_falls_back_to_the_city_when_nothing_was_written(): void
    {
        $shop = Shop::factory()->create([
            'meta_description' => null,
            'description' => null,
            'city' => 'Piaseczno',
        ]);

        $this->assertSame('Sklep internetowy · Piaseczno', Seo::shopTagline($shop));
    }

    public function test_tagline_is_absent_when_there_is_nothing_true_to_say(): void
    {
        $shop = Shop::factory()->create([
            'meta_description' => null,
            'description' => null,
            'city' => null,
        ]);

        // Zamiast ogólnika w rodzaju „Zapraszamy do zakupów" zostawiamy pustkę —
        // nazwa sklepu stoi tuż obok, więc powtórzenie wyglądałoby na usterkę.
        $this->assertNull(Seo::shopTagline($shop));
    }
}
