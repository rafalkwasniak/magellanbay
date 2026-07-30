<?php

namespace Tests\Feature;

use App\Enums\ConsentChannel;
use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Zgoda SPRZEDAWCY na informacje handlowe od Kramio (kody, oferty, nowości).
 *
 * Zbierana na ekranie AKTYWACJI konta (nie przy rejestracji) — dokładnie jak
 * u klientów sklepu: tam sprzedawca trafia, klikając link z WŁASNEJ skrzynki,
 * więc adres jest już potwierdzony i zgoda ma mocny dowód. Osobny,
 * niezaznaczony checkbox — sama rejestracja zgodą NIE jest (art. 10 uśude).
 * Odwoływalna w profilu. Maili niezbędnych do umowy ta zgoda nie dotyczy.
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

    /**
     * @return array<string, string>
     */
    private function activationPayload(User $user, string $token, array $overrides = []): array
    {
        return [
            'token' => $token,
            'token_email' => $user->email,
            'name' => $user->name,
            'surname' => $user->surname,
            'email' => $user->email,
            'password' => 'TajneHaslo123',
            'password_confirmation' => 'TajneHaslo123',
            'terms' => '1',
            'privacy' => '1',
            ...$overrides,
        ];
    }

    public function test_registration_does_not_ask_about_marketing(): void
    {
        // Pytamy dopiero na aktywacji — rejestracja zostaje krótka, a adres
        // jest wtedy jeszcze niepotwierdzony.
        $this->get(route('register'))
            ->assertOk()
            ->assertDontSee('name="marketing"', false);

        $this->post(route('register.store'), $this->payload())->assertRedirect();

        $this->assertSame(0, User::where('email', 'anna@example.test')->firstOrFail()->marketingConsents()->count());
    }

    public function test_activation_screen_offers_an_optional_marketing_checkbox(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $token = Password::broker('activation')->createToken($user);

        $this->get(route('activation.show', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Chcę otrzymywać e-maile o nowościach, ofertach i kodach rabatowych Kramio.')
            ->assertSee('Nieobowiązkowe')
            // Nie może być wymagany ani domyślnie zaznaczony.
            ->assertSee('name="marketing" value="1"', false)
            ->assertDontSee('name="marketing" value="1" required', false);
    }

    public function test_checked_box_on_activation_records_the_consent_with_proof(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $token = Password::broker('activation')->createToken($user);

        $this->post(route('activation.store'), $this->activationPayload($user, $token, ['marketing' => '1']))
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->hasMarketingConsent());

        $consent = $user->marketingConsents()->firstOrFail();
        $this->assertSame(ConsentChannel::Email, $consent->channel);
        $this->assertNull($consent->revoked_at);
        // Dowód: wersja treści i IP — RODO art. 7 każe wykazać, na co ktoś klikał.
        $this->assertSame(config('legal.seller_marketing_consent.version'), $consent->version);
        $this->assertNotNull($consent->ip_address);
    }

    public function test_activation_without_the_box_leaves_no_consent_row(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $token = Password::broker('activation')->createToken($user);

        $this->post(route('activation.store'), $this->activationPayload($user, $token))->assertRedirect();

        // BRAK WIERSZA = „nigdy się nie zgodził". Odróżnienie od „wypisał się"
        // (wiersz z revoked_at) ma znaczenie dowodowe.
        $user->refresh();
        $this->assertFalse($user->hasMarketingConsent());
        $this->assertSame(0, $user->marketingConsents()->count());
    }

    public function test_activation_works_without_the_consent(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $token = Password::broker('activation')->createToken($user);

        // Zgoda jest dobrowolna — jej brak nie może blokować aktywacji konta.
        $this->post(route('activation.store'), $this->activationPayload($user, $token))->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);
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
