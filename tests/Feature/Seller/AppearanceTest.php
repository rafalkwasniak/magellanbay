<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    private function sellerWithShop(array $shopAttributes = []): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(array_merge(['owner_id' => $seller->id], $shopAttributes));

        return [$seller, $shop];
    }

    public function test_seller_can_view_appearance_page(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->get(route('seller.appearance.edit'))
            ->assertOk()
            ->assertSee('Logo sklepu');
    }

    public function test_seller_can_upload_logo(): void
    {
        Storage::fake('public');
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.appearance.update'), [
                'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
            ])
            ->assertRedirect(route('seller.appearance.edit'))
            ->assertSessionHas('success');

        $shop->refresh();
        $this->assertNotNull($shop->logo_path);
        Storage::disk('public')->assertExists($shop->logo_path);
    }

    public function test_logo_can_be_removed(): void
    {
        Storage::fake('public');
        [$seller, $shop] = $this->sellerWithShop();

        $path = UploadedFile::fake()->image('old.png', 200, 200)->store('shops/'.$shop->id, 'public');
        $shop->update(['logo_path' => $path]);

        $this->actingAs($seller)
            ->post(route('seller.appearance.update'), ['remove_logo' => '1']);

        $this->assertNull($shop->fresh()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_image_logo_is_rejected(): void
    {
        Storage::fake('public');
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.appearance.update'), [
                'logo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_appearance_page_shows_template_picker(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->get(route('seller.appearance.edit'))
            ->assertOk()
            ->assertSee('Kolory i szablon')
            ->assertSee('Aksamitna chmurka')
            ->assertSee('Zielony zakątek')
            ->assertSee('Grafitowy wieczór');
    }

    public function test_seller_can_choose_template_and_palette(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.appearance.update'), [
                'template' => 'green_nook',
                'palettes' => ['green_nook' => 'moss'],
            ])
            ->assertRedirect(route('seller.appearance.edit'))
            ->assertSessionHas('success');

        $shop->refresh();
        $this->assertSame('green_nook', $shop->template);
        $this->assertSame('moss', $shop->themePalette());
    }

    public function test_only_the_chosen_templates_palette_is_applied(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        // Formularz wysyła paletę każdego szablonu; bierzemy tylko tę spod klucza
        // wybranego szablonu, resztę ignorujemy.
        $this->actingAs($seller)
            ->post(route('seller.appearance.update'), [
                'template' => 'graphite_dusk',
                'palettes' => [
                    'velvet_cloud' => 'lavender',
                    'graphite_dusk' => 'rose',
                ],
            ]);

        $shop->refresh();
        $this->assertSame('graphite_dusk', $shop->template);
        $this->assertSame('rose', $shop->themePalette());
    }

    public function test_unknown_template_is_rejected(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $original = $shop->fresh()->template;

        $this->actingAs($seller)
            ->post(route('seller.appearance.update'), ['template' => 'does_not_exist'])
            ->assertSessionHasErrors('template');

        $this->assertSame($original, $shop->fresh()->template);
    }

    public function test_palette_not_belonging_to_template_is_rejected(): void
    {
        [$seller] = $this->sellerWithShop();

        // 'moss' należy do green_nook, nie do velvet_cloud.
        $this->actingAs($seller)
            ->post(route('seller.appearance.update'), [
                'template' => 'velvet_cloud',
                'palettes' => ['velvet_cloud' => 'moss'],
            ])
            ->assertSessionHasErrors('palettes.velvet_cloud');
    }

    public function test_preview_uses_a_real_product_image_when_available(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $product->images()->create(['path' => 'shops/'.$shop->id.'/sample.webp', 'position' => 1]);

        $this->actingAs($seller)
            ->get(route('seller.appearance.edit'))
            ->assertOk()
            ->assertSee('shops/'.$shop->id.'/sample.webp')     // zdjęcie w podglądzie
            ->assertSee('alt="Podgląd produktu"', false);       // realny <img>, nie placeholder
    }

    public function test_preview_falls_back_to_placeholder_without_images(): void
    {
        [$seller] = $this->sellerWithShop();

        // Bez zdjęć nie ma realnego <img> — zostaje placeholder (marker w JS
        // `data-preview-img` nie wystarcza, bo żyje w skrypcie zawsze).
        $this->actingAs($seller)
            ->get(route('seller.appearance.edit'))
            ->assertOk()
            ->assertDontSee('alt="Podgląd produktu"', false);
    }
}
