<?php

namespace Tests\Feature\Seller;

use App\Models\Shop;
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
}
