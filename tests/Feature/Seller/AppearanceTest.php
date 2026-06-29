<?php

namespace Tests\Feature\Seller;

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
}
