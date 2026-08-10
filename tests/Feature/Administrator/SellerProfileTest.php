<?php

namespace Tests\Feature\Administrator;

use App\Enums\ConsentChannel;
use App\Enums\LegalDocumentType;
use App\Models\EmailMessage;
use App\Models\LegalDocument;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsola admina — karta sprzedawcy: dane konta, jego sklep, komplet zgód
 * z dowodem (data, IP, wersja) oraz jedyna akcja pomocowa, czyli ponowne
 * wysłanie linku aktywacyjnego.
 */
class SellerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_shows_account_shop_and_consents(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create([
            'name' => 'Zofia',
            'surname' => 'Kruk',
            'email' => 'zofia@example.com',
            'phone' => '+48123456789',
        ]);
        Shop::factory()->package('booth')->create(['name' => 'Kwiaciarnia Zosia', 'owner_id' => $seller->id]);
        $seller->setMarketingConsent(ConsentChannel::Email, true, '10.0.0.7');

        $this->actingAs($admin)
            ->get(route('administrator.sellers.show', $seller))
            ->assertOk()
            ->assertSee('zofia@example.com')
            ->assertSee('+48123456789')
            ->assertSee('Kwiaciarnia Zosia')
            ->assertSee('Stragan')
            ->assertSee('10.0.0.7')          // dowód zgody
            ->assertSee('czynna od');
    }

    public function test_card_shows_accepted_legal_documents_with_version(): void
    {
        $admin = User::factory()->admin()->create();
        $document = LegalDocument::create([
            'type' => LegalDocumentType::Terms,
            'version' => 7,
            'content' => 'Treść',
            'published_at' => now()->subDay(),
        ]);
        $seller = User::factory()->create();
        $seller->consents()->create([
            'legal_document_id' => $document->id,
            'accepted_at' => now()->subDays(3),
            'ip_address' => '10.0.0.9',
        ]);

        $this->actingAs($admin)
            ->get(route('administrator.sellers.show', $seller))
            ->assertOk()
            ->assertSee('Regulamin')
            ->assertSee('wersja 7')
            ->assertSee('10.0.0.9');
    }

    public function test_revoked_consent_is_told_apart_from_never_granted(): void
    {
        // Różnica jest dowodowa: „wypisał się" i „nigdy się nie zgodził" to dwa
        // różne stany, więc karta nie może pokazywać ich tak samo.
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create();
        $seller->setMarketingConsent(ConsentChannel::Email, true, '10.0.0.1');
        $seller->setMarketingConsent(ConsentChannel::Email, false);

        $this->actingAs($admin)
            ->get(route('administrator.sellers.show', $seller))
            ->assertOk()
            ->assertSee('wycofana')
            ->assertDontSee('nigdy nie wyraził zgody');

        $fresh = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('administrator.sellers.show', $fresh))
            ->assertOk()
            ->assertSee('nigdy nie wyraził zgody')
            ->assertDontSee('wycofana');
    }

    public function test_admin_can_resend_activation_link_to_a_waiting_account(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->unverified()->create(['email' => 'czeka@example.com']);

        $this->actingAs($admin)
            ->from(route('administrator.sellers.show', $seller))
            ->post(route('administrator.sellers.activation', $seller))
            ->assertRedirect(route('administrator.sellers.show', $seller))
            ->assertSessionHas('success');

        $mail = EmailMessage::latest('id')->first();
        $this->assertNotNull($mail);
        $this->assertSame('czeka@example.com', $mail->to_email);
    }

    public function test_activation_link_is_refused_for_an_active_account(): void
    {
        // Aktywne konto ma już własne hasło. Zaproszenie do zakładania konta
        // byłoby dla sprzedawcy myląca, więc mówimy adminowi, że to nie ta akcja.
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('administrator.sellers.show', $seller))
            ->post(route('administrator.sellers.activation', $seller))
            ->assertRedirect(route('administrator.sellers.show', $seller))
            ->assertSessionHas('error');

        $this->assertSame(0, EmailMessage::count());
    }

    public function test_admin_account_has_no_seller_card(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('administrator.sellers.show', $other))
            ->assertNotFound();
    }

    public function test_seller_cannot_view_another_sellers_card(): void
    {
        $seller = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($seller)
            ->get(route('administrator.sellers.show', $other))
            ->assertForbidden();
    }

    public function test_seller_cannot_trigger_activation_mail(): void
    {
        $seller = User::factory()->create();
        $target = User::factory()->unverified()->create();

        $this->actingAs($seller)
            ->post(route('administrator.sellers.activation', $target))
            ->assertForbidden();

        $this->assertSame(0, EmailMessage::count());
    }
}
