<?php

namespace Tests\Feature;

use App\Enums\ConsentChannel;
use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Zgoda SPRZEDAWCY na informacje handlowe od Kramio (kody, oferty, nowości).
 *
 * Zbierana przy rejestracji osobnym, niezaznaczonym checkboxem — sama
 * rejestracja zgodą NIE jest (art. 10 uśude). Odwoływalna w profilu. Maili
 * niezbędnych do umowy (faktura, pakiet) ta zgoda nie dotyczy.
 *
 * Zgoda nie działa wstecz, dlatego zbieranie musiało wejść ZANIM pojawią się
 * realni sprzedawcy — inaczej nigdy nie dałoby się do nich napisać.
 */
class SellerMarketingConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rejestracja wymaga aktualnych dokumentów prawnych do akceptacji.
        foreach ([LegalDocumentType::Terms, LegalDocumentType::Privacy] as $type) {
            LegalDocument::create([
                'type' => $type,
                'version' => 'v1',
                'content' => 'Treść',
                'published_at' => now()->subDay(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Anna',
            'surname' => 'Kowalska',
            'email' => 'anna@example.test',
            'shop_name' => 'Kwiaciarnia Anny',
            'slug' => 'kwiaciarnia-anny',
            'terms' => '1',
            'privacy' => '1',
            ...$overrides,
        ];
    }

    public function test_registration_form_offers_an_optional_marketing_checkbox(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Chcę otrzymywać e-maile o nowościach, ofertach i kodach rabatowych Kramio.')
            ->assertSee('Nieobowiązkowe')
            // Checkbox NIE może być `required` ani domyślnie zaznaczony.
            ->assertSee('name="marketing" value="1"', false)
            ->assertDontSee('name="marketing" value="1" required', false);
    }

    public function test_checked_box_records_the_consent_with_proof(): void
    {
        $this->post(route('register.store'), $this->payload(['marketing' => '1']))->assertRedirect();

        $user = User::where('email', 'anna@example.test')->firstOrFail();

        $this->assertTrue($user->hasMarketingConsent());

        $consent = $user->marketingConsents()->firstOrFail();
        $this->assertSame(ConsentChannel::Email, $consent->channel);
        $this->assertNotNull($consent->granted_at);
        $this->assertNull($consent->revoked_at);
        // Dowód: wersja treści i IP — RODO art. 7 każe wykazać, na co ktoś klikał.
        $this->assertSame(config('legal.seller_marketing_consent.version'), $consent->version);
        $this->assertNotNull($consent->ip_address);
    }

    public function test_registration_without_the_box_leaves_no_consent_row(): void
    {
        $this->post(route('register.store'), $this->payload())->assertRedirect();

        $user = User::where('email', 'anna@example.test')->firstOrFail();

        // BRAK WIERSZA = „nigdy się nie zgodził". Odróżnienie od „wypisał się"
        // (wiersz z revoked_at) ma znaczenie dowodowe.
        $this->assertFalse($user->hasMarketingConsent());
        $this->assertSame(0, $user->marketingConsents()->count());
    }

    public function test_registration_still_works_without_the_consent(): void
    {
        // Zgoda jest dobrowolna — jej brak nie może blokować założenia konta.
        $this->post(route('register.store'), $this->payload())->assertRedirect();

        $this->assertNotNull(User::where('email', 'anna@example.test')->first()?->shop);
    }

    public function test_seller_can_grant_and_revoke_the_consent_in_the_profile(): void
    {
        $user = User::factory()->consented()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Wiadomości od Kramio')
            // Mówimy wprost, że maile o fakturach idą niezależnie od zgody.
            ->assertSee('niezbędne do obsługi konta');

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name, 'surname' => $user->surname, 'email' => $user->email,
            'marketing' => '1',
        ])->assertRedirect();
        $this->assertTrue($user->fresh()->hasMarketingConsent());

        // Wycofanie: brak pola w formularzu = zgoda zdjęta.
        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name, 'surname' => $user->surname, 'email' => $user->email,
        ])->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->hasMarketingConsent());
        // Wiersz ZOSTAJE z datą wycofania — dowód, że zgoda była i została odwołana.
        $this->assertNotNull($user->marketingConsents()->firstOrFail()->revoked_at);
    }

    public function test_resaving_the_same_state_does_not_restamp_the_proof(): void
    {
        $user = User::factory()->consented()->create();

        Carbon::setTestNow(Carbon::parse('2026-07-01 10:00'));
        $user->setMarketingConsent(ConsentChannel::Email, true, '10.0.0.1');
        $granted = $user->marketingConsents()->firstOrFail()->granted_at;

        // Kolejne „Zapisz" w profilu bez zmiany stanu NIE może nadpisać dowodu,
        // kiedy zgoda naprawdę padła.
        Carbon::setTestNow(Carbon::parse('2026-07-30 10:00'));
        $this->actingAs($user->fresh())->post(route('profile.update'), [
            'name' => $user->name, 'surname' => $user->surname, 'email' => $user->email,
            'marketing' => '1',
        ])->assertRedirect();

        $this->assertEquals($granted, $user->fresh()->marketingConsents()->firstOrFail()->granted_at);
        $this->assertSame('10.0.0.1', $user->fresh()->marketingConsents()->firstOrFail()->ip_address);

        Carbon::setTestNow();
    }

    public function test_consent_is_per_channel_and_defaults_to_email(): void
    {
        $user = User::factory()->consented()->create();

        $this->assertFalse($user->hasMarketingConsent());

        $user->setMarketingConsent(ConsentChannel::Email, true, '127.0.0.1');

        // Domyślny kanał to e-mail — jedyny, jaki dziś mamy.
        $this->assertTrue($user->fresh()->hasMarketingConsent());
        $this->assertTrue($user->fresh()->hasMarketingConsent(ConsentChannel::Email));
    }
}
