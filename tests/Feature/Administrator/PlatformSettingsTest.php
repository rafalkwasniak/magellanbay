<?php

namespace Tests\Feature\Administrator;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Konsola admina — ustawienia platformy: STAN (diagnostyka) i PRZEŁĄCZNIKI.
 *
 * Najważniejsze, czego pilnują te testy: przełącznik rejestracji zamyka OBIE
 * trasy (formularz i zapis), sekrety nie wychodzą na ekran, a domyślny stan
 * przy pustej tabeli to „otwarte" — awaria magazynu ustawień nie może
 * przypadkiem odciąć platformy od nowych sprzedawców.
 */
class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_platform_state(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('administrator.settings.index'))
            ->assertOk()
            ->assertSee('Integracje')
            ->assertSee('Zadania w tle i poczta')
            ->assertSee('Błędy w logach')
            // Stan kopii czytamy z daty ostatniej udanej, nie z samej
            // konfiguracji — świeża baza nie ma żadnej, więc pasek jest czerwony.
            ->assertSee('Kopie zapasowe')
            ->assertSee('żadna kopia jeszcze się nie powiodła');
    }

    public function test_settings_show_the_date_of_the_last_backup(): void
    {
        Carbon::setTestNow('2026-08-12 09:00:00');
        PlatformSetting::put(PlatformSetting::LAST_BACKUP_AT, Carbon::now()->subHours(6)->toIso8601String());

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('administrator.settings.index'))
            ->assertOk()
            ->assertSee('12 sierpnia 2026');
    }

    public function test_seller_cannot_open_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('administrator.settings.index'))
            ->assertForbidden();
    }

    public function test_secrets_never_reach_the_screen(): void
    {
        // Klucz API na ekranie przeglądarki to klucz w jej historii. Ekran mówi
        // wyłącznie „wpięte / brak" — i to musi zostać prawdą także wtedy, gdy
        // ktoś kiedyś doda tu kolejną integrację.
        config([
            'services.paynow.platform.api_key' => 'sekret-paynow-123',
            'services.fakturownia.token' => 'sekret-fakturownia-456',
            'services.discord.webhook' => 'https://discord.example/sekret-789',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('administrator.settings.index'))
            ->assertOk()
            ->assertDontSee('sekret-paynow-123')
            ->assertDontSee('sekret-fakturownia-456')
            ->assertDontSee('sekret-789');
    }

    public function test_sandbox_paynow_is_called_out_as_a_warning(): void
    {
        // Sandbox na produkcji znaczy, że nikt nie zapłaci naprawdę — a wszystko
        // wygląda wtedy na działające.
        config([
            'services.paynow.platform.api_key' => 'cokolwiek',
            'services.paynow.platform.environment' => 'sandbox',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('administrator.settings.index'))
            ->assertOk()
            ->assertSee('UWAGA: tryb sandbox');
    }

    public function test_registration_is_open_when_nothing_was_ever_saved(): void
    {
        // Pusta tabela to stan świeżej instalacji. Zamknięte drzwi byłyby wtedy
        // awarią, która sama siebie ukrywa.
        $this->assertTrue(PlatformSetting::registrationOpen());

        $this->get(route('register'))->assertOk();
    }

    public function test_closing_registration_blocks_both_the_form_and_the_submit(): void
    {
        // Bramka tylko na formularzu dałaby się obejść POST-em wprost — dlatego
        // pilnuje jej middleware wpięty w obie trasy.
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('administrator.settings.update'), ['maintenance_notice' => ''])
            ->assertRedirect(route('administrator.settings.index'));

        $this->assertFalse(PlatformSetting::registrationOpen());

        $this->get(route('register'))
            ->assertStatus(503)
            ->assertSee('Rejestracja chwilowo zamknięta');

        $this->post(route('register.store'), [])->assertStatus(503);
    }

    public function test_closed_registration_still_lets_existing_sellers_log_in(): void
    {
        // Zamknięcie rejestracji nie może odciąć sprzedawców, którzy właśnie
        // realizują zamówienia.
        PlatformSetting::put(PlatformSetting::REGISTRATION_OPEN, '0');

        $this->get(route('login'))->assertOk();
    }

    public function test_maintenance_notice_shows_across_the_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('administrator.settings.update'), [
                'registration_open' => '1',
                'maintenance_notice' => 'Wieczorem między 22 a 23 mogą być przerwy.',
            ])
            ->assertRedirect(route('administrator.settings.index'));

        // Nad KAŻDYM ekranem panelu, nie tylko nad ustawieniami.
        $this->actingAs($admin)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('Przerwa techniczna')
            ->assertSee('Wieczorem między 22 a 23 mogą być przerwy.');
    }

    public function test_blank_notice_never_shows_an_empty_banner(): void
    {
        // Same białe znaki zapisane w polu włączałyby pustą pomarańczową belkę
        // na całej platformie.
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('administrator.settings.update'), [
            'registration_open' => '1',
            'maintenance_notice' => '   ',
        ]);

        $this->assertNull(PlatformSetting::maintenanceNotice());

        $this->actingAs($admin)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertDontSee('Przerwa techniczna');
    }

    public function test_notice_longer_than_the_limit_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('administrator.settings.update'), [
                'registration_open' => '1',
                'maintenance_notice' => str_repeat('a', 301),
            ])
            ->assertSessionHasErrors('maintenance_notice');
    }
}
