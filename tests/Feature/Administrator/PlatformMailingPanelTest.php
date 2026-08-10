<?php

namespace Tests\Feature\Administrator;

use App\Enums\ConsentChannel;
use App\Livewire\Administrator\PlatformMailingRecipients;
use App\Livewire\Administrator\PlatformMailingSender;
use App\Models\EmailMessage;
use App\Models\PlatformMailing;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel „Wiadomości do sprzedawców": szkic (utworzenie, edycja, usunięcie),
 * wybór odbiorców checkboxami i wysyłka.
 *
 * Dostęp ma wyłącznie administrator — także do komponentów Livewire, bo te są
 * osobnym wejściem, nieobjętym middlewarem grupy tras.
 */
class PlatformMailingPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function consentingSeller(?string $surname = null, ?string $email = null): User
    {
        $seller = User::factory()->create([
            'surname' => $surname ?? fake()->lastName(),
            'email' => $email ?? fake()->unique()->safeEmail(),
        ]);

        $seller->setMarketingConsent(ConsentChannel::Email, true, '127.0.0.1');

        return $seller;
    }

    public function test_admin_can_open_the_list(): void
    {
        $this->consentingSeller();
        PlatformMailing::factory()->create(['subject' => 'Nowa funkcja w Kramio']);

        $this->actingAs($this->admin())
            ->get(route('administrator.mailings.index'))
            ->assertOk()
            ->assertSee('Nowa funkcja w Kramio')
            ->assertSee('Ze zgodą na oferty');
    }

    public function test_admin_can_save_a_draft_and_lands_on_its_editor(): void
    {
        $response = $this->actingAs($this->admin())->post(route('administrator.mailings.store'), [
            'subject' => 'Kurier pod adres',
            'body' => '<p>Od dziś nadasz paczkę kurierem.</p>',
        ]);

        $mailing = PlatformMailing::sole();

        $response->assertRedirect(route('administrator.mailings.edit', $mailing));
        $this->assertSame('Kurier pod adres', $mailing->subject);
        $this->assertFalse($mailing->isSent());
    }

    public function test_empty_body_is_rejected(): void
    {
        // Edytor potrafi przysłać sam znacznik bez tekstu — po sanitizacji
        // zostaje pustka i musi zadziałać `required`.
        $this->actingAs($this->admin())
            ->post(route('administrator.mailings.store'), ['subject' => 'Temat', 'body' => '<p></p>'])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, PlatformMailing::count());
    }

    public function test_sent_message_can_be_viewed_but_not_edited_or_deleted(): void
    {
        $mailing = PlatformMailing::factory()->sent()->create(['subject' => 'Poszło do ludzi']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('administrator.mailings.edit', $mailing))
            ->assertOk()
            ->assertSee('Poszło do ludzi');

        $this->actingAs($admin)
            ->post(route('administrator.mailings.update', $mailing), ['subject' => 'Zmiana', 'body' => '<p>Inna treść</p>'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('administrator.mailings.destroy', $mailing))
            ->assertForbidden();

        $this->assertSame('Poszło do ludzi', $mailing->fresh()->subject);
    }

    public function test_seller_cannot_reach_the_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('administrator.mailings.index'))
            ->assertForbidden();
    }

    public function test_recipients_default_to_everyone_with_consent(): void
    {
        $first = $this->consentingSeller();
        $second = $this->consentingSeller();
        User::factory()->create(); // bez zgody — nie ma go na liście

        $selected = Livewire::actingAs($this->admin())
            ->test(PlatformMailingRecipients::class, ['mailing' => PlatformMailing::factory()->create()])
            ->get('selected');

        // Porównujemy ZBIÓR, nie kolejność — lista idzie po nazwisku, a te
        // pochodzą z generatora, więc kolejność ID nic tu nie znaczy.
        sort($selected);
        $expected = [(string) $first->id, (string) $second->id];
        sort($expected);

        $this->assertSame($expected, $selected);
    }

    public function test_unchecking_someone_is_saved_immediately(): void
    {
        $keep = $this->consentingSeller();
        $drop = $this->consentingSeller();
        $mailing = PlatformMailing::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(PlatformMailingRecipients::class, ['mailing' => $mailing])
            ->set('selected', [(string) $keep->id]);

        // Bez osobnego „Zapisz" — wybór ma przetrwać powrót do pisania treści.
        $this->assertSame([$keep->id], $mailing->fresh()->recipientIds());
    }

    public function test_list_shows_the_shop_name_and_finds_people_by_it(): void
    {
        // Administrator kojarzy sprzedawcę po tym, co sprzedaje, szybciej niż
        // po nazwisku — więc nazwa sklepu jest i widoczna, i wyszukiwalna.
        $florist = $this->consentingSeller('Kruk', 'kruk@example.com');
        Shop::factory()->create(['name' => 'Kwiaciarnia Zosia', 'owner_id' => $florist->id]);

        $baker = $this->consentingSeller('Nowak', 'nowak@example.com');
        Shop::factory()->create(['name' => 'Piekarnia Pod Lipą', 'owner_id' => $baker->id]);

        Livewire::actingAs($this->admin())
            ->test(PlatformMailingRecipients::class, ['mailing' => PlatformMailing::factory()->create()])
            ->assertSee('Kwiaciarnia Zosia')
            ->assertSee('Piekarnia Pod Lipą')
            ->set('search', 'kwiaciarnia')
            ->assertSee('kruk@example.com')
            ->assertDontSee('nowak@example.com');
    }

    public function test_search_narrows_the_list_without_touching_the_selection(): void
    {
        $kowalski = $this->consentingSeller('Kowalski', 'kowalski@example.com');
        $nowak = $this->consentingSeller('Nowak', 'nowak@example.com');

        Livewire::actingAs($this->admin())
            ->test(PlatformMailingRecipients::class, ['mailing' => PlatformMailing::factory()->create()])
            ->set('search', 'kowal')
            ->assertSee('Kowalski')
            ->assertDontSee('nowak@example.com')
            // Zaznaczenie zostaje nietknięte — szukajka filtruje widok, nie wybór.
            ->assertSet('selected', [(string) $kowalski->id, (string) $nowak->id]);
    }

    public function test_select_and_deselect_act_on_the_filtered_list_only(): void
    {
        // Pułapka, o którą łatwo się potknąć: „odznacz" przy wpisanej frazie
        // nie może skasować wyboru osób spoza wyników.
        $kowalski = $this->consentingSeller('Kowalski', 'kowalski@example.com');
        $nowak = $this->consentingSeller('Nowak', 'nowak@example.com');
        $mailing = PlatformMailing::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(PlatformMailingRecipients::class, ['mailing' => $mailing])
            ->set('search', 'kowal')
            ->call('deselectVisible')
            ->assertSet('selected', [(string) $nowak->id]);

        $this->assertSame([$nowak->id], $mailing->fresh()->recipientIds());

        unset($kowalski);
    }

    public function test_deselect_all_without_a_search_clears_everyone(): void
    {
        $this->consentingSeller();
        $this->consentingSeller();
        $mailing = PlatformMailing::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(PlatformMailingRecipients::class, ['mailing' => $mailing])
            ->call('deselectVisible')
            ->assertSet('selected', []);

        // Pusta tablica, nie null — „świadomie nikogo" różni się od „nie wybierano".
        $this->assertSame([], $mailing->fresh()->recipientIds());
        $this->assertTrue($mailing->fresh()->hasRecipientSelection());
    }

    public function test_seller_cannot_change_recipients(): void
    {
        $this->consentingSeller();
        $mailing = PlatformMailing::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(PlatformMailingRecipients::class, ['mailing' => $mailing])
            ->call('deselectVisible')
            ->assertForbidden();
    }

    public function test_recipients_are_locked_after_sending(): void
    {
        $this->consentingSeller();
        $mailing = PlatformMailing::factory()->sent()->create();

        Livewire::actingAs($this->admin())
            ->test(PlatformMailingRecipients::class, ['mailing' => $mailing])
            ->call('deselectVisible')
            ->assertForbidden();
    }

    public function test_sender_shows_the_number_that_will_actually_go_out(): void
    {
        // Przycisk nie może obiecywać innej liczby, niż naprawdę pójdzie:
        // zaznaczony bez zgody się nie liczy.
        $consenting = $this->consentingSeller();
        $without = User::factory()->create();

        $mailing = PlatformMailing::factory()->create();
        $mailing->forceFill(['recipient_ids' => [$consenting->id, $without->id]])->save();

        Livewire::actingAs($this->admin())
            ->test(PlatformMailingSender::class, ['mailing' => $mailing])
            ->assertViewHas('recipients', 1)
            ->assertViewHas('eligible', 1);
    }

    public function test_admin_can_send_from_the_panel(): void
    {
        $this->consentingSeller(null, 'odbiorca@example.com');
        $mailing = PlatformMailing::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(PlatformMailingSender::class, ['mailing' => $mailing])
            ->call('askSend')
            ->assertSet('confirming', true)
            ->call('send')
            ->assertSet('error', null);

        $this->assertTrue($mailing->fresh()->isSent());
        $this->assertSame('odbiorca@example.com', EmailMessage::whereNotNull('platform_mailing_id')->sole()->to_email);
    }

    public function test_seller_cannot_send(): void
    {
        $this->consentingSeller();
        $mailing = PlatformMailing::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(PlatformMailingSender::class, ['mailing' => $mailing])
            ->call('send')
            ->assertForbidden();

        $this->assertFalse($mailing->fresh()->isSent());
    }

    public function test_sample_goes_to_the_logged_in_admin(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'rafal@example.com']);
        $mailing = PlatformMailing::factory()->create();

        Livewire::actingAs($admin)
            ->test(PlatformMailingSender::class, ['mailing' => $mailing])
            ->call('sendTest');

        $this->assertSame('rafal@example.com', EmailMessage::latest('id')->first()->to_email);
    }
}
