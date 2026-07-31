<?php

namespace Tests\Feature\Security;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ochrona formularzy dostępnych bez logowania.
 *
 * Powód powstania: uwaga od testera (31.07.2026), że strony logowania na
 * produkcjach WordPressa są nieustannie męczone łamaniem haseł. Blokadę
 * logowania mieliśmy już wcześniej — przegląd wykazał za to, że REJESTRACJA
 * nie miała żadnego limitu, a to ona wysyła maila na dowolny podany adres.
 *
 * Te testy pilnują trzech niezależnych warstw: limitu żądań per IP (skrypt),
 * blokady per konto (zgadywanie hasła) i pułapki na boty (masowa rejestracja).
 */
class AuthProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    /**
     * @return array<string, string>
     */
    private function registrationPayload(string $email = 'nowy@example.com'): array
    {
        return [
            'shop_name' => 'Sklep Testowy',
            'name' => 'Jan',
            'surname' => 'Kowalski',
            'email' => $email,
            'terms' => '1',
            'privacy' => '1',
        ];
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        $max = (int) config('security.public_forms.register.max_attempts');

        // Wyczerpujemy limit. Adresy są różne, więc nic poza limitem ich nie
        // zatrzymuje — dokładnie tak wyglądałaby masowa wysyłka maili.
        foreach (range(1, $max) as $i) {
            $this->post(route('register.store'), $this->registrationPayload("bot{$i}@example.com"));
        }

        $this->post(route('register.store'), $this->registrationPayload('bot-ostatni@example.com'))
            ->assertStatus(429);

        $this->assertDatabaseMissing('users', ['email' => 'bot-ostatni@example.com']);
    }

    public function test_registration_rejects_submission_with_filled_honeypot(): void
    {
        $payload = $this->registrationPayload() + ['website' => 'http://spam.example.com'];

        $this->post(route('register.store'), $payload)->assertSessionHasErrors('website');

        $this->assertDatabaseMissing('users', ['email' => 'nowy@example.com']);
    }

    public function test_honeypot_does_not_block_a_regular_person(): void
    {
        // Przeglądarka wysyła ukryte pole PUSTE — to musi przechodzić, inaczej
        // pułapka zamyka rejestrację wszystkim.
        $this->post(route('register.store'), $this->registrationPayload() + ['website' => ''])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'nowy@example.com']);
    }

    public function test_activation_is_rate_limited_per_ip(): void
    {
        $max = (int) config('security.public_forms.activation.max_attempts');

        foreach (range(1, $max) as $ignored) {
            $this->post(route('activation.store'), ['token' => 'zmyslony', 'email' => 'kto@example.com']);
        }

        $this->post(route('activation.store'), ['token' => 'zmyslony', 'email' => 'kto@example.com'])
            ->assertStatus(429);
    }

    public function test_storefront_login_locks_the_account_after_failed_attempts(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->create([
            'shop_id' => $shop->id,
            'password' => Hash::make('haslo-klienta'),
            'email_verified_at' => now(),
        ]);

        $max = (int) config('security.login.max_attempts');

        foreach (range(1, $max) as $ignored) {
            $this->post($this->host($shop).'/logowanie', [
                'email' => $customer->email,
                'password' => 'zle-haslo',
            ]);
        }

        // Blokada obowiązuje nawet przy POPRAWNYM haśle — inaczej atakujący,
        // który trafi hasło ostatnią próbą, i tak wchodzi.
        $this->post($this->host($shop).'/logowanie', [
            'email' => $customer->email,
            'password' => 'haslo-klienta',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('customer');
    }

    public function test_storefront_lockout_does_not_leak_between_shops(): void
    {
        $first = Shop::factory()->create();
        $second = Shop::factory()->create();

        $email = 'ten.sam@example.com';

        Customer::factory()->create([
            'shop_id' => $second->id,
            'email' => $email,
            'password' => Hash::make('haslo-klienta'),
            'email_verified_at' => now(),
        ]);

        // Ktoś dobija się do konta o tym adresie w PIERWSZYM sklepie...
        foreach (range(1, (int) config('security.login.max_attempts')) as $ignored) {
            $this->post($this->host($first).'/logowanie', [
                'email' => $email,
                'password' => 'zle-haslo',
            ]);
        }

        // ...co nie może zamknąć drzwi tej samej osobie u innego sprzedawcy.
        $this->post($this->host($second).'/logowanie', [
            'email' => $email,
            'password' => 'haslo-klienta',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticated('customer');
    }

    public function test_lockout_raises_an_alert_on_the_notification_channel(): void
    {
        config()->set('services.discord.webhook', 'https://discord.test/webhook');
        Http::fake(['discord.test/*' => Http::response('', 204)]);

        $user = User::factory()->create();

        foreach (range(1, (int) config('security.login.max_attempts') + 1) as $ignored) {
            $this->post(route('login.attempt'), [
                'email' => $user->email,
                'password' => 'zle-haslo',
            ]);
        }

        Http::assertSent(function ($request) use ($user) {
            $body = json_encode($request->data());

            // Alert ma nieść dość, by rozpoznać sytuację, ale NIE pełny adres
            // e-mail — kanał alertów widzi więcej osób niż serwer.
            return str_contains($body, 'Zablokowane logowanie')
                && ! str_contains($body, $user->email);
        });
    }
}
