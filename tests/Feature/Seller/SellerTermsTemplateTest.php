<?php

namespace Tests\Feature\Seller;

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Support\SellerTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kreator regulaminu sklepu.
 *
 * DWIE REGUŁY NADRZĘDNE CAŁEGO MECHANIZMU:
 *
 * 1. Wstawienie NIE ZAPISUJE TREŚCI. Podstrona Regulamin jest zawsze
 *    opublikowana (`is_system`), więc zapis oznaczałby natychmiastową publikację
 *    dokumentu w imieniu sprzedawcy, zanim ten go przeczyta.
 *
 * 2. Odpowiedzi kreatora NIE WRACAJĄ do sklepu. Sprzedawca może podać w
 *    regulaminie inny adres niż w ustawieniach i to jego prawo — adres
 *    rejestrowy, zwrotów i kontaktowy bywają trzema różnymi rzeczami.
 *    „Kreator tworzy dokument, nie audytuje rzeczywistości" (Rafał, 16.08).
 */
class SellerTermsTemplateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop, 2: Page}
     */
    private function sklep(array $atrybuty = []): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->sellable()->create([
            'owner_id' => $seller->id,
            'company_name' => 'Domowe Lemoniady',
            'nip' => '5252248481',
            'contact_email' => 'sklep@example.com',
            ...$atrybuty,
        ]);

        // Stronę systemową zakłada ShopObserver przy tworzeniu sklepu.
        return [$seller, $shop, $shop->pages()->where('is_system', true)->firstOrFail()];
    }

    /**
     * @return array<string, string>
     */
    private function odpowiedzi(array $override = []): array
    {
        return [
            'seller_name' => 'Domowe Lemoniady',
            'nip' => '5252248481',
            'address' => 'Miętowa 7, 05-500 Piaseczno',
            'email' => 'sklep@example.com',
            'phone' => '+48 500 600 700',
            'return_address' => '',
            'shipping_days' => '2',
            'withdrawal_exclusions' => '',
            ...$override,
        ];
    }

    private function wstaw(User $seller, Page $page, array $odpowiedzi): string
    {
        return $this->actingAs($seller)
            ->from(route('seller.pages.edit', $page))
            ->followingRedirects()
            ->post(route('seller.pages.terms.insert', $page), $odpowiedzi)
            ->assertOk()
            ->getContent();
    }

    public function test_the_wizard_opens_prefilled_from_the_shop_profile(): void
    {
        [$seller, $shop, $page] = $this->sklep();

        $content = $this->actingAs($seller)
            ->from(route('seller.pages.edit', $page))
            ->followingRedirects()
            ->post(route('seller.pages.terms', $page))
            ->assertOk()
            ->getContent();

        // Adres z fabryki (`sellable()`), nie ze sklepu demo — podpowiedź ma
        // pochodzić z profilu TEGO sklepu.
        $this->assertStringContainsString($shop->addressLine(), $content);
        $this->assertStringContainsString('sklep@example.com', $content);
    }

    /**
     * Niekompletny profil NIE BLOKUJE. Sprzedawca dopisuje brakujące dane
     * w kreatorze, zamiast być odsyłanym na inny ekran, z którego musiałby wracać.
     */
    public function test_an_incomplete_profile_does_not_block_the_wizard(): void
    {
        [$seller, $shop, $page] = $this->sklep(['contact_email' => null, 'street' => null]);

        $this->actingAs($seller)
            ->from(route('seller.pages.edit', $page))
            ->followingRedirects()
            ->post(route('seller.pages.terms', $page))
            ->assertOk();

        $content = $this->wstaw($seller, $page, $this->odpowiedzi([
            'address' => 'Nowa 1, 00-001 Warszawa',
            'email' => 'dopisany@example.com',
        ]));

        $this->assertStringContainsString('Nowa 1, 00-001 Warszawa', $content);
        $this->assertStringContainsString('dopisany@example.com', $content);
    }

    public function test_the_template_lands_in_the_editor_but_not_in_the_database(): void
    {
        [$seller, $shop, $page] = $this->sklep();
        $przed = $page->content;

        $content = $this->wstaw($seller, $page, $this->odpowiedzi());

        $this->assertStringContainsString('§1 / Kto prowadzi sklep', $content);
        $this->assertSame($przed, $page->fresh()->content);
        $this->assertNull($page->fresh()->terms_template_version);
    }

    /**
     * Odpowiedzi zapisujemy (pamięć kreatora), ale do SKLEPU nie wraca nic.
     */
    public function test_answers_are_remembered_and_never_written_back_to_the_shop(): void
    {
        [$seller, $shop, $page] = $this->sklep();

        $this->wstaw($seller, $page, $this->odpowiedzi([
            'address' => 'Zupełnie Inna 9, 30-001 Kraków',
            'email' => 'inny@example.com',
        ]));

        $this->assertSame('Zupełnie Inna 9, 30-001 Kraków', $page->fresh()->terms_answers['address']);

        $shop->refresh();
        $this->assertSame('Kwiatowa', $shop->street);
        $this->assertSame('sklep@example.com', $shop->contact_email);
    }

    public function test_the_wizard_prefills_from_previous_answers(): void
    {
        [$seller, $shop, $page] = $this->sklep();
        $this->wstaw($seller, $page, $this->odpowiedzi(['shipping_days' => '5']));

        $dane = SellerTerms::defaults($shop->fresh(), $page->fresh());

        $this->assertSame('5', $dane['shipping_days']);
    }

    public function test_required_answers_are_validated_and_nothing_is_inserted(): void
    {
        [$seller, $shop, $page] = $this->sklep();

        $this->actingAs($seller)
            ->from(route('seller.pages.edit', $page))
            ->post(route('seller.pages.terms.insert', $page), $this->odpowiedzi([
                'shipping_days' => '', 'address' => '', 'email' => '',
            ]))
            ->assertSessionHasErrors(['shipping_days', 'address', 'email']);

        $this->assertNull($page->fresh()->terms_answers);
    }

    /**
     * §6 ust. 2 Regulaminu Kramio dopuszcza działalność nierejestrowaną — brak
     * NIP-u to poprawna odpowiedź, nie luka.
     */
    public function test_a_seller_without_a_vat_number_gets_the_unregistered_variant(): void
    {
        [$seller, $shop, $page] = $this->sklep();

        $wzor = SellerTerms::render($shop, $this->odpowiedzi(['nip' => null, 'seller_name' => 'Anna Nowak']));

        $this->assertStringContainsString('działalności nierejestrowanej', $wzor);
        $this->assertStringNotContainsString('NIP', $wzor);
    }

    public function test_the_document_comes_out_without_any_gaps_to_hunt_for(): void
    {
        [$seller, $shop, $page] = $this->sklep();

        $wzor = SellerTerms::render($shop, $this->odpowiedzi());

        $this->assertStringNotContainsString('UZUPEŁNIJ', $wzor);
    }

    public function test_the_template_describes_only_what_the_shop_really_offers(): void
    {
        // Pakiet może dopuszczać płatność online, ale bez wpiętej integracji
        // regulamin nie ma prawa jej obiecywać.
        [$seller, $shop, $page] = $this->sklep([
            'courier_enabled' => false,
            'parcel_locker_enabled' => false,
            'pickup_enabled' => true,
            'pay_on_pickup_enabled' => true,
            'bank_transfer_enabled' => false,
        ]);

        $wzor = SellerTerms::render($shop->fresh(), $this->odpowiedzi());

        $this->assertStringContainsString('odbiór osobisty', $wzor);
        $this->assertStringNotContainsString('kurier', $wzor);
        $this->assertStringNotContainsString('płatność online', $wzor);
    }

    public function test_own_content_is_flagged_before_it_gets_replaced(): void
    {
        [$seller, $shop, $page] = $this->sklep();
        $page->forceFill(['content' => '<div>Mój własny regulamin, pisany ręcznie.</div>'])->save();

        $content = $this->actingAs($seller)
            ->from(route('seller.pages.edit', $page))
            ->followingRedirects()
            ->post(route('seller.pages.terms', $page))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('wzór go zastąpi', $content);
        $this->assertStringContainsString('Mój własny regulamin', $page->fresh()->content);
    }

    public function test_saving_records_which_template_version_was_used(): void
    {
        [$seller, $shop, $page] = $this->sklep();

        $this->actingAs($seller)->post(route('seller.pages.update', $page), [
            'content' => SellerTerms::render($shop, $this->odpowiedzi()),
            'terms_template_version' => SellerTerms::VERSION,
        ])->assertRedirect();

        $this->assertSame(SellerTerms::VERSION, $page->fresh()->terms_template_version);
    }

    /**
     * Treść przechodzi przez `HtmlSanitizer`. Gdyby wzór używał tagów spoza jego
     * listy (np. `<p>`), sprzedawca zapisałby dokument bez struktury akapitów —
     * i nikt by tego nie zauważył, bo sam tekst by został.
     */
    public function test_the_template_survives_the_html_sanitizer(): void
    {
        [$seller, $shop, $page] = $this->sklep();

        $this->actingAs($seller)->post(route('seller.pages.update', $page), [
            'content' => SellerTerms::render($shop, $this->odpowiedzi()),
            'terms_template_version' => SellerTerms::VERSION,
        ]);

        $zapisane = $page->fresh()->content;

        foreach (['<h2>', '<div>', '<ol>', '<li>', '<strong>'] as $tag) {
            $this->assertStringContainsString($tag, $zapisane, "Sanitizer wyciął {$tag} — wzór traci strukturę.");
        }
        $this->assertSame(15, substr_count($zapisane, '<h2>'), 'Zniknął któryś paragraf wzoru.');
    }

    public function test_a_foreign_page_cannot_be_filled(): void
    {
        [$seller] = $this->sklep();
        [, , $obcaStrona] = $this->sklep(['slug' => 'inny-sklep']);

        $this->actingAs($seller)
            ->post(route('seller.pages.terms', $obcaStrona))
            ->assertNotFound();
    }

    public function test_a_regular_page_has_no_wizard(): void
    {
        [$seller, $shop] = $this->sklep();
        $zwykla = $shop->pages()->create([
            'title' => 'Dostawa', 'slug' => 'dostawa', 'content' => '<div>Wysyłamy szybko.</div>',
            'position' => 5, 'published' => true,
        ]);

        $this->actingAs($seller)
            ->get(route('seller.pages.edit', $zwykla))
            ->assertOk()
            ->assertDontSee('Wstaw wzór regulaminu');

        $this->actingAs($seller)
            ->post(route('seller.pages.terms', $zwykla))
            ->assertNotFound();
    }
}
