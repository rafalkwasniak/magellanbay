<?php

namespace Tests\Feature\Seller;

use App\Enums\ContentReportCategory;
use App\Enums\ContentReportStatus;
use App\Models\ContentReport;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Models\User;
use App\Support\Mode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zgłoszenia treści bezprawnych w panelu WŁAŚCICIELA sklepu dedykowanego.
 *
 * Sedno różnicy: w Kramio są trzy strony — zgłaszający, platforma (rozstrzyga)
 * i sprzedawca (dostaje zawiadomienie z art. 17). Podział nie jest organizacyjny,
 * na nim stoi nasza kwalifikacja jako dostawcy hostingu. W sklepie dedykowanym
 * podmiot jest JEDEN, więc zgłoszenie musi trafić do właściciela — a zawiadomienie
 * „dla sprzedawcy" traci sens, bo byłby to list od siebie do siebie.
 *
 * Każde zachowanie w PARZE „dedykowany / Kramio", jak w DedicatedModeTest.
 */
class SellerContentReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sklepDedykowany(): array
    {
        config()->set('shop.mode', Mode::DEDICATED);

        $owner = User::factory()->consented()->create();
        $shop = Shop::factory()->active()->create(['owner_id' => $owner->id]);

        return [$owner, $shop];
    }

    private function zgloszenie(?Shop $shop, array $atrybuty = []): ContentReport
    {
        $report = new ContentReport([
            'url' => 'https://przyklad.test/produkt/1-cos',
            'category' => ContentReportCategory::Counterfeit,
            'justification' => 'To jest podróbka naszego znaku towarowego, zarejestrowanego w EUIPO.',
            'reporter_email' => 'zglaszajacy@example.com',
            'good_faith' => true,
            ...$atrybuty,
        ]);
        $report->shop()->associate($shop);
        $report->save();

        return $report;
    }

    // --- Rozwiązywanie sklepu z adresu ------------------------------------

    /**
     * NAJWAŻNIEJSZY TEST TEJ FUNKCJI. Bez tego zgłoszenia zapisywały się
     * z pustym `shop_id`: adres „sklep.test/produkt/1" nie kończy się na
     * „.sklep.test", więc `shopFromUrl()` zwracał null. Skutek był całkowicie
     * cichy — zgłoszenie przyjęte, potwierdzenie wysłane, a w panelu pusto.
     */
    public function test_report_from_the_main_domain_finds_the_shop_when_dedicated(): void
    {
        [, $shop] = $this->sklepDedykowany();

        $this->assertTrue(
            $shop->is(ContentReport::shopFromUrl('https://'.$shop->host().'/produkt/12-magnes'))
        );
    }

    public function test_report_from_a_foreign_domain_finds_nothing(): void
    {
        $this->sklepDedykowany();

        $this->assertNull(ContentReport::shopFromUrl('https://zupelnie-obcy-serwis.test/cos'));
    }

    /**
     * W Kramio adres sklepu to nadal SUBDOMENA — ta zmiana nie ma prawa tego
     * ruszyć, bo tam każdy sklep siedzi pod własną etykietą.
     */
    public function test_subdomain_still_resolves_the_shop_in_saas(): void
    {
        $shop = Shop::factory()->create(['slug' => 'lemoniady']);

        $this->assertTrue(
            $shop->is(ContentReport::shopFromUrl('https://lemoniady.'.config('tenancy.central_domain').'/produkt/1'))
        );
    }

    // --- Panel właściciela ------------------------------------------------

    public function test_owner_sees_reports_about_their_shop(): void
    {
        [$owner, $shop] = $this->sklepDedykowany();
        $report = $this->zgloszenie($shop);

        $this->actingAs($owner)
            ->get(route('seller.reports.index'))
            ->assertOk()
            ->assertSee($report->reference())
            ->assertSee('zglaszajacy@example.com');
    }

    /**
     * Zgłoszenie bez sklepu (adres spoza tej instalacji) nie należy do nikogo
     * i nie ma prawa pojawić się w cudzym panelu.
     */
    public function test_reports_without_a_shop_stay_out_of_the_panel(): void
    {
        [$owner] = $this->sklepDedykowany();
        $obce = $this->zgloszenie(null);

        $this->actingAs($owner)
            ->get(route('seller.reports.index'))
            ->assertOk()
            ->assertDontSee($obce->reference());

        $this->actingAs($owner)->get(route('seller.reports.show', $obce))->assertNotFound();
    }

    /**
     * W Kramio zgłoszenia rozpatruje PLATFORMA i tak ma zostać — sprzedawca
     * rozstrzygający sprawę przeciwko własnemu sklepowi byłby sędzią we własnej
     * sprawie, a na tym podziale stoi nasza kwalifikacja z art. 6 DSA.
     */
    public function test_the_panel_screen_does_not_exist_in_saas(): void
    {
        $owner = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $owner->id]);
        $report = $this->zgloszenie($shop);

        $this->actingAs($owner)->get(route('seller.reports.index'))->assertNotFound();
        $this->actingAs($owner)->get(route('seller.reports.show', $report))->assertNotFound();
    }

    public function test_menu_shows_the_reports_entry_only_when_dedicated(): void
    {
        [$owner] = $this->sklepDedykowany();

        $this->actingAs($owner)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Zgłoszenia');
    }

    public function test_menu_hides_the_reports_entry_in_saas(): void
    {
        $owner = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $owner->id]);

        $html = $this->actingAs($owner)->get(route('seller.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('seller/zgloszenia', $html);
    }

    // --- Rozstrzygnięcie --------------------------------------------------

    public function test_owner_decides_and_the_reporter_is_notified(): void
    {
        [$owner, $shop] = $this->sklepDedykowany();
        $report = $this->zgloszenie($shop);

        $this->actingAs($owner)
            ->from(route('seller.reports.show', $report))
            ->post(route('seller.reports.decide', $report), [
                'status' => ContentReportStatus::Upheld->value,
                'decision_reason' => 'Sprawdziliśmy zdjęcie i rzeczywiście narusza cudzy znak towarowy, więc je wycofujemy.',
            ])
            ->assertRedirect(route('seller.reports.show', $report));

        $report->refresh();
        $this->assertSame(ContentReportStatus::Upheld, $report->status);
        $this->assertSame($owner->id, $report->decided_by);
        $this->assertNotNull($report->decided_at);

        $this->assertTrue(
            EmailMessage::query()->where('to_email', 'zglaszajacy@example.com')->exists()
        );
    }

    /**
     * Zawiadomienie z art. 17 idzie do SPRZEDAWCY o decyzji PLATFORMY. Przy
     * jednym podmiocie właściciel dostałby pismo urzędowe od siebie do siebie,
     * kilka sekund po kliknięciu przycisku.
     */
    public function test_the_owner_is_never_notified_about_their_own_decision(): void
    {
        [$owner, $shop] = $this->sklepDedykowany();
        $report = $this->zgloszenie($shop);

        $this->actingAs($owner)
            ->from(route('seller.reports.show', $report))
            ->post(route('seller.reports.decide', $report), [
                'status' => ContentReportStatus::Upheld->value,
                'decision_reason' => 'Sprawdziliśmy zdjęcie i rzeczywiście narusza cudzy znak towarowy, więc je wycofujemy.',
            ]);

        $this->assertFalse(
            EmailMessage::query()->where('to_email', $owner->email)->exists()
        );
    }

    /**
     * Bramka na powtórne rozstrzygnięcie: bez niej odświeżony formularz wysłałby
     * zgłaszającemu drugą, sprzeczną decyzję.
     */
    public function test_a_decided_report_cannot_be_decided_again(): void
    {
        [$owner, $shop] = $this->sklepDedykowany();
        $report = $this->zgloszenie($shop, [
            'status' => ContentReportStatus::Rejected,
        ]);
        $report->forceFill(['status' => ContentReportStatus::Rejected])->save();

        $this->actingAs($owner)
            ->from(route('seller.reports.show', $report))
            ->post(route('seller.reports.decide', $report), [
                'status' => ContentReportStatus::Upheld->value,
                'decision_reason' => 'Jednak zmieniam zdanie i uznaję to zgłoszenie w całości za zasadne.',
            ]);

        $this->assertSame(ContentReportStatus::Rejected, $report->fresh()->status);
    }

    public function test_a_decision_requires_a_real_justification(): void
    {
        [$owner, $shop] = $this->sklepDedykowany();
        $report = $this->zgloszenie($shop);

        $this->actingAs($owner)
            ->from(route('seller.reports.show', $report))
            ->post(route('seller.reports.decide', $report), [
                'status' => ContentReportStatus::Rejected->value,
                'decision_reason' => 'nie',
            ])
            ->assertSessionHasErrors('decision_reason');

        $this->assertSame(ContentReportStatus::New, $report->fresh()->status);
    }

    // --- Formularz publiczny ----------------------------------------------

    /**
     * Formularz w szacie SKLEPU: w sklepie dedykowanym nie ma operatora ani
     * „innych sklepów", więc tekst o niezależnych sprzedawcach byłby nieprawdą,
     * a bursztynowa skóra platformy wyglądałaby jak strona z obcego serwisu.
     */
    public function test_the_public_form_wears_the_shop_livery_when_dedicated(): void
    {
        [, $shop] = $this->sklepDedykowany();

        $html = $this->get(route('reports.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Jeśli w tym sklepie widzisz treść', $html);
        $this->assertStringContainsString($shop->name, $html);
        // Tokeny palety sklepu — dowód, że renderuje się layout storefrontu.
        $this->assertStringContainsString('--brand:', $html);
    }

    public function test_the_public_form_keeps_the_platform_livery_in_saas(): void
    {
        $html = $this->get(route('reports.create'))->assertOk()->getContent();

        $this->assertStringContainsString('prowadzą niezależni sprzedawcy', $html);
        $this->assertStringNotContainsString('--brand:', $html);
    }

    /**
     * Stopka storefrontu podpisywała się nazwą platformy na KAŻDEJ podstronie
     * sklepu. Klient kupił silnik na własny serwer — reklama naszego produktu
     * u niego to reklama, za którą nikt nie zapłacił, a do tego kierująca jego
     * kupujących do konkurencyjnej oferty sklepów.
     */
    public function test_the_storefront_footer_does_not_advertise_the_platform(): void
    {
        [, $shop] = $this->sklepDedykowany();

        $this->get('http://'.$shop->slug.'.'.config('tenancy.central_domain').'/')
            ->assertOk()
            ->assertDontSee('Sklep zbudowany na');
    }

    public function test_the_storefront_footer_still_signs_shops_in_saas(): void
    {
        $shop = Shop::factory()->active()->create();

        $this->get('http://'.$shop->slug.'.'.config('tenancy.central_domain').'/')
            ->assertOk()
            ->assertSee('Sklep zbudowany na');
    }
}
