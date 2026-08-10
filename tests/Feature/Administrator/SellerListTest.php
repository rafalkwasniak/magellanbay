<?php

namespace Tests\Feature\Administrator;

use App\Enums\ConsentChannel;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsola admina — lista sprzedawców: dostęp tylko dla admina, komplet danych
 * w wierszu, filtry (aktywacja, zgoda na oferty) i liczniki nad tabelą.
 *
 * Licznik „Zgoda na oferty” jest tu najważniejszy: to z niego admin odczyta,
 * do ilu sprzedawców wolno mu legalnie wysłać treści handlowe.
 */
class SellerListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_seller_with_shop_and_package(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create(['name' => 'Zofia', 'surname' => 'Kruk', 'email' => 'zofia@example.com']);
        Shop::factory()->package('booth')->create(['name' => 'Kwiaciarnia Zosia', 'owner_id' => $seller->id]);

        $this->actingAs($admin)
            ->get(route('administrator.sellers.index'))
            ->assertOk()
            ->assertSee('Zofia Kruk')
            ->assertSee('zofia@example.com')
            ->assertSee('Kwiaciarnia Zosia')
            ->assertSee('Stragan');
    }

    public function test_admin_accounts_are_not_listed_as_sellers(): void
    {
        // Dział nazywa się „Sprzedawcy". Konto administratora w tej tabeli
        // zawyżałoby liczniki, z których czyta się zasięg wysyłki.
        //
        // Szukamy DRUGIEGO admina, nie zalogowanego — nazwisko własne widnieje
        // w pasku bocznym panelu, więc na nim ten test przechodziłby zawsze.
        $admin = User::factory()->admin()->create(['name' => 'Ala', 'surname' => 'Zalogowana']);
        User::factory()->admin()->create(['name' => 'Rafał', 'surname' => 'Adminowski']);

        $response = $this->actingAs($admin)->get(route('administrator.sellers.index'));

        $response->assertOk()->assertDontSee('Adminowski');
        $this->assertSame(0, $response->viewData('summary')['sellers']);
    }

    public function test_unactivated_account_is_flagged(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->unverified()->create(['name' => 'Jan', 'surname' => 'Niedokończony']);

        $this->actingAs($admin)
            ->get(route('administrator.sellers.index'))
            ->assertOk()
            ->assertSee('Jan Niedokończony')
            ->assertSee('czeka na aktywację');
    }

    public function test_activation_filter_separates_activated_from_waiting(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['surname' => 'Aktywna']);
        User::factory()->unverified()->create(['surname' => 'Czekajaca']);

        $this->actingAs($admin)
            ->get(route('administrator.sellers.index', ['aktywacja' => '1']))
            ->assertOk()
            ->assertSee('Aktywna')
            ->assertDontSee('Czekajaca');

        $this->actingAs($admin)
            ->get(route('administrator.sellers.index', ['aktywacja' => '0']))
            ->assertOk()
            ->assertSee('Czekajaca')
            ->assertDontSee('Aktywna');
    }

    public function test_consent_filter_uses_the_same_definition_as_the_model(): void
    {
        // Wycofana zgoda ZOSTAWIA wiersz w bazie. Gdyby filtr pytał tylko o
        // istnienie rekordu, ktoś wypisany trafiłby na listę adresatów — a to
        // już jest wysyłka bez zgody, nie usterka wyświetlania.
        $admin = User::factory()->admin()->create();

        $consented = User::factory()->create(['surname' => 'Zgodna']);
        $consented->setMarketingConsent(ConsentChannel::Email, true, '10.0.0.1');

        $revoked = User::factory()->create(['surname' => 'Wypisana']);
        $revoked->setMarketingConsent(ConsentChannel::Email, true, '10.0.0.2');
        $revoked->setMarketingConsent(ConsentChannel::Email, false);

        $never = User::factory()->create(['surname' => 'Niepytana']);

        $this->actingAs($admin)
            ->get(route('administrator.sellers.index', ['zgoda' => '1']))
            ->assertOk()
            ->assertSee('Zgodna')
            ->assertDontSee('Wypisana')
            ->assertDontSee('Niepytana');

        $this->actingAs($admin)
            ->get(route('administrator.sellers.index', ['zgoda' => '0']))
            ->assertOk()
            ->assertSee('Wypisana')
            ->assertSee('Niepytana')
            ->assertDontSee('Zgodna');

        unset($never);
    }

    public function test_summary_counts_reachable_sellers(): void
    {
        $admin = User::factory()->admin()->create();

        $consented = User::factory()->create();
        $consented->setMarketingConsent(ConsentChannel::Email, true, '10.0.0.1');
        User::factory()->create();
        User::factory()->unverified()->create();

        $response = $this->actingAs($admin)->get(route('administrator.sellers.index'));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('summary')['sellers']);
        $this->assertSame(2, $response->viewData('summary')['activated']);
        $this->assertSame(1, $response->viewData('summary')['consented']);
    }

    public function test_summary_follows_the_active_filters(): void
    {
        // Kafelki opisują to, co widać w tabeli — inaczej przy zawężonej liście
        // pokazywałyby liczbę, do której nic na ekranie nie pasuje.
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();
        User::factory()->unverified()->create();

        $response = $this->actingAs($admin)
            ->get(route('administrator.sellers.index', ['aktywacja' => '0']));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('summary')['sellers']);
    }

    public function test_search_finds_seller_by_shop_name(): void
    {
        $admin = User::factory()->admin()->create();
        $matching = User::factory()->create(['surname' => 'Znaleziona']);
        Shop::factory()->create(['name' => 'Bukiety Anny', 'owner_id' => $matching->id]);
        User::factory()->create(['surname' => 'Pominieta']);

        $this->actingAs($admin)
            ->get(route('administrator.sellers.index', ['szukaj' => 'Bukiety']))
            ->assertOk()
            ->assertSee('Znaleziona')
            ->assertDontSee('Pominieta');
    }

    public function test_seller_cannot_view_the_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('administrator.sellers.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('administrator.sellers.index'))
            ->assertRedirect(route('login'));
    }
}
