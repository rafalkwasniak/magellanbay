<?php

namespace Tests\Feature\Storefront;

use App\Enums\ConsentChannel;
use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MarketingConsentTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create(['slug' => 'sklep']);
    }

    private function host(): string
    {
        return $this->shop->slug.'.'.config('tenancy.central_domain');
    }

    private function pendingCustomer(): Customer
    {
        return Customer::factory()->for($this->shop)->create([
            'password' => null,
            'email_verified_at' => null,
        ]);
    }

    private function activationUrl(Customer $customer): string
    {
        return URL::temporarySignedRoute('storefront.activation', now()->addDay(), [
            'shop' => $this->shop->slug,
            'customer' => $customer->getKey(),
        ]);
    }

    public function test_activation_without_checkbox_grants_no_consent(): void
    {
        $customer = $this->pendingCustomer();

        $this->post($this->activationUrl($customer), [
            'password' => 'tajne-haslo-123',
            'password_confirmation' => 'tajne-haslo-123',
        ])->assertRedirect();

        $this->assertFalse($customer->fresh()->hasConsent(ConsentChannel::Email));

        // Brak zgody to brak wiersza — nie mylimy „nigdy się nie zgodził"
        // z „wypisał się".
        $this->assertSame(0, $customer->consents()->count());
    }

    public function test_activation_with_checkbox_grants_consent_with_proof(): void
    {
        $customer = $this->pendingCustomer();

        $this->post($this->activationUrl($customer), [
            'password' => 'tajne-haslo-123',
            'password_confirmation' => 'tajne-haslo-123',
            'marketing_email' => '1',
        ])->assertRedirect();

        $customer->refresh();
        $this->assertTrue($customer->hasConsent(ConsentChannel::Email));

        // Dowód: kiedy, na jaką treść, z jakiego IP (RODO art. 7 ust. 1).
        $consent = $customer->consents()->first();
        $this->assertNotNull($consent->granted_at);
        $this->assertNull($consent->revoked_at);
        $this->assertSame(config('legal.marketing_consent.version'), $consent->version);
        $this->assertNotNull($consent->ip_address);
    }

    public function test_customer_can_grant_and_revoke_from_profile(): void
    {
        $customer = Customer::factory()->for($this->shop)->create();

        $this->actingAs($customer, 'customer')
            ->post('http://'.$this->host().'/moje-konto/zgody', ['marketing_email' => '1'])
            ->assertRedirect('/moje-konto/dane');

        $this->assertTrue($customer->fresh()->hasConsent(ConsentChannel::Email));

        // Wypis: bez pola w ogóle — tak wygląda odznaczony checkbox.
        $this->actingAs($customer, 'customer')
            ->post('http://'.$this->host().'/moje-konto/zgody', [])
            ->assertRedirect('/moje-konto/dane');

        $this->assertFalse($customer->fresh()->hasConsent(ConsentChannel::Email));
    }

    /**
     * Po wypisie wiersz ZOSTAJE z `revoked_at` — inaczej nie odróżnisz kogoś,
     * kto się wypisał, od kogoś, kto nigdy się nie zgodził.
     */
    public function test_revoking_keeps_the_row_and_stamps_revoked_at(): void
    {
        $customer = Customer::factory()->for($this->shop)->create();

        $customer->setConsent(ConsentChannel::Email, true, '1.2.3.4');
        $customer->setConsent(ConsentChannel::Email, false);

        $consent = $customer->consents()->first();
        $this->assertNotNull($consent->revoked_at);
        $this->assertNotNull($consent->granted_at);
        $this->assertSame(1, $customer->consents()->count());
    }

    /**
     * Sedno: ponowny zapis TEGO SAMEGO stanu nie może przestemplować daty ani IP
     * — inaczej „Zapisz" w profilu niszczyłby dowód, kiedy zgoda naprawdę padła.
     */
    public function test_resaving_same_state_does_not_restamp_the_proof(): void
    {
        $customer = Customer::factory()->for($this->shop)->create();
        $customer->setConsent(ConsentChannel::Email, true, '1.2.3.4');

        $granted = $customer->consents()->first()->granted_at;

        $this->travel(5)->minutes();

        $this->actingAs($customer, 'customer')
            ->post('http://'.$this->host().'/moje-konto/zgody', ['marketing_email' => '1']);

        $this->assertEquals(
            $granted->timestamp,
            $customer->fresh()->consents()->first()->granted_at->timestamp,
            'Ponowny zapis tej samej zgody przestemplował datę — dowód przepadł.'
        );
    }

    public function test_consent_is_per_shop_not_per_email(): void
    {
        $other = Shop::factory()->create(['slug' => 'inny']);

        $here = Customer::factory()->for($this->shop)->create(['email' => 'anna@example.com']);
        $there = Customer::factory()->for($other)->create(['email' => 'anna@example.com']);

        $here->setConsent(ConsentChannel::Email, true, '1.2.3.4');

        // Ta sama osoba, ten sam adres, inny sklep — zgoda NIE może wyciec.
        $this->assertTrue($here->hasConsent(ConsentChannel::Email));
        $this->assertFalse($there->fresh()->hasConsent(ConsentChannel::Email));
    }

    public function test_one_row_per_channel_even_after_many_changes(): void
    {
        $customer = Customer::factory()->for($this->shop)->create();

        $customer->setConsent(ConsentChannel::Email, true, '1.2.3.4');
        $customer->setConsent(ConsentChannel::Email, false);
        $customer->setConsent(ConsentChannel::Email, true, '5.6.7.8');

        // To nie jest dziennik zmian — unikat na (klient, kanał).
        $this->assertSame(1, $customer->consents()->count());
        $this->assertTrue($customer->fresh()->hasConsent(ConsentChannel::Email));
    }

    public function test_deleting_customer_removes_consents(): void
    {
        $customer = Customer::factory()->for($this->shop)->create();
        $customer->setConsent(ConsentChannel::Email, true, '1.2.3.4');

        $customer->delete();

        // RODO: „prawo do bycia zapomnianym" — kaskada, bez sierot.
        $this->assertDatabaseCount('customer_consents', 0);
    }
}
