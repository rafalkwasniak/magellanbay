<?php

namespace Tests\Feature\Seller;

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Support\Mode;
use App\Support\SellerPrivacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wzór polityki prywatności sklepu.
 *
 * Bliźniak wzoru regulaminu i pilnujemy tu tego samego: dokument ma opisywać
 * RZECZYWISTOŚĆ tego sklepu, a nie katalog możliwości. Polityka wymieniająca
 * operatora płatności w sklepie bez płatności online mówi klientowi nieprawdę
 * o jego własnych danych — i jest gorsza niż jej brak.
 *
 * @see App\Support\SellerPrivacy
 */
class SellerPrivacyTemplateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $dane
     */
    private function render(Shop $shop, array $dane = []): string
    {
        return SellerPrivacy::render($shop, $dane);
    }

    private function komplet(): array
    {
        return [
            'seller_name' => 'Magellan Bay sp. z o.o.',
            'nip' => '5252445767',
            'address' => 'Kwiatowa 12, 80-001 Gdańsk',
            'email' => 'kontakt@example.com',
            'phone' => '600 100 200',
        ];
    }

    public function test_it_uses_the_answers_it_was_given(): void
    {
        $html = $this->render(Shop::factory()->create(), $this->komplet());

        $this->assertStringContainsString('Magellan Bay sp. z o.o.', $html);
        $this->assertStringContainsString('5252445767', $html);
        $this->assertStringContainsString('Kwiatowa 12, 80-001 Gdańsk', $html);
        $this->assertStringContainsString('kontakt@example.com', $html);
    }

    /**
     * Przy szkicu przygotowywanym ZA klienta (wdrożenie dedykowane, zanim poda
     * dane firmowe) luka musi rzucać się w oczy w kilkunastu tysiącach znaków.
     * Stąd wielkie litery w nawiasach kwadratowych — nie klamry, bo klamra to
     * składnia Blade.
     */
    public function test_missing_answers_leave_visible_gaps(): void
    {
        $html = $this->render(Shop::factory()->create());

        $this->assertStringContainsString('[NAZWA_SPRZEDAWCY]', $html);
        $this->assertStringContainsString('[ADRES_SIEDZIBY]', $html);
        $this->assertStringContainsString('[ADRES_EMAIL]', $html);
        $this->assertStringNotContainsString('{', $html);
    }

    /**
     * Brak NIP-u to POPRAWNA odpowiedź (działalność nierejestrowana), więc nie
     * zamieniamy go na lukę — inaczej wzór kazałby uzupełnić coś, czego nie ma.
     */
    public function test_missing_nip_is_not_a_gap(): void
    {
        $dane = $this->komplet();
        $dane['nip'] = '';

        $html = $this->render(Shop::factory()->create(), $dane);

        $this->assertStringNotContainsString('[NIP]', $html);
        $this->assertStringNotContainsString('NIP ,', $html);
    }

    /**
     * `HtmlSanitizer`, przez który przechodzi zapis podstrony, nie ma `p` na
     * liście dozwolonych tagów — akapity w `<p>` zostałyby wycięte, zostawiając
     * zlepiony tekst. Ta sama reguła co we wzorze regulaminu.
     */
    public function test_it_uses_div_paragraphs_not_p(): void
    {
        $html = $this->render(Shop::factory()->create(), $this->komplet());

        $this->assertStringNotContainsString('<p>', $html);
        $this->assertStringContainsString('<div>', $html);
    }

    /**
     * Sklep bez wpiętego operatora płatności nie ma prawa go wymieniać wśród
     * odbiorców danych — nikt tam żadnych danych nie wysyła.
     */
    public function test_it_does_not_list_receivers_that_are_switched_off(): void
    {
        $html = $this->render(Shop::factory()->create(), $this->komplet());

        $this->assertStringNotContainsString('Operator płatności', $html);
        $this->assertStringNotContainsString('InPost', $html);
        $this->assertStringNotContainsString('narzędzia analitycznego', $html);
    }

    /**
     * Para „dedykowany / Kramio" — jak w DedicatedModeTest.
     *
     * W sklepie dedykowanym nie ma platformy ani podmiotu przetwarzającego:
     * właściciel jest administratorem i gospodarzem infrastruktury naraz.
     * Zdanie o powierzeniu danych Kramio byłoby tam wprost nieprawdziwe.
     */
    public function test_it_names_the_platform_as_processor_only_in_saas(): void
    {
        $shop = Shop::factory()->create();

        $this->assertStringContainsString('podmiot przetwarzający', $this->render($shop, $this->komplet()));

        config()->set('shop.mode', Mode::DEDICATED);

        $html = $this->render($shop, $this->komplet());
        $this->assertStringNotContainsString('podmiot przetwarzający', $html);
        $this->assertStringNotContainsString('Kramio', $html);
    }

    /**
     * Prawa podmiotu danych i organ nadzorczy to obowiązkowa treść z art. 13
     * RODO — bez nich dokument nie spełnia swojej funkcji.
     */
    public function test_it_covers_the_mandatory_rodo_content(): void
    {
        $html = $this->render(Shop::factory()->create(), $this->komplet());

        foreach ([
            'Administratorem Twoich danych',
            'art. 6 ust. 1 lit. b RODO',
            'art. 6 ust. 1 lit. c RODO',
            'art. 6 ust. 1 lit. a RODO',
            'art. 6 ust. 1 lit. f RODO',
            'Prezesa Urzędu Ochrony Danych Osobowych',
            'sprzeciwu',
            'cofnięcia zgody',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $html);
        }
    }

    // --- Strona systemowa i kreator w panelu -------------------------------

    /**
     * @return array{0: User, 1: Shop, 2: Page}
     */
    private function sklepDedykowany(): array
    {
        // Tryb ustawiamy PRZED utworzeniem sklepu: stronę zakłada ShopObserver
        // w chwili zapisu, więc później byłoby już po sprawie.
        config()->set('shop.mode', Mode::DEDICATED);

        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->active()->create([
            'owner_id' => $seller->id,
            'company_name' => 'Magellan Bay sp. z o.o.',
            'nip' => '5252445767',
            'contact_email' => 'sklep@example.com',
        ]);

        $page = $shop->pages()->where('slug', config('pages.privacy.slug'))->firstOrFail();

        return [$seller, $shop, $page];
    }

    /**
     * Sedno całej zmiany. W Kramio politykę pisze platforma — jest podmiotem
     * przetwarzającym i to ona wie, co dzieje się z danymi. W sklepie
     * dedykowanym platformy nie ma, więc dokument musi należeć do sklepu
     * i dać się edytować w panelu.
     */
    public function test_dedicated_shop_gets_its_own_privacy_page(): void
    {
        [, $shop, $page] = $this->sklepDedykowany();

        $this->assertTrue((bool) $page->is_system);
        $this->assertTrue((bool) $page->published);
        $this->assertSame(config('pages.privacy.title'), $page->title);
        $this->assertSame(2, $shop->pages()->count());
    }

    public function test_saas_shop_does_not_get_a_privacy_page(): void
    {
        $shop = Shop::factory()->create();

        $this->assertFalse(
            $shop->pages()->where('slug', config('pages.privacy.slug'))->exists()
        );
        $this->assertSame(1, $shop->pages()->count());
    }

    /**
     * Polityka ma STAŁY adres i jest doklejana na końcu działu „Informacje".
     * Gdyby wchodziła dodatkowo przez pętlę po stronach, w menu byłaby dwa razy.
     */
    public function test_privacy_appears_exactly_once_in_the_information_menu(): void
    {
        [, $shop] = $this->sklepDedykowany();

        $etykiety = array_column($shop->informationMenu(), 'label');
        $adresy = array_column($shop->informationMenu(), 'url');

        $this->assertSame(1, count(array_keys($etykiety, config('pages.privacy.title'), true)));
        $this->assertContains($shop->privacyPath(), $adresy);
        $this->assertSame(config('pages.privacy.title'), end($etykiety));
    }

    public function test_the_wizard_opens_prefilled_from_the_shop_profile(): void
    {
        [$seller, , $page] = $this->sklepDedykowany();

        $this->actingAs($seller)
            ->from(route('seller.pages.edit', $page))
            ->followingRedirects()
            ->post(route('seller.pages.privacy', $page))
            ->assertOk()
            ->assertSee('Wzór polityki prywatności')
            ->assertSee('Magellan Bay sp. z o.o.', false)
            ->assertSee('sklep@example.com', false);
    }

    /**
     * Ta sama reguła nadrzędna co przy regulaminie: wstawienie NIE ZAPISUJE
     * TREŚCI. Strona systemowa jest zawsze opublikowana, więc zapis oznaczałby
     * publikację dokumentu prawnego w imieniu właściciela, zanim go przeczyta.
     */
    public function test_inserting_does_not_publish_the_document(): void
    {
        [$seller, , $page] = $this->sklepDedykowany();
        $przed = $page->content;

        $this->actingAs($seller)
            ->from(route('seller.pages.edit', $page))
            ->followingRedirects()
            ->post(route('seller.pages.privacy.insert', $page), [
                'seller_name' => 'Magellan Bay sp. z o.o.',
                'nip' => '5252445767',
                'address' => 'Kwiatowa 12, 80-001 Gdańsk',
                'email' => 'kontakt@example.com',
                'phone' => '',
            ])
            ->assertOk()
            ->assertSee('Administratorem Twoich danych', false);

        $this->assertSame($przed, $page->fresh()->content);
    }

    /**
     * Odpowiedzi zapisujemy od razu — to pamięć kreatora, nie dokument. Dzięki
     * temu po poprawkach właściciel nie przepisuje wszystkiego od nowa.
     */
    public function test_answers_are_remembered_but_never_written_back_to_the_shop(): void
    {
        [$seller, $shop, $page] = $this->sklepDedykowany();

        $this->actingAs($seller)
            ->from(route('seller.pages.edit', $page))
            ->post(route('seller.pages.privacy.insert', $page), [
                'seller_name' => 'Zupełnie Inna Nazwa',
                'nip' => '',
                'address' => 'Inna 1, 00-001 Warszawa',
                'email' => 'inny@example.com',
                'phone' => '',
            ]);

        $this->assertSame('Zupełnie Inna Nazwa', $page->fresh()->terms_answers['seller_name']);

        // Kreator tworzy dokument, nie audytuje rzeczywistości — dane sklepu
        // (stopka, faktury) zostają nietknięte.
        $this->assertSame('Magellan Bay sp. z o.o.', $shop->fresh()->company_name);
        $this->assertSame('sklep@example.com', $shop->fresh()->contact_email);
    }

    public function test_the_wizard_requires_the_administrator_identity(): void
    {
        [$seller, , $page] = $this->sklepDedykowany();

        $this->actingAs($seller)
            ->from(route('seller.pages.edit', $page))
            ->post(route('seller.pages.privacy.insert', $page), [
                'seller_name' => '',
                'address' => '',
                'email' => 'to-nie-jest-email',
            ])
            ->assertSessionHasErrors(['seller_name', 'address', 'email']);
    }
}
