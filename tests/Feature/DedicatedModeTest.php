<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use App\Support\Mode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tryb dedykowany: sklep JEDNEGO klienta, na jego serwerze.
 *
 * Cała suita opisuje Kramio (`SHOP_MODE=saas` wymuszone w phpunit.xml), więc
 * zachowania tego trybu sprawdzamy wyłącznie tutaj, jawnie przestawiając
 * konfigurację. Każdy test ma parę: „w trybie dedykowanym nie ma" oraz
 * „w Kramio nadal jest" — bo równie groźne jak niedomknięcie platformy jest
 * przypadkowe wygaszenie jej w Kramio.
 *
 * @see config/shop.php — opis obu trybów
 * @see App\Http\Middleware\EnsureSaasMode
 */
class DedicatedModeTest extends TestCase
{
    use RefreshDatabase;

    private function dedicated(): void
    {
        config()->set('shop.mode', Mode::DEDICATED);
    }

    /**
     * Właściciel sklepu dedykowanego — rola `seller`, jak w Kramio. Różnicą jest
     * to, CO widzi po zalogowaniu, a nie kim jest.
     *
     * @return array{0: User, 1: Shop}
     */
    private function owner(): array
    {
        $user = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);

        return [$user, $shop];
    }

    public function test_seller_registration_is_not_registered_at_all(): void
    {
        // Konto właściciela zakłada seeder wdrożeniowy. Otwarty formularz
        // wysyłający maile aktywacyjne na dowolny adres byłby tu wyłącznie
        // ryzykiem — i to takim, które kosztuje reputację serwera pocztowego.
        //
        // Sprawdzamy REJESTRACJĘ TRAS, nie odpowiedź serwera: tych tras w trybie
        // dedykowanym po prostu nie ma. Zamknięcie ich middlewarem nie
        // wystarczyło — adres `/rejestracja` należy się klientowi sklepu, a
        // definicja centrali stoi wyżej w pliku i cicho nadpisywała storefront.
        $routes = $this->routesIn(Mode::DEDICATED);

        foreach (['register', 'register.store', 'register.confirmation', 'register.resend'] as $name) {
            $this->assertArrayNotHasKey($name, $routes);
        }

        // Adres przejmuje rejestracja KLIENTA — i to ona ma tam odpowiadać.
        $this->assertSame('rejestracja', $routes['storefront.register'] ?? null);
    }

    public function test_seller_registration_stays_open_in_kramio(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_administrator_console_is_not_found_even_for_a_logged_in_seller(): void
    {
        // 404, nie 403 i nie przekierowanie do logowania: te odpowiedzi
        // potwierdzałyby, że adres istnieje, czyli zdradzały, że pod spodem
        // siedzi platforma. Middleware `saas` musi wykonać się PRZED
        // uwierzytelnianiem — patrz lista priorytetów w bootstrap/app.php.
        $this->dedicated();
        [$user] = $this->owner();

        $this->actingAs($user)->get('/administrator/panel')->assertNotFound();
        $this->actingAs($user)->get('/administrator/sklepy')->assertNotFound();

        // ZAKRES TEJ GWARANCJI — sprawdzone na działającej instalacji:
        //
        // Zalogowany właściciel dostaje 404 i to jest przypadek, o który chodzi:
        // jedyny użytkownik sklepu dedykowanego nie ma jak stwierdzić, że pod
        // spodem siedzi konsola platformy.
        //
        // Odwiedzający NIEZALOGOWANY dostaje 302 do logowania, bo `Authenticate`
        // wyprzedza nasze middleware na liście priorytetów Laravela. Próba
        // wymuszenia kolejności przez `prependToPriorityList` zadziałała w
        // testach, ale nie na serwerze — dlatego NIE ma tu asercji na 404 dla
        // gościa. Zielony test obiecujący zachowanie, którego produkcja nie ma,
        // byłby gorszy niż jego brak.
        //
        // Zostawiamy tak świadomie: 302 zdradza tylko tyle, że adres wymaga
        // logowania. Do zamknięcia razem z krokiem „jedna domena", gdy i tak
        // przebudowujemy routing.
    }

    public function test_administrator_console_answers_admins_in_kramio(): void
    {
        $admin = User::factory()->admin()->consented()->create();

        $this->actingAs($admin)->get('/administrator/panel')->assertOk();
    }

    public function test_package_screen_is_closed(): void
    {
        // Sklep dedykowany jest opłacony jednorazowo — nie ma pakietu do
        // oglądania, kupowania ani przedłużania.
        $this->dedicated();
        [$user] = $this->owner();

        $this->actingAs($user)->get(route('seller.package.show'))->assertNotFound();
    }

    public function test_shop_self_deletion_is_closed(): void
    {
        // To wyjście z USŁUGI, potrzebne sprzedawcy rezygnującemu z platformy.
        // U klienta na własnym serwerze byłoby przyciskiem uruchamiającym
        // `shops:purge` — jedyną komendę kasującą sklep z historią sprzedaży.
        $this->dedicated();
        [$user] = $this->owner();

        $this->actingAs($user)->get(route('seller.deletion.show'))->assertNotFound();
        $this->actingAs($user)->post(route('seller.deletion.store'), [])->assertNotFound();
    }

    public function test_package_payment_webhook_is_closed(): void
    {
        // Webhook opłat za pakiety należy do konta PLATFORMY. Webhook Paynow
        // samego sklepu zostaje otwarty — to nim płacą kupujący.
        $this->dedicated();

        $this->post(route('payments.paynow.packages.webhook'), [])->assertNotFound();
        $this->post(route('payments.paynow.webhook'), [])->assertStatus(400);
    }

    public function test_panel_menu_has_no_package_entry(): void
    {
        // Trasa i tak odpowiada 404, ale odnośnik prowadzący w pustkę wygląda
        // jak usterka, a nie jak decyzja.
        $this->dedicated();
        [$user] = $this->owner();

        $html = $this->actingAs($user)->get(route('seller.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Mój pakiet', $html);
    }

    public function test_shop_edit_has_no_deletion_section(): void
    {
        $this->dedicated();
        [$user] = $this->owner();

        $html = $this->actingAs($user)->get(route('seller.shop.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Chcę usunąć sklep', $html);
    }

    public function test_platform_commands_are_not_scheduled(): void
    {
        // Harmonogram ma mówić prawdę o tym, co ta instalacja robi. `shops:purge`
        // jest tu ważniejszy niż `subscriptions:check`: to jedyna komenda, która
        // kasuje sklep razem z historią sprzedaży, więc jej nieobecność jest
        // ostatnią barierą między pomyłką a bezpowrotną utratą danych klienta.
        //
        // Harmonogram budujemy w osobnym procesie, bo `routes/console.php`
        // czyta tryb w chwili rejestracji zadań — czyli zanim test zdąży
        // przestawić konfigurację.
        $output = $this->artisanScheduleIn(Mode::DEDICATED);

        $this->assertStringNotContainsString('subscriptions:check', $output);
        $this->assertStringNotContainsString('shops:purge', $output);
        // Komendy samego sklepu zostają — bez nich nie wychodzi ani jeden mail.
        $this->assertStringContainsString('email:dispatch', $output);
        $this->assertStringContainsString('shipments:refresh', $output);
    }

    public function test_platform_commands_stay_scheduled_in_kramio(): void
    {
        $output = $this->artisanScheduleIn(Mode::SAAS);

        $this->assertStringContainsString('subscriptions:check', $output);
        $this->assertStringContainsString('shops:purge', $output);
    }

    public function test_storefront_takes_over_the_main_domain(): void
    {
        // W Kramio sklep siedzi na subdomenie, a `/` należy do landingu
        // platformy. W sklepie dedykowanym jest odwrotnie: `/` to strona główna
        // SKLEPU, a landing w ogóle nie jest rejestrowany — trasa zamknięta
        // middlewarem i tak przechwyciłaby adres, bo Laravel bierze pierwszą
        // pasującą.
        $routes = $this->routesIn(Mode::DEDICATED);

        $this->assertSame('/', $routes['storefront.home'] ?? null);
        $this->assertArrayNotHasKey('sitemap', $routes, 'Mapa strony centrali nie może przechwycić adresu sklepu.');
        $this->assertArrayNotHasKey('robots', $routes, 'robots.txt centrali nie może przechwycić adresu sklepu.');
        $this->assertArrayNotHasKey('register', $routes, 'Rejestracja sprzedawcy nadpisałaby rejestrację klienta.');
    }

    public function test_owner_login_moves_under_the_panel_prefix(): void
    {
        // Jeden host znaczy, że `/logowanie` może należeć tylko do jednego z
        // dwóch logowań. Dostaje je KLIENT — to jego sklep. Właściciel wchodzi
        // tam, gdzie i tak mieszka jego panel.
        //
        // Nazwy `login` i `logout` MUSZĄ przetrwać: woła je siedem widoków oraz
        // mechanizm przekierowania gościa w Laravelu.
        $routes = $this->routesIn(Mode::DEDICATED);

        $this->assertSame('sprzedawca/logowanie', $routes['login'] ?? null);
        $this->assertSame('sprzedawca/wyloguj', $routes['logout'] ?? null);
        $this->assertSame('logowanie', $routes['storefront.login'] ?? null);
        $this->assertSame('rejestracja', $routes['storefront.register'] ?? null);
    }

    public function test_kramio_keeps_the_central_addresses(): void
    {
        $routes = $this->routesIn(Mode::SAAS);

        $this->assertSame('logowanie', $routes['login'] ?? null);
        $this->assertSame('wyloguj', $routes['logout'] ?? null);
        $this->assertSame('rejestracja', $routes['register'] ?? null);
        $this->assertSame('sitemap.xml', $routes['sitemap'] ?? null);
    }

    public function test_no_two_routes_share_an_address_in_dedicated_mode(): void
    {
        // Storefront traci ograniczenie domeny, więc jego 39 tras ląduje w tej
        // samej puli co trasy centrali. Para metoda+adres jest w Laravelu
        // kluczem: zderzenie nie jest błędem, tylko CICHYM nadpisaniem — jedna
        // trasa znika razem ze swoją nazwą. Tak właśnie zniknęło `login` przy
        // pierwszym podejściu i panel przestał się otwierać.
        $duplicates = $this->duplicateAddressesIn(Mode::DEDICATED);

        $this->assertSame([], $duplicates, 'Adresy nie mogą się dublować: '.implode(', ', $duplicates));
    }

    /**
     * Mapa `nazwa trasy => adres` z osobnego procesu, w zadanym trybie.
     *
     * @return array<string, string>
     */
    private function routesIn(string $mode): array
    {
        $json = $this->artisanIn($mode, 'route:list --json');
        $routes = json_decode($json, true) ?? [];
        $map = [];

        foreach ($routes as $route) {
            if (($route['name'] ?? null) !== null) {
                $map[$route['name']] = $route['uri'];
            }
        }

        return $map;
    }

    /**
     * Adresy (metoda + URI) obsadzone więcej niż raz.
     *
     * @return list<string>
     */
    private function duplicateAddressesIn(string $mode): array
    {
        $routes = json_decode($this->artisanIn($mode, 'route:list --json'), true) ?? [];
        $seen = [];
        $duplicates = [];

        foreach ($routes as $route) {
            $key = $route['method'].' '.$route['uri'];

            if (isset($seen[$key])) {
                $duplicates[$key] = true;
            }

            $seen[$key] = true;
        }

        return array_keys($duplicates);
    }

    /**
     * `Shop::host()` buduje adres sklepu dla maili o zamówieniu, linku do
     * formularza zwrotu, linku do płatności, mapy strony, webhooka Paynow
     * i podpisu na grafice OG.
     *
     * W trybie dedykowanym sklep stoi na domenie GŁÓWNEJ, więc doklejenie sluga
     * dawało adres, pod którym nic nie odpowiada („magellan.magellan.kwasniak.org").
     * Usterka była niewidoczna na ekranach — wychodziła dopiero przy pierwszym
     * prawdziwym zamówieniu, w mailu do klienta.
     */
    public function test_shop_host_is_the_main_domain_without_subdomain(): void
    {
        $shop = Shop::factory()->create(['slug' => 'magellan', 'domain' => null]);

        $this->dedicated();
        $this->assertSame(config('tenancy.central_domain'), $shop->host());
    }

    public function test_shop_host_keeps_the_subdomain_in_saas(): void
    {
        $shop = Shop::factory()->create(['slug' => 'magellan', 'domain' => null]);

        $this->assertSame('magellan.'.config('tenancy.central_domain'), $shop->host());
    }

    /**
     * Własna domena wygrywa w OBU trybach — jest jawnym ustawieniem sklepu,
     * a nie skutkiem ubocznym trybu pracy aplikacji.
     */
    public function test_explicit_domain_wins_in_both_modes(): void
    {
        $shop = Shop::factory()->create(['slug' => 'magellan', 'domain' => 'magellanbay.pl']);

        $this->assertSame('magellanbay.pl', $shop->host());

        $this->dedicated();
        $this->assertSame('magellanbay.pl', $shop->host());
    }

    /**
     * Lista zaplanowanych zadań z osobnego procesu, w zadanym trybie.
     */
    private function artisanScheduleIn(string $mode): string
    {
        return $this->artisanIn($mode, 'schedule:list');
    }

    /**
     * Uruchamia komendę artisana w OSOBNYM PROCESIE, z zadanym trybem.
     *
     * Trasy i zadania cykliczne rejestrują się przy starcie aplikacji, czyli
     * zanim test zdąży cokolwiek przestawić przez `config()->set()`. Różnice
     * między trybami widać więc dopiero w świeżo wystartowanym procesie —
     * inaczej sprawdzalibyśmy konfigurację, a nie rzeczywistą rejestrację.
     */
    private function artisanIn(string $mode, string $command): string
    {
        return (string) shell_exec(sprintf(
            'cd %s && SHOP_MODE=%s %s artisan %s 2>&1',
            escapeshellarg(base_path()),
            escapeshellarg($mode),
            escapeshellarg(PHP_BINARY),
            $command,
        ));
    }
}
