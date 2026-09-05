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

    public function test_seller_registration_is_closed(): void
    {
        // Konto właściciela zakłada seeder wdrożeniowy. Otwarty formularz
        // wysyłający maile aktywacyjne na dowolny adres byłby tu wyłącznie
        // ryzykiem — i to takim, które kosztuje reputację serwera pocztowego.
        $this->dedicated();

        $this->get(route('register'))->assertNotFound();
        $this->post(route('register.store'), [])->assertNotFound();
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

    /**
     * Lista zaplanowanych zadań z osobnego procesu, w zadanym trybie.
     */
    private function artisanScheduleIn(string $mode): string
    {
        $command = sprintf(
            'cd %s && SHOP_MODE=%s %s artisan schedule:list 2>&1',
            escapeshellarg(base_path()),
            escapeshellarg($mode),
            escapeshellarg(PHP_BINARY),
        );

        return (string) shell_exec($command);
    }
}
