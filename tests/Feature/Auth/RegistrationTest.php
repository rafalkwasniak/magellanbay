<?php

namespace Tests\Feature\Auth;

use App\Enums\LegalDocumentType;
use App\Enums\MailPriority;
use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Models\EmailMessage;
use App\Models\LegalDocument;
use App\Models\Shop;
use App\Models\User;
use App\Services\SlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'shop_name' => 'Wiązanki Małgosi',
            'name' => 'Anna',
            'surname' => 'Nowak',
            'email' => 'anna@sklep.test',
            'terms' => '1',
            'privacy' => '1',
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_address_from_unclaimed_subdomain_prefills_shop_name(): void
    {
        // Ktoś wpisał kwiatki-u-ani.kramio.pl, zobaczył „adres wolny" i kliknął.
        // Formularz nie ma pola adresu (slug liczy serwer z nazwy), więc adres
        // musi wrócić jako NAZWA dająca dokładnie ten sam slug.
        $this->get(route('register', ['adres' => 'kwiatki-u-ani']))
            ->assertOk()
            ->assertSee('value="Kwiatki U Ani"', false);

        $this->assertSame('kwiatki-u-ani', app(SlugService::class)->make('Kwiatki U Ani'));
    }

    public function test_malformed_address_in_query_is_ignored(): void
    {
        // Cokolwiek doklei się do URL-a, nie może wylądować w polu nazwy —
        // sprzedawca zająłby adres inny niż ten, po który przyszedł.
        $this->get(route('register', ['adres' => 'Zły Adres!']))
            ->assertOk()
            ->assertDontSee('Zły Adres', false);
    }

    public function test_new_users_register_as_sellers_without_logging_in(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload());

        $response->assertRedirect(route('register.confirmation'));
        $this->assertGuest(); // konto bez hasła — logowanie dopiero po ustawieniu hasła

        $user = User::where('email', 'anna@sklep.test')->firstOrFail();
        $this->assertSame(UserRole::Seller, $user->role);
        $this->assertSame('Anna', $user->name);
        $this->assertSame('Nowak', $user->surname);
    }

    public function test_registration_queues_high_priority_activation_email(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $message = EmailMessage::where('to_email', 'anna@sklep.test')->firstOrFail();

        $this->assertSame(MailPriority::High, $message->priority);
        $this->assertNull($message->sent_at);
        $this->assertStringContainsString('/aktywacja/', (string) $message->action_url);
    }

    public function test_activation_email_can_be_resent_for_pending_account(): void
    {
        // Rejestracja tworzy konto nieaktywowane (1. mail w kolejce).
        $this->post(route('register.store'), $this->validPayload());

        $this->post(route('register.resend'), ['email' => 'anna@sklep.test'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(2, EmailMessage::where('to_email', 'anna@sklep.test')->count());
    }

    public function test_resend_does_nothing_for_already_activated_account(): void
    {
        // Fabryka tworzy konto z email_verified_at = teraz (już aktywowane).
        $user = User::factory()->create(['email' => 'aktywny@sklep.test']);
        $this->assertNotNull($user->email_verified_at);

        $this->post(route('register.resend'), ['email' => 'aktywny@sklep.test'])
            ->assertRedirect()
            ->assertSessionHas('status'); // neutralny komunikat mimo braku wysyłki

        $this->assertDatabaseCount('email_messages', 0);
    }

    public function test_resend_is_neutral_for_unknown_email(): void
    {
        $this->post(route('register.resend'), ['email' => 'nieznany@sklep.test'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseCount('email_messages', 0);
    }

    public function test_registration_records_consents_for_current_documents(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $user = User::where('email', 'anna@sklep.test')->firstOrFail();

        $this->assertCount(2, $user->consents);
        foreach (LegalDocumentType::cases() as $type) {
            $current = LegalDocument::current($type);
            $this->assertTrue(
                $user->consents->contains('legal_document_id', $current->id),
                "Brak zgody na {$type->value}"
            );
        }
    }

    public function test_registration_requires_accepting_terms_and_privacy(): void
    {
        $this->post(route('register.store'), $this->validPayload(['terms' => '0', 'privacy' => '0']))
            ->assertSessionHasErrors(['terms', 'privacy']);

        $this->assertDatabaseMissing('users', ['email' => 'anna@sklep.test']);
        $this->assertDatabaseCount('email_messages', 0);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'anna@sklep.test']);

        $this->post(route('register.store'), $this->validPayload())
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_creates_draft_shop_with_slug_from_name(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $user = User::where('email', 'anna@sklep.test')->firstOrFail();
        $shop = $user->shop;

        $this->assertNotNull($shop);
        $this->assertSame('Wiązanki Małgosi', $shop->name);
        $this->assertSame('wiazanki-malgosi', $shop->slug); // PL znaki transliterowane
        $this->assertSame(ShopStatus::Draft, $shop->status);
        $this->assertSame($user->id, $shop->owner_id);
    }

    public function test_registration_rejects_taken_shop_slug(): void
    {
        Shop::factory()->create(['slug' => 'wiazanki-malgosi']);

        $this->post(route('register.store'), $this->validPayload(['shop_name' => 'Wiązanki Małgosi']))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('users', ['email' => 'anna@sklep.test']);
        $this->assertDatabaseCount('email_messages', 0);
    }

    public function test_registration_rejects_reserved_subdomain(): void
    {
        $this->post(route('register.store'), $this->validPayload(['shop_name' => 'Admin']))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('users', ['email' => 'anna@sklep.test']);
    }

    public function test_registration_requires_shop_name(): void
    {
        // Nazwa złożona z samych symboli daje pusty slug — odrzucamy.
        $this->post(route('register.store'), $this->validPayload(['shop_name' => '!!! ???']))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('users', ['email' => 'anna@sklep.test']);
    }

    public function test_authenticated_user_visiting_register_is_redirected_home(): void
    {
        $seller = User::factory()->consented()->create();

        $this->actingAs($seller)
            ->get(route('register'))
            ->assertRedirect(route('seller.dashboard'));
    }
}
