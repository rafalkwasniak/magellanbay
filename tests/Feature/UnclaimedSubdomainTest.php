<?php

namespace Tests\Feature;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\ReservedSlug;
use App\Models\Shop;
use App\Services\SubdomainAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Subdomena, pod którą nie stoi żaden sklep. Zamiast ślepego 404 pokazujemy
 * stronę zachęcającą do zajęcia adresu — ale „wolny" wolno napisać tylko wtedy,
 * gdy rejestracja naprawdę ten adres przyjmie.
 */
class UnclaimedSubdomainTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $slug, string $path = '/'): string
    {
        return 'http://'.$slug.'.'.config('tenancy.central_domain').$path;
    }

    public function test_free_subdomain_shows_invitation_with_that_address(): void
    {
        $response = $this->get($this->url('kwiatki-u-ani'));

        // Status zostaje 404: pod wildcardem istnieje nieskończenie wiele takich
        // adresów i żaden nie ma prawa trafić do indeksu.
        $response->assertNotFound()
            ->assertSee('kwiatki-u-ani.'.config('tenancy.central_domain'))
            ->assertSee('Ten adres jest wolny')
            ->assertSee('Zajmij ten adres za darmo')
            ->assertHeader('X-Robots-Tag', 'noindex');
    }

    public function test_invitation_links_to_central_registration_carrying_the_address(): void
    {
        // Pułapka, którą ta strona musi omijać: `route('register')` renderowane
        // NA SUBDOMENIE zbuduje link do storefrontu, gdzie /rejestracja zakłada
        // konto klienta sklepu, a nie sklep.
        $this->get($this->url('kwiatki-u-ani'))
            ->assertSee(config('app.url').'/rejestracja?adres=kwiatki-u-ani', false)
            ->assertDontSee('http://kwiatki-u-ani.'.config('tenancy.central_domain').'/rejestracja', false);
    }

    public function test_quarantined_address_is_not_offered_as_free(): void
    {
        // Adres po usuniętym sklepie: stare linki i maile do klientów wciąż
        // prowadzą tutaj, więc nie wolno go nikomu obiecać.
        ReservedSlug::create([
            'slug' => 'po-sklepie',
            'released_at' => Carbon::now()->addDays(30),
        ]);

        $this->get($this->url('po-sklepie'))
            ->assertNotFound()
            ->assertSee('Nie ma tu sklepu')
            ->assertSee('Załóż sklep za darmo')
            ->assertDontSee('Ten adres jest wolny');
    }

    public function test_expired_quarantine_frees_the_address_again(): void
    {
        ReservedSlug::create([
            'slug' => 'znowu-wolny',
            'released_at' => Carbon::now()->subDay(),
        ]);

        $this->get($this->url('znowu-wolny'))
            ->assertSee('Ten adres jest wolny');
    }

    public function test_reserved_platform_label_is_not_offered_as_free(): void
    {
        $this->get($this->url('panel'))
            ->assertNotFound()
            ->assertSee('Nie ma tu sklepu')
            ->assertDontSee('Ten adres jest wolny');
    }

    public function test_too_short_address_is_not_offered_as_free(): void
    {
        // Krótsze niż minimum z config('tenancy.subdomain') — rejestracja by je
        // odrzuciła, więc strona nie może go obiecywać.
        $this->get($this->url('ab'))
            ->assertNotFound()
            ->assertDontSee('Ten adres jest wolny');
    }

    public function test_shop_awaiting_deletion_shows_no_shop_and_no_invitation(): void
    {
        $shop = Shop::factory()->active()->create([
            'slug' => 'zamykany',
            'name' => 'Sklep Zamykany',
            'deletion_scheduled_at' => Carbon::now()->addDays(7),
        ]);

        $this->get($this->url($shop->slug))
            ->assertNotFound()
            ->assertDontSee('Sklep Zamykany')
            ->assertDontSee('Ten adres jest wolny');
    }

    public function test_existing_shop_still_renders_its_storefront(): void
    {
        $shop = Shop::factory()->active()->create(['name' => 'Kwiaciarnia Bukiet']);

        $this->get($this->url($shop->slug))
            ->assertOk()
            ->assertSee('Kwiaciarnia Bukiet')
            ->assertDontSee('Ten adres jest wolny');
    }

    public function test_robots_and_sitemap_stay_plain_404_on_unclaimed_subdomain(): void
    {
        // Plik tekstowy z HTML-em w środku jest gorszy niż brak pliku.
        foreach (['/robots.txt', '/sitemap.xml'] as $path) {
            $this->get($this->url('kwiatki-u-ani', $path))
                ->assertNotFound()
                ->assertDontSee('Zajmij ten adres');
        }
    }

    public function test_www_redirects_to_central_keeping_the_path(): void
    {
        // `www` pasuje do wzorca {shop}.{central_domain}, więc bez tego kończyło
        // się 404 — na adresie, który ludzie wpisują z palca.
        $this->get($this->url('www', '/logowanie'))
            ->assertRedirect(config('app.url').'/logowanie');
    }

    public function test_availability_agrees_with_registration_validation(): void
    {
        // Dwa źródła prawdy o tym samym adresie muszą mówić to samo — inaczej
        // strona obiecuje adres, którego formularz nie przyjmie.
        ReservedSlug::create(['slug' => 'kwarantanna', 'released_at' => Carbon::now()->addDays(30)]);
        Shop::factory()->create(['slug' => 'zajety-sklep']);

        $availability = app(SubdomainAvailability::class);

        foreach (['wolny-adres', 'kwarantanna', 'zajety-sklep', 'panel', 'ab', 'Zły Adres'] as $slug) {
            $this->assertSame(
                $this->passesRegistrationRules($slug),
                $availability->isFree($slug),
                "Rozjazd oceny adresu „{$slug}” między stroną wolnej subdomeny a rejestracją."
            );
        }
    }

    /**
     * Reguła `slug` z RegisterRequest, odpytana w oderwaniu od formularza.
     */
    private function passesRegistrationRules(string $slug): bool
    {
        $rules = (new RegisterRequest)->rules();

        return Validator::make(['slug' => $slug], ['slug' => $rules['slug']])->passes();
    }
}
