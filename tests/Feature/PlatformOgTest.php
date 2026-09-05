<?php

namespace Tests\Feature;

use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Karta social media dla stron PLATFORMY (nie storefrontów sprzedawców).
 *
 * Grafika jest opcjonalna i oba stany są poprawne:
 *
 * - USTAWIONA — wtedy plik musi naprawdę leżeć na dysku, w wymiarze, którego
 *   oczekują serwisy. Łatwo tu o rozjazd: ktoś wgrywa nową grafikę i zapomina
 *   zmienić `config/seo.php`, albo odwrotnie.
 *
 * - PUSTA — wdrożenie dedykowane, zanim klient dostarczy własne materiały.
 *   Wtedy znaczników `og:image` NIE MA WCALE. Pusty atrybut serwisy czytają
 *   jak zepsuty plik, a podstawienie NASZEJ grafiki znaczyłoby, że link do
 *   sklepu klienta wyświetla się na Facebooku z cudzą marką.
 */
class PlatformOgTest extends TestCase
{
    use RefreshDatabase;

    private function skonfigurowana(): ?string
    {
        $path = trim((string) config('seo.og_image'));

        return $path !== '' ? $path : null;
    }

    public function test_configured_og_image_exists_on_disk(): void
    {
        $path = $this->skonfigurowana();

        if ($path === null) {
            $this->assertNull(Seo::platformImage(), 'Brak grafiki w configu ma dawać null, nie adres donikąd.');

            return;
        }

        $this->assertFileExists(public_path($path), 'Grafika OG z config/seo.php nie istnieje w public/.');
    }

    public function test_og_image_has_dimensions_expected_by_social_networks(): void
    {
        $path = $this->skonfigurowana();

        if ($path === null) {
            $this->markTestSkipped('Karta platformy wyłączona — nie ma czego mierzyć.');
        }

        [$width, $height] = getimagesize(public_path($path));

        $this->assertSame(1200, $width);
        $this->assertSame(630, $height);
    }

    public function test_og_image_stays_light_enough_to_serve(): void
    {
        $path = $this->skonfigurowana();

        if ($path === null) {
            $this->markTestSkipped('Karta platformy wyłączona — nie ma czego ważyć.');
        }

        // Grafike pobiera robot przy KAZDYM pierwszym udostepnieniu linku, a my
        // siedzimy na shared hoscie. Pierwsza wersja wazyla 1 MB jako PNG; ta
        // sama grafika w JPG to ~165 KB przy nieodroznialnej jakosci. Prog jest
        // z zapasem — ma zlapac powrot do nieskompresowanego pliku, nie czepiac
        // sie kilkudziesieciu kilobajtow.
        $kilobytes = filesize(public_path($path)) / 1024;

        $this->assertLessThan(400, $kilobytes, 'Grafika OG urosla — skompresuj ja przed wgraniem.');
    }

    public function test_platform_image_url_is_absolute_when_configured(): void
    {
        if ($this->skonfigurowana() === null) {
            $this->assertNull(Seo::platformImage());

            return;
        }

        // Facebook pobiera grafikę własnym robotem — ścieżki względnej nie rozwiąże.
        $this->assertStringStartsWith('http', Seo::platformImage());
    }

    /**
     * Karta bez obrazka nadal ma tytuł, opis i adres — znika wyłącznie grafika.
     * Serwis pokaże wtedy link z opisem, a nie ramkę z zepsutym plikiem.
     */
    public function test_landing_page_exposes_social_card(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('property="og:title"', escape: false);
        $response->assertSee('property="og:description"', escape: false);

        if (Seo::platformImage() === null) {
            $response->assertDontSee('property="og:image"', escape: false);
            $response->assertDontSee('name="twitter:card"', escape: false);

            return;
        }

        $response->assertSee('property="og:image" content="'.Seo::platformImage().'"', escape: false);
        $response->assertSee('name="twitter:card" content="summary_large_image"', escape: false);
    }

    /**
     * Pusty `og:image` jest gorszy niż jego brak — serwisy próbują pobrać plik
     * spod adresu katalogu i pokazują ramkę z błędem. Ten test pilnuje, że przy
     * wyłączonej karcie taki atrybut nie wychodzi NIGDZIE.
     */
    public function test_empty_og_image_attribute_is_never_emitted(): void
    {
        config()->set('seo.og_image', null);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('property="og:image" content=""', escape: false);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('property="og:image" content=""', escape: false);
    }

    public function test_landing_page_declares_canonical_url(): void
    {
        $response = $this->get('/');

        $response->assertSee('rel="canonical" href="'.url('/').'"', escape: false);
    }
}
