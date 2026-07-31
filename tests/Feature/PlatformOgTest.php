<?php

namespace Tests\Feature;

use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Karta social media dla stron PLATFORMY (nie storefrontów sprzedawców).
 *
 * Link do kramio.pl wklejony na Facebooka czy Messengera jest dla wielu osób
 * pierwszym kontaktem z produktem — bez tych znaczników widzą goły adres.
 *
 * Grafika jest plikiem statycznym o nazwie z losowym ciągiem (cache Facebooka
 * trzyma starą wersję tygodniami), więc łatwo o rozjazd: ktoś wgrywa nową
 * grafikę i zapomina zmienić `config/seo.php`, albo odwrotnie. Test pilnuje,
 * żeby wskazany plik naprawdę leżał na dysku w wymiarze, jakiego oczekują
 * serwisy społecznościowe.
 */
class PlatformOgTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_og_image_exists_on_disk(): void
    {
        $path = public_path(config('seo.og_image'));

        $this->assertFileExists($path, 'Grafika OG z config/seo.php nie istnieje w public/.');
    }

    public function test_og_image_has_dimensions_expected_by_social_networks(): void
    {
        [$width, $height] = getimagesize(public_path(config('seo.og_image')));

        $this->assertSame(1200, $width);
        $this->assertSame(630, $height);
    }

    public function test_platform_image_url_is_absolute(): void
    {
        // Facebook pobiera grafikę własnym robotem — ścieżki względnej nie rozwiąże.
        $this->assertStringStartsWith('http', Seo::platformImage());
    }

    public function test_landing_page_exposes_social_card(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('property="og:image" content="'.Seo::platformImage().'"', escape: false);
        $response->assertSee('property="og:title"', escape: false);
        $response->assertSee('property="og:description"', escape: false);
        $response->assertSee('name="twitter:card" content="summary_large_image"', escape: false);
    }

    public function test_landing_page_declares_canonical_url(): void
    {
        $response = $this->get('/');

        $response->assertSee('rel="canonical" href="'.url('/').'"', escape: false);
    }
}
