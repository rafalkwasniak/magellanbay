<?php

namespace Tests\Feature;

use App\Enums\ContentReportCategory;
use App\Enums\ContentReportStatus;
use App\Models\ContentReport;
use App\Models\EmailMessage;
use App\Models\Product;
use App\Models\Shop;
use App\Services\ContentReportMailer;
use App\Support\Central;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zgłaszanie treści bezprawnych — mechanizm z art. 16 DSA.
 *
 * Formularz jest publiczny (bez logowania), bo „łatwo dostępny" to wymóg, nie
 * wygoda. Sklep rozwiązujemy z ADRESU po stronie serwera — pole z formularza nie
 * może decydować, kogo zgłoszenie dotyczy.
 */
class ContentReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $override = []): array
    {
        return [
            'url' => 'https://bukiety.'.config('tenancy.central_domain').'/produkt/12-bukiet',
            'category' => ContentReportCategory::Counterfeit->value,
            'justification' => 'To zdjęcie jest moje, zrobiłem je do własnego katalogu i nie wyrażałem zgody na użycie.',
            'reporter_email' => 'Zglaszajacy@Example.COM',
            'reporter_name' => '  Anna   Nowak  ',
            'good_faith' => '1',
            ...$override,
        ];
    }

    public function test_the_form_is_open_without_logging_in(): void
    {
        $this->get(route('reports.create'))
            ->assertOk()
            ->assertSee('Zgłoś nielegalną treść');
    }

    public function test_a_report_lands_in_the_register_and_is_tied_to_the_shop_from_the_url(): void
    {
        $shop = Shop::factory()->create(['slug' => 'bukiety']);

        $this->post(route('reports.store'), $this->payload())
            ->assertRedirect(route('reports.create'))
            // `success` = zielony toast; `status` dałby bursztynowy, informacyjny.
            ->assertSessionHas('success');

        $report = ContentReport::firstOrFail();
        $this->assertTrue($shop->is($report->shop));
        $this->assertSame(ContentReportStatus::New, $report->status);
        $this->assertSame(ContentReportCategory::Counterfeit, $report->category);
        $this->assertTrue($report->good_faith);
        // Normalizacja w prepareForValidation: e-mail małymi, nazwisko bez zdublowanych spacji.
        $this->assertSame('zglaszajacy@example.com', $report->reporter_email);
        $this->assertSame('Anna Nowak', $report->reporter_name);
        $this->assertNotNull($report->ip_address);
    }

    public function test_the_reporter_gets_an_acknowledgement(): void
    {
        $this->post(route('reports.store'), $this->payload());

        $report = ContentReport::firstOrFail();
        $this->assertNotNull($report->acknowledged_at);

        $mail = EmailMessage::where('to_email', 'zglaszajacy@example.com')->firstOrFail();
        // Numer sprawy w temacie — po nim odnajdujemy wątek przy odpowiedzi.
        $this->assertSame('Zgłoszenie '.$report->reference().' — potwierdzenie odbioru', $mail->subject);
        $this->assertSame(config('company.abuse_email'), $mail->reply_to);
        // Bez `shop_id`: to korespondencja platformy, nie sklepu. Pismo przeciwko
        // sklepowi w jego własnej szacie byłoby mylące.
        $this->assertNull($mail->shop_id);
    }

    public function test_the_acknowledgement_is_sent_once(): void
    {
        $this->post(route('reports.store'), $this->payload());
        app(ContentReportMailer::class)->acknowledge(ContentReport::firstOrFail());

        $this->assertSame(1, EmailMessage::where('to_email', 'zglaszajacy@example.com')->count());
    }

    public function test_an_upheld_report_notifies_both_sides(): void
    {
        $shop = Shop::factory()->create(['slug' => 'bukiety']);
        $this->post(route('reports.store'), $this->payload());

        $report = ContentReport::firstOrFail();
        $report->forceFill([
            'status' => ContentReportStatus::Upheld,
            'decision_reason' => 'Zdjęcie pochodzi z katalogu zgłaszającego i zostało użyte bez zgody.',
        ])->save();

        $mailer = app(ContentReportMailer::class);
        $mailer->decision($report);
        $mailer->statementOfReasons($report->refresh());

        $doZglaszajacego = EmailMessage::where('subject', 'Zgłoszenie '.$report->reference().' — rozstrzygnięcie')->firstOrFail();
        $this->assertSame('zglaszajacy@example.com', $doZglaszajacego->to_email);

        $doSprzedawcy = EmailMessage::where('subject', 'Zgłoszenie '.$report->reference().' — ograniczyliśmy treść w Twoim sklepie')->firstOrFail();
        $this->assertSame($shop->owner->email, $doSprzedawcy->to_email);
    }

    /**
     * Art. 17 dotyczy sytuacji, w której faktycznie ograniczamy treść. Przy
     * odrzuceniu sprzedawcy nic się nie dzieje, więc zawiadamianie go o cudzych
     * zarzutach, których nie podzieliliśmy, tylko by go niepokoiło.
     */
    public function test_a_rejected_report_does_not_disturb_the_seller(): void
    {
        $shop = Shop::factory()->create(['slug' => 'bukiety']);
        $this->post(route('reports.store'), $this->payload());

        $report = ContentReport::firstOrFail();
        $report->forceFill([
            'status' => ContentReportStatus::Rejected,
            'decision_reason' => 'Zgłaszający nie wykazał praw do zdjęcia.',
        ])->save();

        $mailer = app(ContentReportMailer::class);
        $mailer->decision($report);
        $mailer->statementOfReasons($report);

        $this->assertSame(1, EmailMessage::where('subject', 'like', '%rozstrzygnięcie')->count());
        $this->assertSame(0, EmailMessage::where('to_email', $shop->owner->email)->count());
    }

    /**
     * Link w stopce storefrontu MUSI celować w centralę.
     *
     * `route()` renderowane na subdomenie zbudowałoby adres tej subdomeny, czyli
     * sklepu, którego zgłoszenie dotyczy — formularz wyglądałby na skrzynkę
     * osoby, której treść się kwestionuje. Stąd `Central::url()` w komponencie.
     */
    public function test_the_storefront_footer_points_at_the_central_form(): void
    {
        // Sklep musi mieć aktywny produkt — bez niego storefront pokazuje
        // „już wkrótce", które nie renderuje pełnej stopki (i słusznie: nie ma
        // tam jeszcze cudzych treści do zgłaszania).
        $shop = Shop::factory()->sellable()->create(['slug' => 'bukiety']);
        Product::factory()->create(['shop_id' => $shop->id]);

        $content = $this->get('https://'.$shop->fresh()->host().'/')->getContent();

        $this->assertStringContainsString('Zgłoś nielegalną treść', $content);
        $this->assertStringContainsString(Central::url('/zglos-tresc'), $content);
        $this->assertStringNotContainsString('https://'.$shop->host().'/zglos-tresc', $content);
    }

    public function test_a_report_about_an_unknown_address_is_still_accepted(): void
    {
        // Adres spoza Kramio albo literówka w subdomenie NIE może odbić zgłoszenia:
        // ocena, czego dotyczy, należy do nas, nie do zgłaszającego.
        $this->post(route('reports.store'), $this->payload(['url' => 'https://obcy-serwis.example/produkt/1']))
            ->assertSessionHasNoErrors();

        $this->assertNull(ContentReport::firstOrFail()->shop_id);
    }

    public function test_the_shop_cannot_be_pointed_at_by_a_smuggled_field(): void
    {
        $shop = Shop::factory()->create(['slug' => 'bukiety']);
        $inny = Shop::factory()->create(['slug' => 'ciuszki']);

        $this->post(route('reports.store'), $this->payload(['shop_id' => $inny->id]));

        $this->assertTrue($shop->is(ContentReport::firstOrFail()->shop));
    }

    public function test_a_report_without_the_good_faith_statement_is_rejected(): void
    {
        $this->post(route('reports.store'), $this->payload(['good_faith' => null]))
            ->assertSessionHasErrors('good_faith');

        $this->assertSame(0, ContentReport::count());
    }

    public function test_a_one_sentence_justification_is_not_enough(): void
    {
        $this->post(route('reports.store'), $this->payload(['justification' => 'Bo tak.']))
            ->assertSessionHasErrors('justification');
    }

    public function test_the_honeypot_stops_a_bot(): void
    {
        $this->post(route('reports.store'), $this->payload(['website' => 'https://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertSame(0, ContentReport::count());
    }

    /**
     * `www` to druga nazwa centrali, a nie sklep o slugu `www` — tak samo
     * traktuje ją `ResolveShop` przy zwykłym ruchu.
     */
    public function test_central_and_multi_label_addresses_do_not_resolve_to_a_shop(): void
    {
        Shop::factory()->create(['slug' => 'bukiety']);
        $domain = config('tenancy.central_domain');

        $this->assertNull(ContentReport::shopFromUrl('https://www.'.$domain.'/regulamin'));
        $this->assertNull(ContentReport::shopFromUrl('https://'.$domain.'/regulamin'));
        $this->assertNull(ContentReport::shopFromUrl('https://cos.bukiety.'.$domain.'/'));
        $this->assertNull(ContentReport::shopFromUrl('nie-adres'));
    }
}
