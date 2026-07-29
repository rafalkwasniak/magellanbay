<?php

namespace Tests\Feature\Seller;

use App\Enums\ConsentChannel;
use App\Livewire\Seller\BulkMailingSender;
use App\Models\BulkMailing;
use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel „Wiadomości do klientów": szkic pisany i poprawiany dowolnie długo,
 * próbka do siebie bez limitu, a wysyłka do klientów raz — za potwierdzeniem.
 * Wysłanej wiadomości nie da się już zmienić ani skasować, bo klienci mają ją
 * w skrzynkach.
 */
class BulkMailingPanelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(bool $allowed = true, string $email = 'sprzedawca@example.test'): array
    {
        $seller = User::factory()->consented()->create(['email' => $email]);
        $factory = $allowed ? Shop::factory()->withBulkMail() : Shop::factory();

        return [$seller, $factory->create(['owner_id' => $seller->id])];
    }

    private function consentingCustomer(Shop $shop): Customer
    {
        $customer = Customer::factory()->create(['shop_id' => $shop->id]);
        $customer->setConsent(ConsentChannel::Email, true, '127.0.0.1');

        return $customer->fresh();
    }

    private function draftFor(Shop $shop): BulkMailing
    {
        return $shop->bulkMailings()->create(['subject' => 'Nowości', 'body' => 'Zajrzyj do sklepu.']);
    }

    public function test_shop_without_the_entitlement_sees_an_invitation_instead_of_the_tool(): void
    {
        [$seller] = $this->sellerWithShop(allowed: false);

        $this->actingAs($seller)->get(route('seller.mailings.index'))
            ->assertOk()
            ->assertSee('Wiadomości do klientów w pakiecie Pawilon')
            ->assertDontSee('Napisz wiadomość');

        // Sama strona listy zaprasza, ale narzędzie jest twardo zamknięte.
        $this->actingAs($seller)->get(route('seller.mailings.create'))->assertForbidden();
    }

    public function test_seller_writes_a_draft_and_lands_on_its_edit_page(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)->post(route('seller.mailings.store'), [
            'subject' => 'Nowa książka w sprzedaży',
            'body' => "Mamy dla Ciebie nowość.\n\nZajrzyj do sklepu.",
        ])->assertRedirect();

        $mailing = $shop->bulkMailings()->firstOrFail();
        $this->assertSame('Nowa książka w sprzedaży', $mailing->subject);
        $this->assertTrue($mailing->isDraft());
    }

    public function test_draft_without_a_subject_is_rejected(): void
    {
        [$seller] = $this->sellerWithShop();

        $this->actingAs($seller)->post(route('seller.mailings.store'), ['body' => 'Treść bez tematu'])
            ->assertSessionHasErrors('subject');
    }

    public function test_drafts_are_deleted_from_the_list_not_from_the_editor(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $draft = $this->draftFor($shop);

        // Usuwanie mieszka na liście (jak przy kodach rabatowych)…
        $this->actingAs($seller)->get(route('seller.mailings.index'))
            ->assertOk()
            ->assertSee('Usuń');

        // …a nie w edytorze.
        $this->actingAs($seller)->get(route('seller.mailings.edit', $draft))
            ->assertOk()
            ->assertDontSee('Usuń szkic');

        $this->actingAs($seller)->post(route('seller.mailings.destroy', $draft))->assertRedirect();
        $this->assertSame(0, $shop->bulkMailings()->count());
    }

    public function test_index_shows_recipient_count_and_availability(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $this->consentingCustomer($shop);

        $this->actingAs($seller)->get(route('seller.mailings.index'))
            ->assertOk()
            ->assertSee('Odbiorcy')
            ->assertSee('Możesz wysłać teraz');
    }

    public function test_test_send_goes_to_the_seller_and_leaves_the_draft_alone(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $this->consentingCustomer($shop);
        $mailing = $this->draftFor($shop);

        EmailMessage::query()->delete();
        $this->actingAs($seller);

        Livewire::test(BulkMailingSender::class, ['mailing' => $mailing])
            ->call('sendTest')
            ->assertOk()
            ->assertSee('sprzedawca@example.test');

        $this->assertSame(1, EmailMessage::where('to_email', 'sprzedawca@example.test')->count());
        $this->assertTrue($mailing->fresh()->isDraft());
    }

    public function test_sending_to_customers_requires_confirmation_and_happens_once(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $this->consentingCustomer($shop);
        $mailing = $this->draftFor($shop);

        EmailMessage::query()->delete();
        $this->actingAs($seller);

        $component = Livewire::test(BulkMailingSender::class, ['mailing' => $mailing])
            ->call('askSend')
            ->assertSet('confirming', true)
            ->assertSee('Wysłać tę wiadomość do 1 klienta?')
            ->call('send');

        $this->assertSame(1, EmailMessage::count());
        $this->assertTrue($mailing->fresh()->isSent());
        $component->assertSee('Wiadomość poszła do 1 klienta');
    }

    public function test_sent_mailing_can_no_longer_be_edited_or_deleted(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $mailing = BulkMailing::factory()->sent()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)->get(route('seller.mailings.edit', $mailing))
            ->assertOk()
            ->assertSee('jest tylko do odczytu');

        $this->actingAs($seller)->post(route('seller.mailings.update', $mailing), [
            'subject' => 'Podmieniony temat',
            'body' => 'Podmieniona treść',
        ])->assertForbidden();

        $this->actingAs($seller)->post(route('seller.mailings.destroy', $mailing))->assertForbidden();

        $this->assertSame($mailing->subject, $mailing->fresh()->subject);
    }

    public function test_cooldown_is_shown_instead_of_the_send_button(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $this->consentingCustomer($shop);

        Carbon::setTestNow(Carbon::parse('2026-07-28 20:00:00'));
        BulkMailing::factory()->sent()->create(['shop_id' => $shop->id]);

        Carbon::setTestNow(Carbon::parse('2026-07-30 09:00:00'));
        $this->actingAs($seller);

        Livewire::test(BulkMailingSender::class, ['mailing' => $this->draftFor($shop)])
            ->assertSee('Kolejną wiadomość wyślesz od 04.08.2026')
            ->assertDontSee('Wyślij do klientów (1)');

        Carbon::setTestNow();
    }

    public function test_seller_cannot_touch_a_mailing_of_another_shop(): void
    {
        [, $shop] = $this->sellerWithShop();
        $mailing = $this->draftFor($shop);

        [$intruder] = $this->sellerWithShop(email: 'obcy@example.test');

        $this->actingAs($intruder)->get(route('seller.mailings.edit', $mailing))->assertNotFound();

        Livewire::test(BulkMailingSender::class, ['mailing' => $mailing])
            ->call('send')
            ->assertForbidden();

        $this->assertTrue($mailing->fresh()->isDraft());
    }
}
