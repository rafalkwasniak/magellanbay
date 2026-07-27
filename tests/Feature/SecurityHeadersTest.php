<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nagłówki bezpieczeństwa na każdej odpowiedzi webowej. Audyt ursalogic
 * (16.07.2026) dał platformie ocenę E właśnie za ich brak — 55/100 identycznie
 * na wszystkich 100 podstronach, bo problem był systemowy, nie w konkretnej
 * stronie. Te testy pilnują, żeby nie zniknęły po cichu przy refaktorze.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_pages_carry_the_headers(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_storefront_pages_carry_the_headers(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);

        $this->get('http://'.$shop->slug.'.'.config('tenancy.central_domain'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_panel_pages_carry_the_headers(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);

        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_geolocation_stays_allowed_for_the_parcel_locker_map(): void
    {
        $response = $this->get('/')->assertOk();

        // Mapa paczkomatów InPost szuka punktów w pobliżu kupującego — zakaz
        // geolokalizacji wywróciłby wybór paczkomatu w kasie.
        $this->assertStringContainsString('geolocation=(self)', $response->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_hsts_only_over_https(): void
    {
        // Adres wprost po http — testy chodzą domyślnie po APP_URL, czyli https.
        $this->get('http://'.config('tenancy.central_domain'))
            ->assertHeaderMissing('Strict-Transport-Security');

        $secure = $this->get('https://'.config('tenancy.central_domain'));

        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $secure->headers->get('Strict-Transport-Security'),
        );
    }

    public function test_hsts_never_announces_preload_by_default(): void
    {
        // Wpis na listę preload przeglądarek jest praktycznie nieodwracalny —
        // nie może się włączyć przypadkiem.
        $header = $this->get('https://'.config('tenancy.central_domain'))
            ->headers->get('Strict-Transport-Security');

        $this->assertStringNotContainsString('preload', (string) $header);
    }

    public function test_header_can_be_switched_off_from_config(): void
    {
        config(['security.headers.X-Frame-Options' => null]);

        $this->get('/')->assertHeaderMissing('X-Frame-Options');
    }
}
