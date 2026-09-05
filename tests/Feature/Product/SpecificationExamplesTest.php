<?php

namespace Tests\Feature\Product;

use App\Enums\OptionGroupKind;
use App\Enums\PriceComponentKind;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\Shop;
use App\Support\ProductConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CZTERY PRZYKŁADY ZAKUPU WPROST ZE SPECYFIKACJI KLIENTA.
 *
 * Klient policzył je sam, ręcznie, i podał kwoty końcowe. To najostrzejszy
 * sprawdzian, jaki ten moduł może mieć: nie sprawdzamy, czy kod robi to, co
 * napisaliśmy w kodzie, tylko czy robi to, co zamawiający miał na myśli.
 *
 * Każdy test cytuje przykład, żeby po roku dało się porównać wynik z zamówieniem
 * bez otwierania dokumentu.
 *
 * @see docs_mod/07-specyfikacja-klienta.md
 */
class SpecificationExamplesTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shop = Shop::factory()->sellable()->create();
    }

    /**
     * Grupa „wykonanie graweru" — koszt na grupie, licencja przy grafice.
     * Dokładnie tak, jak dzieli to specyfikacja: trzeci składnik ceny to
     * „koszt ewentualnego wykonania grawerki", czwarty „cena licencji na
     * grafikę grawerki".
     */
    private function grawer(float $wykonanie = 10.00): OptionGroup
    {
        return $this->shop->optionGroups()->create([
            'name' => 'Grawer',
            'kind' => OptionGroupKind::Choice,
            'surcharge_gross' => $wykonanie,
        ]);
    }

    private function suma(Product $product, array $config): float
    {
        return round(array_sum(array_column(
            ProductConfiguration::breakdown($product, $config),
            'amount'
        )), 2);
    }

    /**
     * PRZYKŁAD 1 ze specyfikacji.
     *
     * „Metalowy magnes z oficjalnym logotypem 7 Maraton Wałbrzych. Cena 49 pln
     * … Organizator ustalił koszt licencji na swój logotyp na 25 pln … wybiera
     * grawerkę z BEZPŁATNYM logiem Kameralna Polska."
     *
     *   49 produkt + 25 licencja logotypu + 10 wykonanie graweru + 0 licencja
     *   grafiki = 84 pln
     */
    public function test_example_1_metal_magnet_with_licensed_logo_and_free_engraving(): void
    {
        $walbrzych = $this->shop->licensors()->create(['name' => '7 Maraton Wałbrzych']);

        $product = Product::factory()->create([
            'shop_id' => $this->shop->id, 'is_active' => true, 'track_stock' => false,
            'name' => 'Metalowy magnes', 'price_gross' => 49.00,
            'licensor_id' => $walbrzych->id, 'licence_fee_gross' => 25.00,
        ]);

        $grawer = $this->grawer();
        $kameralna = $grawer->choices()->create(['label' => 'Kameralna Polska', 'licence_fee_gross' => 0]);
        $product->optionGroups()->attach($grawer);

        $suma = $this->suma($product->fresh(), [$grawer->id => ['choice' => $kameralna->id]]);

        $this->assertSame(84.00, $suma);
    }

    /**
     * PRZYKŁAD 2 ze specyfikacji.
     *
     * „Kamienny magnes … kosztuje 30 pln, NIE JEST personalizowany … Klient
     * wybiera wygrawerowanie TEKSTU."
     *
     *   30 produkt + 0 licencji + 10 wykonanie graweru + 0 = 40 pln
     */
    public function test_example_2_stone_magnet_with_engraved_text(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id, 'is_active' => true, 'track_stock' => false,
            'name' => 'Kamienny magnes', 'price_gross' => 30.00,
        ]);

        // Grawer tekstowy — ten sam koszt wykonania co graficzny.
        $tekst = $this->shop->optionGroups()->create([
            'name' => 'Grawer — tekst',
            'kind' => OptionGroupKind::Text,
            'surcharge_gross' => 10.00,
        ]);
        $pole = $tekst->fields()->create(['label' => 'Tekst', 'max_length' => 200]);
        $product->optionGroups()->attach($tekst);

        $suma = $this->suma($product->fresh(), [
            $tekst->id => ['fields' => [$pole->id => 'Na Matterhorn wszedłem 15 lipca 2025 roku']],
        ]);

        $this->assertSame(40.00, $suma);
    }

    /**
     * PRZYKŁAD 3 ze specyfikacji — z zastrzeżeniem.
     *
     * „Magnes z ramką 3D … kosztuje w sklepie 15 pln … + 20 pln za produkt …
     * Razem: 20 pln". SPECYFIKACJA SAMA SOBIE PRZECZY: raz 15, raz 20.
     * Rozstrzygamy na korzyść kwoty użytej w wyliczeniu (20), bo to ona daje
     * podaną sumę — ale to jest do potwierdzenia z klientem.
     *
     * Test sprawdza RZECZ ISTOTNĄ i niesporną: personalizacja nadrukiem
     * NIC NIE KOSZTUJE. „Koszt produktu ZAWIERA koszt personalizacji awersu,
     * który nie jest wykazywany osobno".
     */
    public function test_example_3_front_print_costs_nothing_extra(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id, 'is_active' => true, 'track_stock' => false,
            'name' => 'Magnes 3D', 'price_gross' => 20.00,
        ]);

        // Formatka BEZ dopłaty — koszt nadruku siedzi w cenie produktu.
        $formatka = $this->shop->optionGroups()->create([
            'name' => 'Nadruk', 'kind' => OptionGroupKind::Text, 'surcharge_gross' => 0,
        ]);
        $bieg = $formatka->fields()->create(['label' => 'Nazwa biegu', 'max_length' => 30, 'position' => 0]);
        $rok = $formatka->fields()->create(['label' => 'Rok', 'max_length' => 4, 'position' => 1]);
        $wynik = $formatka->fields()->create(['label' => 'Wynik', 'max_length' => 10, 'position' => 2]);
        $product->optionGroups()->attach($formatka);

        $config = [$formatka->id => ['fields' => [
            $bieg->id => '7. Maraton Wałbrzych',
            $rok->id => '2026',
            $wynik->id => '03:33:31',
        ]]];

        $this->assertSame(20.00, $this->suma($product->fresh(), $config));

        // W rozbiciu jest SAM produkt — nadruk nie jest wykazywany osobno.
        $breakdown = ProductConfiguration::breakdown($product->fresh(), $config);
        $this->assertCount(1, $breakdown);
    }

    /**
     * PRZYKŁAD 4 ze specyfikacji — SEDNO REGUŁY LICENCJI.
     *
     * „Organizator ustalił koszt licencji na swój logotyp na 25 pln, oraz na
     * grafikę z grawerką na 40 pln … Ceny licencji na jednym produkcie się nie
     * sumują, więc zamiast +25 i +40 doliczamy wyższą cenę: +40 pln"
     *
     *   49 produkt + 40 licencja (nie 25+40) + 10 wykonanie graweru = 99 pln
     *
     * To jest ten przykład, dla którego licencja awersu MUSIAŁA przenieść się
     * z opcji na produkt: dopóki logotyp był wyborem kupującego, tej sytuacji
     * nie dało się nawet wyrazić.
     */
    public function test_example_4_two_licences_from_one_licensor_take_the_higher(): void
    {
        $walbrzych = $this->shop->licensors()->create(['name' => '7 Maraton Wałbrzych']);

        $product = Product::factory()->create([
            'shop_id' => $this->shop->id, 'is_active' => true, 'track_stock' => false,
            'name' => 'Metalowy magnes', 'price_gross' => 49.00,
            'licensor_id' => $walbrzych->id, 'licence_fee_gross' => 25.00,
        ]);

        $grawer = $this->grawer();
        $logoOrganizatora = $grawer->choices()->create([
            'label' => 'Logo organizatora',
            'licensor_id' => $walbrzych->id,
            'licence_fee_gross' => 40.00,
        ]);
        $product->optionGroups()->attach($grawer);

        $breakdown = ProductConfiguration::breakdown(
            $product->fresh(),
            [$grawer->id => ['choice' => $logoOrganizatora->id]]
        );

        $this->assertSame(99.00, round(array_sum(array_column($breakdown, 'amount')), 2));

        // JEDNA opłata licencyjna, ta wyższa — nie dwie.
        $licencje = array_values(array_filter(
            $breakdown,
            fn ($c) => $c['kind'] === PriceComponentKind::Licence
        ));
        $this->assertCount(1, $licencje);
        $this->assertSame(40.00, $licencje[0]['amount']);
    }

    /**
     * Wariant przykładu 4 z DWIEMA RÓŻNYMI firmami — nie ma go w specyfikacji,
     * rozstrzygnął go Rafał 05.09: różne firmy to różne prawa, więc sumujemy.
     *
     *   49 + 25 (Wałbrzych) + 10 wykonanie + 40 (PZLA) = 124 pln
     */
    public function test_two_different_licensors_are_both_charged(): void
    {
        $walbrzych = $this->shop->licensors()->create(['name' => '7 Maraton Wałbrzych']);
        $pzla = $this->shop->licensors()->create(['name' => 'PZLA']);

        $product = Product::factory()->create([
            'shop_id' => $this->shop->id, 'is_active' => true, 'track_stock' => false,
            'price_gross' => 49.00, 'licensor_id' => $walbrzych->id, 'licence_fee_gross' => 25.00,
        ]);

        $grawer = $this->grawer();
        $znakPzla = $grawer->choices()->create([
            'label' => 'Znak PZLA', 'licensor_id' => $pzla->id, 'licence_fee_gross' => 40.00,
        ]);
        $product->optionGroups()->attach($grawer);

        $this->assertSame(124.00, $this->suma(
            $product->fresh(),
            [$grawer->id => ['choice' => $znakPzla->id]]
        ));
    }

    /**
     * Produkt z licencją, ale BEZ grawerki. Opłata za logotyp awersu należy się
     * niezależnie od tego, czy kupujący cokolwiek wybrał — jest częścią produktu.
     */
    public function test_the_front_logo_licence_is_charged_even_without_engraving(): void
    {
        $walbrzych = $this->shop->licensors()->create(['name' => '7 Maraton Wałbrzych']);

        $product = Product::factory()->create([
            'shop_id' => $this->shop->id, 'is_active' => true, 'track_stock' => false,
            'price_gross' => 49.00, 'licensor_id' => $walbrzych->id, 'licence_fee_gross' => 25.00,
        ]);

        $this->assertSame(74.00, $this->suma($product, []));
    }
}
