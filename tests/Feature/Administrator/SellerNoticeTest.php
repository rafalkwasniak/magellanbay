<?php

namespace Tests\Feature\Administrator;

use App\Enums\ConsentChannel;
use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsola admina — wiadomość serwisowa do właściciela sklepu (karta sklepu).
 *
 * Sedno tego modułu i powód, dla którego nie da się go zastąpić działem
 * „Wiadomości": leci NIEZALEŻNIE od zgody marketingowej. Awarii nie da się
 * „nie zaprenumerować", a sprzedawca bez zgody na oferty i tak musi dostać
 * informację o usterce w swoim sklepie.
 */
class SellerNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sends_notice_to_seller_without_marketing_consent(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@kramio.pl']);
        $seller = User::factory()->create(['name' => 'Łukasz', 'email' => 'seller@example.com']);
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);

        // Zgody NIE ma — i to jest właśnie przypadek, który ma zadziałać.
        $this->assertFalse(
            User::whereKey($seller->id)->whereHas('marketingConsents', User::activeMarketingConsent())->exists()
        );

        $this->actingAs($admin)
            ->post(route('administrator.shops.message', $shop), [
                'subject' => 'Usterka w Twoim sklepie',
                'body' => '<p>Już naprawiona.</p>',
            ])
            ->assertRedirect();

        $message = EmailMessage::latest('id')->first();

        $this->assertNotNull($message);
        $this->assertSame('seller@example.com', $message->to_email);
        $this->assertSame('Usterka w Twoim sklepie', $message->subject);
        $this->assertStringContainsString('Już naprawiona.', $message->body_html);
        // Pisze platforma, nie sklep — inaczej mail przyszedłby „od sklepu"
        // do jego własnego właściciela.
        $this->assertNull($message->shop_id);
        // Bez stopki wypisu: z informacji o awarii nie można się wypisać.
        $this->assertNull($message->unsubscribe_url);
        // Odpowiedź wraca do administratora, który pisał.
        $this->assertSame('admin@kramio.pl', $message->reply_to);
        $this->assertSame(MailPriority::Mid, $message->priority);
        // Powitanie w wołaczu, jak w pozostałych mailach platformy.
        $this->assertStringContainsString('Łukaszu', $message->heading);
    }

    public function test_notice_also_reaches_seller_who_has_consent(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create(['email' => 'zgodny@example.com']);
        $seller->setMarketingConsent(ConsentChannel::Email, true, '10.0.0.7');
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);

        $this->actingAs($admin)
            ->post(route('administrator.shops.message', $shop), [
                'subject' => 'Sprawa konta',
                'body' => '<p>Treść.</p>',
            ]);

        $this->assertDatabaseHas('email_messages', [
            'to_email' => 'zgodny@example.com',
            'subject' => 'Sprawa konta',
        ]);
    }

    public function test_empty_subject_or_body_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create();

        $this->actingAs($admin)
            ->post(route('administrator.shops.message', $shop), ['subject' => '', 'body' => ''])
            ->assertSessionHasErrors(['subject', 'body']);

        // Sam znacznik bez tekstu to dla edytora „pusto" — sanitizer zdejmuje
        // go przed walidacją, więc `required` ma co odrzucić.
        $this->actingAs($admin)
            ->post(route('administrator.shops.message', $shop), ['subject' => 'Temat', 'body' => '<p><br></p>'])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('email_messages', 0);
    }

    public function test_seller_cannot_send_notices(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);

        $this->actingAs($seller)
            ->post(route('administrator.shops.message', $shop), [
                'subject' => 'Temat',
                'body' => '<p>Treść.</p>',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('email_messages', 0);
    }

    public function test_form_is_visible_on_the_shop_card(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create(['email' => 'widoczny@example.com']);
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);

        $this->actingAs($admin)
            ->get(route('administrator.shops.edit', $shop))
            ->assertOk()
            ->assertSee('Napisz do sprzedawcy')
            ->assertSee('widoczny@example.com');
    }
}
