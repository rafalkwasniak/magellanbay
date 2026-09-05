<?php

namespace Tests\Feature;

use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\Mode;
use App\Support\SellerPrivacy;
use App\Support\SellerTerms;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DeploymentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Seedery danych startowych: `DeploymentSeeder` (idzie na serwer klienta) oraz
 * `DemoSeeder` (wyłącznie środowisko robocze).
 *
 * Ten seeder uruchamia się RAZ, na cudzym serwerze, zwykle bez nas przy
 * klawiaturze — czyli w warunkach, w których błąd wychodzi najpóźniej i kosztuje
 * najwięcej. Stąd nacisk na gardy: sprawdzamy nie tylko to, że seeder zakłada
 * sklep, ale przede wszystkim to, czego ODMAWIA.
 *
 * @see docs_mod/06-dane-startowe.md
 */
class DeploymentSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Suita opisuje Kramio (`SHOP_MODE=saas` w phpunit.xml), więc tryb i
     * parametry wdrożenia ustawiamy tu jawnie — tak samo jak w DedicatedModeTest.
     */
    private function deploymentConfig(): void
    {
        config()->set('shop.mode', Mode::DEDICATED);
        config()->set('deployment.owner.name', 'Jan');
        config()->set('deployment.owner.surname', 'Kowalski');
        config()->set('deployment.owner.email', 'wlasciciel@example.com');
        config()->set('deployment.owner.phone', '600100200');
        config()->set('deployment.shop.name', 'Magellan Bay');
        config()->set('deployment.shop.slug', '');
        config()->set('deployment.shop.contact_email', 'sklep@example.com');
        config()->set('deployment.shop.contact_phone', '600100201');
        config()->set('deployment.company.company_name', 'Magellan Bay sp. z o.o.');
        config()->set('deployment.company.nip', '5252445767');
        config()->set('deployment.company.country', 'PL');
        config()->set('deployment.company.province', 'pomorskie');
        config()->set('deployment.company.city', 'Gdańsk');
        config()->set('deployment.company.postal_code', '80-001');
        config()->set('deployment.company.street', 'Kwiatowa');
        config()->set('deployment.company.building_number', '12');
        config()->set('deployment.company.apartment_number', '');
        config()->set('deployment.sales.default_vat_rate', '23');
        config()->set('deployment.appearance.template', 'white_harbour');
        config()->set('deployment.appearance.palette', 'sunset');
        config()->set('deployment.package', 'dedicated');
    }

    private function seedDeployment(): void
    {
        $this->seed(DeploymentSeeder::class);
    }

    public function test_it_creates_exactly_one_owner_and_one_shop(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Shop::query()->count());

        $owner = User::query()->sole();
        $this->assertSame('wlasciciel@example.com', $owner->email);
        $this->assertSame(UserRole::Seller, $owner->role);

        $shop = Shop::query()->sole();
        $this->assertSame('Magellan Bay', $shop->name);
        $this->assertSame($owner->id, $shop->owner_id);
        $this->assertSame('Magellan Bay sp. z o.o.', $shop->company_name);
        $this->assertSame('5252445767', $shop->nip);
        $this->assertSame('Gdańsk', $shop->city);
    }

    /**
     * Sedno całego trybu dedykowanego: klient zapłacił za sklep bez limitów,
     * więc pierwsza rzecz, której pilnujemy, to że po wdrożeniu żadnej bramy
     * pakietu nie ma.
     */
    public function test_shop_has_dedicated_package_that_never_expires(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();

        $shop = Shop::query()->sole();

        $this->assertSame('dedicated', $shop->package);
        $this->assertTrue($shop->comped);
        $this->assertTrue($shop->subscriptionActive());
        $this->assertNull($shop->subscription_ends_at);

        // Snapshot uprawnień zapisany NA SKLEPIE, nie czytany z configu —
        // późniejsza zmiana definicji pakietu nie ma prawa go ruszyć.
        $this->assertSame(1000000, $shop->entitlement('max_products'));
        $this->assertTrue($shop->entitlement('order_editing'));
        $this->assertTrue($shop->entitlement('discount_codes'));
        $this->assertTrue($shop->entitlement('invoices'));
    }

    /**
     * Data wygaśnięcia jest pusta, więc gdyby ktoś kiedyś zdjął `comped`,
     * sklep NIE ma prawa zgasnąć po cichu następnego dnia. Ten test pilnuje
     * pary `comped` + „pusta data przy płatnym pakiecie = bezterminowo".
     */
    public function test_subscription_stays_active_even_without_comped_flag(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();

        $shop = Shop::query()->sole();
        $shop->comped = false;
        $shop->save();

        $this->assertTrue($shop->fresh()->subscriptionActive());
    }

    public function test_owner_gets_no_usable_password(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();

        $owner = User::query()->sole();

        // Hasło ustawia właściciel z linku aktywacyjnego. Gdyby seeder wpisał
        // cokolwiek przewidywalnego, powstałoby konto sprzedawcy ze znanym
        // hasłem na cudzej produkcji — dokładnie ten śmieć sprzątaliśmy 24.08.
        $this->assertFalse(Hash::check('password', $owner->password));
        $this->assertFalse(Hash::check('', $owner->password));
        $this->assertFalse(Hash::check($owner->email, $owner->password));
    }

    /**
     * Sklep zostaje szkicem: publikacja to decyzja właściciela, podjęta po
     * wgraniu produktów i dokumentów. Storefront wystawiony w chwili zakładania
     * bazy pokazywałby klientom pustą półkę i zaślepkę zamiast regulaminu.
     */
    public function test_shop_starts_as_draft(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();

        $this->assertSame(ShopStatus::Draft, Shop::query()->sole()->status);
    }

    /**
     * Strony systemowe powstają z obserwatora sklepu, nie z seedera — i mają
     * powstać także tutaj. Gdyby seeder omijał obserwatora (np. wstawiając
     * wiersz zapytaniem), sklep klienta ruszyłby bez nieusuwalnych dokumentów
     * i nikt by tego nie zauważył aż do pierwszej reklamacji.
     *
     * W trybie dedykowanym są DWIE: regulamin i polityka prywatności. Polityka
     * jest tu dokumentem SKLEPU, bo nie ma platformy, która mogłaby opisać
     * przetwarzanie danych za właściciela.
     */
    public function test_system_pages_are_created_with_the_shop(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();

        $slugi = Page::query()->where('is_system', true)->pluck('slug')->all();

        $this->assertEqualsCanonicalizing([
            config('pages.regulamin.slug'),
            config('pages.privacy.slug'),
        ], $slugi);

        $this->assertSame(2, Page::query()->count());
    }

    /**
     * Strony systemowe są ZAWSZE opublikowane, a odnośniki do nich stoją
     * w nagłówku i stopce od pierwszego dnia. Wybór nie brzmi więc „szkic czy
     * publikacja", tylko „co klient publikuje, zanim usiądzie do dokumentów".
     * Wzór bije zaślepkę „w przygotowaniu" w obie strony.
     */
    public function test_system_pages_are_filled_with_the_real_templates(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();

        $regulamin = Page::query()->where('slug', config('pages.regulamin.slug'))->sole();
        $polityka = Page::query()->where('slug', config('pages.privacy.slug'))->sole();

        $this->assertTrue((bool) $regulamin->published);
        $this->assertTrue((bool) $polityka->published);

        // Nie zaślepka — realna treść dokumentu.
        $this->assertStringNotContainsString('w przygotowaniu', $regulamin->content);
        $this->assertStringNotContainsString('w przygotowaniu', $polityka->content);
        $this->assertStringContainsString('Prawo odstąpienia', $regulamin->content);
        $this->assertStringContainsString('Administratorem Twoich danych', $polityka->content);

        // Wersja wzoru zapisana — bez niej po poprawkach prawnika nie ustalimy,
        // kto ma starą.
        $this->assertSame(SellerTerms::VERSION, $regulamin->terms_template_version);
        $this->assertSame(SellerPrivacy::VERSION, $polityka->terms_template_version);
    }

    /**
     * Dane firmowe z `.env` wchodzą wprost do dokumentów, a czego nie ma —
     * zostaje widoczną luką. Wzór z pustym miejscem po nazwie sprzedawcy
     * czytałby się jak usterka; `[NAZWA_SPRZEDAWCY]` czyta się jak zadanie.
     */
    public function test_documents_use_env_data_and_mark_what_is_missing(): void
    {
        $this->deploymentConfig();
        config()->set('deployment.company.company_name', '');
        config()->set('deployment.company.nip', '');

        $this->seedDeployment();

        $polityka = Page::query()->where('slug', config('pages.privacy.slug'))->sole();

        $this->assertStringContainsString('[NAZWA_SPRZEDAWCY]', $polityka->content);
        // NIP też jest luką, mimo że w kreatorze pusty NIP znaczy „działalność
        // nierejestrowana". Tam pustkę wybrał człowiek, tu po prostu nikt nie
        // wypełnił zmiennej — a to za mało, by stwierdzić cudzy status prawny.
        $this->assertStringContainsString('[NIP]', $polityka->content);
        $this->assertStringContainsString('Gdańsk', $polityka->content);
    }

    public function test_slug_is_derived_from_shop_name_when_not_given(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();

        $this->assertSame('magellan-bay', Shop::query()->sole()->slug);
    }

    public function test_explicit_slug_wins_over_derived_one(): void
    {
        $this->deploymentConfig();
        config()->set('deployment.shop.slug', 'magellanbay');

        $this->seedDeployment();

        $this->assertSame('magellanbay', Shop::query()->sole()->slug);
    }

    // --- Gardy ------------------------------------------------------------

    /**
     * Najgroźniejsza pomyłka, jaką ten seeder umożliwia: puszczony na Kramio
     * zakłada sprzedawcę z dożywotnim pakietem bez limitów, i to bez śladu
     * w historii pakietów.
     */
    public function test_it_refuses_to_run_in_saas_mode(): void
    {
        $this->deploymentConfig();
        config()->set('shop.mode', Mode::SAAS);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('trybie dedykowanym');

        $this->seedDeployment();
    }

    /**
     * `ResolveShop` bierze w tym trybie `Shop::query()->first()`, więc drugi
     * sklep byłby niewidoczny — i tym trudniejszy do wykrycia.
     */
    public function test_it_refuses_to_run_twice(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('jest już sklep');

        $this->seedDeployment();

        $this->assertSame(1, Shop::query()->count());
    }

    /**
     * Klient dedykowany płaci za sklep, który od pierwszego uruchomienia wygląda
     * na jego — nie za ekran wyboru skóry. Szablon domyślny aplikacji należy do
     * rodziny Kramio i byłby tu widocznym śladem po platformie.
     */
    public function test_shop_starts_with_the_deployment_appearance(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();

        $shop = Shop::query()->sole();

        $this->assertSame('white_harbour', $shop->template);
        $this->assertSame('#C04A09', $shop->themeTokens()['brand']);
        $this->assertSame('#FFFFFF', $shop->themeTokens()['surface']);
    }

    /**
     * Literówka w nazwie szablonu nie wywaliłaby niczego — sklep spadłby na
     * szablon domyślny i klient dostałby cudze barwy, przekonany, że tak ma być.
     */
    public function test_it_refuses_an_unknown_appearance_template(): void
    {
        $this->deploymentConfig();
        config()->set('deployment.appearance.template', 'nie-ma-takiego');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nieznany szablon');

        $this->seedDeployment();
    }

    /**
     * Mail aktywacyjny mówi tym, co się NAPRAWDĘ stało. W sklepie dedykowanym
     * nikt się nie rejestrował — konto założył ten seeder, sklep już stoi,
     * a właściciel dostaje klucze do rzeczy, którą zamówił. Powinszowanie
     * „pierwszego kroku do sprzedaży w internecie" brzmiałoby jak reklama
     * cudzej usługi wysłana pod zły adres.
     */
    public function test_the_activation_mail_does_not_talk_about_registration(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();

        $mail = EmailMessage::query()->sole();
        $tresc = $mail->subject.' '.json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('rejestracj', $tresc);
        $this->assertStringNotContainsString('w kilka minut', $tresc);
        $this->assertStringContainsString('Magellan Bay', $mail->subject);
        $this->assertSame('Ustaw hasło', $mail->action_text);
    }

    /**
     * Link musi wyjść Z MAILERA, nie powstać obok: broker `activation` kasuje
     * poprzedni token przy tworzeniu nowego, więc drugie wywołanie unieważniłoby
     * ten wysłany w mailu — i właściciel dostałby martwy link.
     */
    public function test_the_printed_link_is_the_same_one_that_was_mailed(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();

        $owner = User::query()->sole();
        $mail = EmailMessage::query()->sole();

        $this->assertStringContainsString('/aktywacja/', (string) $mail->action_url);

        // Ten sam token nadal działa — ekran aktywacji go przyjmuje.
        $this->get($mail->action_url)->assertOk();
        $this->assertSame($owner->email, $mail->to_email);
    }

    public function test_it_refuses_to_run_without_owner_email(): void
    {
        $this->deploymentConfig();
        config()->set('deployment.owner.email', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DEPLOY_OWNER_EMAIL');

        $this->seedDeployment();
    }

    public function test_it_refuses_to_run_without_shop_name(): void
    {
        $this->deploymentConfig();
        config()->set('deployment.shop.name', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DEPLOY_SHOP_NAME');

        $this->seedDeployment();
    }

    /**
     * Garda musi zadziałać PRZED zapisem — inaczej w bazie klienta zostaje
     * osierocone konto sprzedawcy bez sklepu.
     */
    public function test_failed_run_leaves_no_records_behind(): void
    {
        $this->deploymentConfig();
        config()->set('deployment.owner.email', '');

        try {
            $this->seedDeployment();
        } catch (RuntimeException) {
            // oczekiwane
        }

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Shop::query()->count());
    }

    // --- DemoSeeder -------------------------------------------------------

    public function test_demo_seeder_fills_the_shop_with_working_data(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();
        $this->seed(DemoSeeder::class);

        $shop = Shop::query()->sole();

        $this->assertSame(12, $shop->products()->count());
        $this->assertSame(5, $shop->tags()->count());
        $this->assertSame(2, $shop->orders()->count());

        // Promowane na głównej nie mogą przekroczyć sufitu z configu — demo ma
        // pokazywać stan osiągalny w panelu, nie taki, którego panel zabrania.
        $this->assertLessThanOrEqual(
            (int) config('shop.homepage_promoted_limit'),
            Product::query()->where('show_on_homepage', true)->count()
        );
    }

    /**
     * Magnes z czyimś imieniem to rzecz wykonana na indywidualne zamówienie —
     * art. 38 pkt 3 u.p.k. wyłącza go z prawa odstąpienia. Demo ma uczyć
     * właściwego ustawienia, bo to on pokazuje klientowi, jak wypełniać katalog.
     */
    public function test_demo_marks_personalised_products_as_excluded_from_withdrawal(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();
        $this->seed(DemoSeeder::class);

        $excluded = Product::query()->where('withdrawal_excluded', true)->count();

        $this->assertGreaterThan(0, $excluded);
        $this->assertLessThan(Product::query()->count(), $excluded);
    }

    /**
     * Kwoty w demo muszą się spinać. Zamówienie z sumami, których nie da się
     * wystawić na fakturze, wygląda poprawnie i uczy złego — a właśnie z tego
     * ekranu klient uczy się czytać własny panel.
     */
    public function test_demo_orders_have_consistent_totals(): void
    {
        $this->deploymentConfig();
        $this->seedDeployment();
        $this->seed(DemoSeeder::class);

        foreach (Order::query()->with('items')->get() as $order) {
            $items = $order->items->sum(fn ($item) => (float) $item->line_total_gross);

            $this->assertEqualsWithDelta($items, (float) $order->items_total, 0.01);
            $this->assertEqualsWithDelta(
                $items + (float) $order->delivery_cost,
                (float) $order->total_gross,
                0.01
            );
            $this->assertEqualsWithDelta(
                (float) $order->total_gross,
                (float) $order->total_net + (float) $order->total_vat,
                0.01
            );
        }
    }

    public function test_demo_seeder_refuses_to_run_without_a_shop(): void
    {
        $this->deploymentConfig();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nie ma sklepu');

        $this->seed(DemoSeeder::class);
    }
}
