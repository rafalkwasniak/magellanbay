<?php

namespace Tests\Feature\Product;

use App\Enums\OptionGroupKind;
use App\Enums\PriceComponentKind;
use App\Models\Licensor;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\OrderService;
use App\Support\LicenceFees;
use App\Support\ProductConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opłaty licencyjne i rozbicie ceny (Etap 2, krok 4).
 *
 * ===========================================================================
 * REGUŁA: SUMA PO LICENCJODAWCACH, MAKSIMUM WEWNĄTRZ JEDNEGO
 * ===========================================================================
 *
 * Potwierdzona przez Rafała 05.09.2026 na czterech przypadkach — i te cztery
 * przypadki są tu przepisane jeden do jednego, bo to jedyne miejsce, w którym
 * błąd nie daje o sobie znać: klient płaci tyle, ile widzi, a rozjazd wychodzi
 * dopiero przy przelewie do partnera, kwartał później.
 *
 * Sens biznesowy: umowa licencyjna dotyczy PRAWA DO ZNAKU jednej firmy. Użycie
 * go dwa razy na jednym magnesie to nadal jedno użycie prawa, więc partner nie
 * inkasuje dwa razy. Dwie różne firmy to dwa różne prawa.
 */
class LicenceFeesTest extends TestCase
{
    use RefreshDatabase;

    // --- Sama reguła, na czystych liczbach ---------------------------------

    private function fee(?int $licensorId, float $amount): array
    {
        return ['licensor_id' => $licensorId, 'amount' => $amount];
    }

    public function test_one_licensor_two_licences_pays_the_higher_one(): void
    {
        $this->assertSame(8.0, LicenceFees::total([
            $this->fee(1, 5.00),
            $this->fee(1, 8.00),
        ]));
    }

    public function test_two_licensors_are_summed(): void
    {
        $this->assertSame(12.0, LicenceFees::total([
            $this->fee(1, 5.00),
            $this->fee(2, 7.00),
        ]));
    }

    public function test_the_mixed_case_from_the_agreement(): void
    {
        // Bieg Gdański 5 + Bieg Gdański 8 + PZLA 7 → 8 + 7 = 15.
        $this->assertSame(15.0, LicenceFees::total([
            $this->fee(1, 5.00),
            $this->fee(1, 8.00),
            $this->fee(2, 7.00),
        ]));
    }

    public function test_no_licences_cost_nothing(): void
    {
        $this->assertSame(0.0, LicenceFees::total([]));
    }

    /**
     * Odrzucona opłata ZNIKA z rozbicia, a nie zostaje jako zero. Pokazywanie
     * kupującemu wiersza „0,00 zł, bo tę licencję już policzyliśmy" byłoby
     * wyjaśnianiem naszej księgowości komuś, kto chce kupić magnes.
     */
    public function test_the_losing_fee_disappears_instead_of_showing_as_zero(): void
    {
        $reduced = LicenceFees::reduce([
            $this->fee(1, 5.00),
            $this->fee(1, 8.00),
        ]);

        $this->assertCount(1, $reduced);
        $this->assertSame(8.00, $reduced[0]['amount']);
    }

    /**
     * Opłata bez przypisanego partnera NIE podlega regule. Grupowanie po `null`
     * sklejałoby w jedną kupkę należności różnych podmiotów i po cichu obcinało
     * kwotę.
     */
    public function test_fees_without_a_licensor_are_summed_normally(): void
    {
        $this->assertSame(9.0, LicenceFees::total([
            $this->fee(null, 4.00),
            $this->fee(null, 5.00),
        ]));
    }

    /**
     * Ten sam koszyk musi zawsze dać ten sam wynik — przy remisie wygrywa
     * pierwsza pozycja. Losowanie zwycięzcy sprawiłoby, że dwa identyczne
     * zamówienia miałyby różne rozbicia.
     */
    public function test_a_tie_is_resolved_deterministically(): void
    {
        $fees = [
            ['licensor_id' => 1, 'amount' => 6.00, 'label' => 'pierwsza'],
            ['licensor_id' => 1, 'amount' => 6.00, 'label' => 'druga'],
        ];

        $this->assertSame('pierwsza', LicenceFees::reduce($fees)[0]['label']);
        $this->assertSame('pierwsza', LicenceFees::reduce($fees)[0]['label']);
    }

    // --- Reguła na prawdziwym produkcie ------------------------------------

