<?php

namespace Tests\Feature\Mail;

use App\Enums\ConsentChannel;
use App\Models\User;
use App\Services\PlatformMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Wypis sprzedawcy z wiadomości handlowych Kramio — z podpisanego linku
 * w stopce, bez logowania.
 *
 * Zgoda musi być odwoływalna równie łatwo, jak została udzielona (RODO art. 7
 * ust. 3), więc samo wejście na stronę wypisuje. Pomyłkę naprawia przycisk
 * „przywróć" na tej samej stronie.
 */
class PlatformUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    private function consentingSeller(): User
    {
        $seller = User::factory()->create();
        $seller->setMarketingConsent(ConsentChannel::Email, true, '127.0.0.1');

        return $seller;
    }

    public function test_opening_the_link_unsubscribes_immediately(): void
    {
        $seller = $this->consentingSeller();

        $this->get(app(PlatformMailService::class)->unsubscribeUrl($seller))
            ->assertOk()
            ->assertSee('to była ostatnia taka wiadomość');

        $this->assertFalse($seller->fresh()->hasMarketingConsent());
    }

    public function test_page_says_that_contract_emails_keep_coming(): void
    {
        // Bez tego wypis czyta się jak odcięcie wszystkiego od platformy —
        // a faktura i informacja o pakiecie idą niezależnie od tej zgody.
        $this->get(app(PlatformMailService::class)->unsubscribeUrl($this->consentingSeller()))
            ->assertOk()
            ->assertSee('faktury');
    }

    public function test_unsubscribing_twice_changes_nothing(): void
    {
        $seller = $this->consentingSeller();
        $url = app(PlatformMailService::class)->unsubscribeUrl($seller);

        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        $this->assertFalse($seller->fresh()->hasMarketingConsent());
    }

    public function test_link_without_a_valid_signature_is_refused(): void
    {
        $seller = $this->consentingSeller();

        $this->get(route('platform.unsubscribe', ['user' => $seller->id]))
            ->assertForbidden();

        // Zgoda nietknięta — podpis jest jedyną bramką tej trasy.
        $this->assertTrue($seller->fresh()->hasMarketingConsent());
    }

    public function test_restore_brings_the_consent_back_with_fresh_proof(): void
    {
        $seller = $this->consentingSeller();

        $this->get(app(PlatformMailService::class)->unsubscribeUrl($seller))->assertOk();
        $this->assertFalse($seller->fresh()->hasMarketingConsent());

        $this->post(URL::signedRoute('platform.unsubscribe.restore', ['user' => $seller->id]))
            ->assertOk()
            ->assertSee('Zgoda przywrócona');

        $this->assertTrue($seller->fresh()->hasMarketingConsent());
    }

    public function test_restore_without_a_signature_is_refused(): void
    {
        $seller = $this->consentingSeller();
        $seller->setMarketingConsent(ConsentChannel::Email, false);

        $this->post(route('platform.unsubscribe.restore', ['user' => $seller->id]))
            ->assertForbidden();

        $this->assertFalse($seller->fresh()->hasMarketingConsent());
    }
}