    /**
     * @return array{0: Shop, 1: Product, 2: array<string, mixed>}
     */
    private function magnesZDwiemaLicencjami(): array
    {
        $shop = Shop::factory()->sellable()->create();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false,
            'name' => 'Magnes', 'price_gross' => 24.90,
        ]);

        $bieg = $shop->licensors()->create(['name' => 'Bieg Gdański']);
        $pzla = $shop->licensors()->create(['name' => 'PZLA']);

        // Awers: logotyp organizatora — opłata licencyjna, bez kosztu wykonania.
        $awers = $shop->optionGroups()->create(['name' => 'Logotyp', 'kind' => OptionGroupKind::Choice]);
        $logoBiegu = $awers->choices()->create([
            'label' => 'Bieg Gdański 2026', 'licensor_id' => $bieg->id, 'licence_fee_gross' => 5.00,
        ]);

        // Rewers: grawer — koszt WYKONANIA na grupie, licencja przy grafice.
        $rewers = $shop->optionGroups()->create([
            'name' => 'Grawer', 'kind' => OptionGroupKind::Choice, 'surcharge_gross' => 15.00,
        ]);
        $grafikaBiegu = $rewers->choices()->create([
            'label' => 'Trasa biegu', 'licensor_id' => $bieg->id, 'licence_fee_gross' => 8.00,
        ]);
        $grafikaPzla = $rewers->choices()->create([
            'label' => 'Znak PZLA', 'licensor_id' => $pzla->id, 'licence_fee_gross' => 7.00,
        ]);

        $product->optionGroups()->attach([$awers->id, $rewers->id]);

        return [$shop, $product->fresh(), [
            'awers' => $awers, 'rewers' => $rewers, 'bieg' => $bieg, 'pzla' => $pzla,
            'logoBiegu' => $logoBiegu, 'grafikaBiegu' => $grafikaBiegu, 'grafikaPzla' => $grafikaPzla,
        ]];
    }

    /**
     * DWIE LICENCJE TEJ SAMEJ FIRMY na jednym magnesie: logo Biegu Gdańskiego
     * na awersie (5 zł) i jego grafika na rewersie (8 zł). Płacimy 8, nie 13.
     */
    public function test_the_same_licensor_twice_on_one_product_is_charged_once(): void
    {
        [, $product, $x] = $this->magnesZDwiemaLicencjami();

        $config = [
            $x['awers']->id => ['choice' => $x['logoBiegu']->id],
            $x['rewers']->id => ['choice' => $x['grafikaBiegu']->id],
        ];

        // 24,90 produkt + 15,00 wykonanie graweru + 8,00 licencja (nie 5+8).
        $this->assertSame(23.00, ProductConfiguration::surcharge($product, $config));

        $licencje = array_values(array_filter(
            ProductConfiguration::breakdown($product, $config),
            fn ($c) => $c['kind'] === PriceComponentKind::Licence
        ));

        $this->assertCount(1, $licencje);
        $this->assertSame(8.00, $licencje[0]['amount']);
        $this->assertSame('Bieg Gdański', $licencje[0]['licensor_name']);
    }

    /**
     * DWIE RÓŻNE FIRMY: 5 zł Biegu + 7 zł PZLA = 12 zł. Sumujemy.
     */
    public function test_two_different_licensors_are_both_charged(): void
    {
        [, $product, $x] = $this->magnesZDwiemaLicencjami();

        $config = [
            $x['awers']->id => ['choice' => $x['logoBiegu']->id],
            $x['rewers']->id => ['choice' => $x['grafikaPzla']->id],
        ];

        // 15,00 wykonanie + 5,00 Bieg + 7,00 PZLA.
        $this->assertSame(27.00, ProductConfiguration::surcharge($product, $config));
    }

    /**
     * NIEZMIENNIK CAŁEGO MODUŁU: suma składników równa się cenie jednostkowej.
     * Rozbicie jest rozwinięciem ceny, nie notatką obok niej — gdyby się
     * rozjechało, klient płaciłby co innego, niż mówi mu ekran.
     */
    public function test_the_components_always_add_up_to_the_unit_price(): void
    {
        [$shop, $product, $x] = $this->magnesZDwiemaLicencjami();

        $config = [
            $x['awers']->id => ['choice' => $x['logoBiegu']->id],
            $x['rewers']->id => ['choice' => $x['grafikaPzla']->id],
        ];

        app(CartService::class)->add($product, 1, $config);
        $line = app(CartService::class)->lines($shop->id)->first();

        $suma = array_sum(array_column(
            ProductConfiguration::breakdown($product, $config),
            'amount'
        ));

        $this->assertSame($line['unit_price'], round($suma, 2));
        $this->assertSame(51.90, $line['unit_price']);   // 24,90 + 15 + 5 + 7
    }

    // --- Zapis na zamówieniu -----------------------------------------------

    /**
     * Rozbicie musi wylądować na pozycji zamówienia, bo to z niego powstaje
     * odpowiedź na pytanie „ile się należy Biegowi Gdańskiemu za marzec".
     */
    public function test_the_order_stores_the_full_breakdown(): void
    {
        [$shop, $product, $x] = $this->magnesZDwiemaLicencjami();

        app(CartService::class)->add($product, 3, [
            $x['awers']->id => ['choice' => $x['logoBiegu']->id],
            $x['rewers']->id => ['choice' => $x['grafikaPzla']->id],
        ]);

        app(OrderService::class)->place($shop, [
            'buyer_name' => 'Anna',
            'buyer_surname' => 'Nowak',
            'buyer_email' => 'anna@example.com',
            'buyer_phone' => '600100200',
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ]);

        $item = Order::query()->sole()->items()->sole();
        $components = $item->components;

        // Produkt + wykonanie graweru + dwie licencje.
        $this->assertCount(4, $components);
        $this->assertSame(PriceComponentKind::Product, $components[0]->kind);
        $this->assertSame('Magnes', $components[0]->label);

        // NIEZMIENNIK także po zapisie.
        $this->assertSame(
            (float) $item->unit_price_gross,
            round($components->sum(fn ($c) => (float) $c->unit_amount_gross), 2)
        );

        // Licencje wskazują partnerów — po tym idzie rozliczenie.
        $licencje = $components->where('kind', PriceComponentKind::Licence);
        $this->assertSame(
            ['Bieg Gdański', 'PZLA'],
            $licencje->pluck('licensor_name')->sort()->values()->all()
        );
    }

    /**
     * Migawka nazwy partnera przeżywa skasowanie kartoteki. Bez niej raport
     * sprzed roku zamieniłby się w listę kwot bez adresata.
     */
    public function test_the_licensor_name_survives_the_registry_being_cleaned_up(): void
    {
        [$shop, $product, $x] = $this->magnesZDwiemaLicencjami();

        app(CartService::class)->add($product, 1, [
            $x['awers']->id => ['choice' => $x['logoBiegu']->id],
        ]);

        app(OrderService::class)->place($shop, [
            'buyer_name' => 'Anna',
            'buyer_surname' => 'Nowak',
            'buyer_email' => 'anna@example.com',
            'buyer_phone' => '600100200',
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ]);

        $x['bieg']->delete();

        $licencja = Order::query()->sole()->items()->sole()
            ->components()->licences()->sole();

        $this->assertNull($licencja->licensor_id);
        $this->assertSame('Bieg Gdański', $licencja->licensor_name);
        $this->assertSame('5.00', $licencja->unit_amount_gross);
    }

    /**
     * Skasowanie partnera nie ma prawa zabrać grafiki z biblioteki — zdejmuje
     * tylko opłatę. To sprzedawca decyduje, czy grafika zostaje w sprzedaży.
     */
    public function test_deleting_a_licensor_keeps_the_graphic_in_the_library(): void
    {
        [, , $x] = $this->magnesZDwiemaLicencjami();

        $x['bieg']->delete();

        $this->assertNotNull($x['logoBiegu']->fresh());
        $this->assertNull($x['logoBiegu']->fresh()->licensor_id);
    }

    public function test_licensors_are_scoped_to_their_shop(): void
    {
        $moj = Shop::factory()->create();
        $cudzy = Shop::factory()->create();

        $moj->licensors()->create(['name' => 'Bieg Gdański']);
        $cudzy->licensors()->create(['name' => 'Cudzy Partner']);

        $this->assertSame(['Bieg Gdański'], $moj->licensors()->pluck('name')->all());
    }

    /**
     * Wycofanego partnera gasimy, nie kasujemy — rozliczenia historyczne muszą
     * dalej wskazywać, komu należały się pieniądze.
     */
    public function test_a_withdrawn_licensor_stays_in_the_registry(): void
    {
        $shop = Shop::factory()->create();
        $shop->licensors()->create(['name' => 'Bieg Gdański', 'is_active' => false]);

        $this->assertSame(1, $shop->licensors()->count());
        $this->assertSame(0, Licensor::query()->active()->count());
    }
}
