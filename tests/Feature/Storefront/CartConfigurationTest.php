<?php

namespace Tests\Feature\Storefront;

use App\Enums\OptionGroupKind;
use App\Models\OptionGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\OrderService;
use App\Support\ProductConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Koszyk rozpoznający personalizację (Etap 2, krok 2) — ŚCIEŻKA PIENIĘDZY.
 *
 * Sedno zmiany: kluczem pozycji przestał być `product_id`. Magnes z imieniem
 * „Zosia" i magnes z imieniem „Antek" to jeden produkt w katalogu, ale dwie
 * różne rzeczy do wykonania — muszą leżeć w koszyku osobno.
 *
 * Ten plik pilnuje czterech rzeczy, w których błąd kosztuje najwięcej:
 * rozdzielania pozycji, dopłaty w cenie, ODRZUCANIA konfiguracji niewykonalnych
 * oraz stanu magazynowego dzielonego między konfiguracje.
 */
class CartConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function cart(): CartService
    {
        return app(CartService::class);
    }

    /**
     * @return array{0: Shop, 1: Product, 2: OptionGroup}
     */
    private function sklepZFormatka(array $productAttributes = []): array
    {
        $shop = Shop::factory()->sellable()->create();

        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'is_active' => true,
            'track_stock' => false,
            'price_gross' => 24.90,
            ...$productAttributes,
        ]);

        $group = $shop->optionGroups()->create([
            'name' => 'Nadruk',
            'kind' => OptionGroupKind::Text,
            'surcharge_gross' => 10.00,
        ]);
        $group->fields()->create(['label' => 'Imię', 'max_length' => 12, 'required' => true]);

        $product->optionGroups()->attach($group);

        return [$shop, $product->fresh(), $group];
    }

    private function imie(OptionGroup $group, string $value): array
    {
        return [$group->id => ['fields' => [$group->fields->first()->id => $value]]];
    }

    // --- Rozdzielanie pozycji ---------------------------------------------

    public function test_two_names_are_two_separate_lines(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka();

        $this->cart()->add($product, 1, $this->imie($group, 'Zosia'));
        $this->cart()->add($product, 1, $this->imie($group, 'Antek'));

        $this->assertSame(2, $this->cart()->count($shop->id));
        $this->assertSame(2.0, $this->cart()->quantityOfProduct($shop->id, $product->id));
    }

    public function test_the_same_name_added_twice_is_one_line(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka();

        $this->cart()->add($product, 1, $this->imie($group, 'Zosia'));
        $this->cart()->add($product, 2, $this->imie($group, 'Zosia'));

        $this->assertSame(1, $this->cart()->count($shop->id));
        $this->assertSame(3.0, $this->cart()->quantityOfProduct($shop->id, $product->id));
    }

    /**
     * Białe znaki na końcu wpisu biorą się z wklejania i są niewidoczne dla
     * kupującego. Gdyby tworzyły osobną pozycję, koszyk pokazywałby dwa razy
     * to samo imię i dwie osobne ceny.
     */
    public function test_whitespace_does_not_create_a_second_line(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka();

        $this->cart()->add($product, 1, $this->imie($group, 'Zosia'));
        $this->cart()->add($product, 1, $this->imie($group, '  Zosia  '));

        $this->assertSame(1, $this->cart()->count($shop->id));
    }

    public function test_removing_one_configuration_leaves_the_other(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka();

        $this->cart()->add($product, 1, $this->imie($group, 'Zosia'));
        $this->cart()->add($product, 1, $this->imie($group, 'Antek'));

        $klucz = ProductConfiguration::key($product->id, $this->imie($group, 'Zosia'));
        $this->cart()->remove($shop->id, $klucz);

        $this->assertSame(1, $this->cart()->count($shop->id));
        $this->assertSame(
            'Antek',
            $this->cart()->lines($shop->id)->first()['personalisation'][0]['value']
        );
    }

    // --- Cena --------------------------------------------------------------

    public function test_the_surcharge_is_added_to_the_product_price(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka();

        $this->cart()->add($product, 2, $this->imie($group, 'Zosia'));
        $line = $this->cart()->lines($shop->id)->first();

        $this->assertSame(24.90, $line['base_price']);
        $this->assertSame(10.00, $line['surcharge']);
        $this->assertSame(34.90, $line['unit_price']);
        $this->assertSame(69.80, $line['line_total']);
    }

    /**
     * Produkt bez personalizacji ma zachowywać się dokładnie jak dotąd — sklep
     * Magellana sprzedaje jedno i drugie z tego samego katalogu.
     */
    public function test_a_plain_product_keeps_its_price_and_readable_key(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'price_gross' => 24.90,
        ]);

        $this->cart()->add($product, 1);
        $line = $this->cart()->lines($shop->id)->first();

        $this->assertSame('p'.$product->id, $line['key']);
        $this->assertSame(0.0, $line['surcharge']);
        $this->assertSame(24.90, $line['unit_price']);
        $this->assertSame([], $line['personalisation']);
    }

    /**
     * Ceny NIGDY nie ma w sesji — to zasada tego koszyka od początku. Podmiana
     * dopłaty w bazie ma się odbić na koszyku natychmiast, także na pozycji,
     * która leży w nim od wczoraj.
     */
    public function test_price_follows_the_database_not_the_session(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka();

        $this->cart()->add($product, 1, $this->imie($group, 'Zosia'));
        $group->update(['surcharge_gross' => 25.00]);

        $this->assertSame(49.90, $this->cart()->lines($shop->id)->first()['unit_price']);
    }

    // --- Odrzucanie konfiguracji niewykonalnych ----------------------------

    public function test_a_missing_required_field_keeps_the_product_out_of_the_cart(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka();
        $group->update(['required' => true]);

        $this->cart()->add($product->fresh(), 1, $this->imie($group, ''));
        $this->cart()->add($product->fresh(), 1, []);

        $this->assertSame(0, $this->cart()->count($shop->id));
    }

    /**
     * „WYMAGANE" NA POLU ZNACZY „wymagane, jeśli korzystasz z tej grupy",
     * a nie „musisz z niej skorzystać". Od tego drugiego jest `required` na
     * samej grupie.
     *
     * Bez tego rozróżnienia grupa opisana jako nieobowiązkowa zachowywała się
     * jak przymusowa: „Grawer — nieobowiązkowy" z wymaganym polem „Tekst" nie
     * dawał się przeskoczyć. Złapał to dopiero test formularza.
     */
    public function test_an_optional_group_can_be_skipped_even_with_required_fields(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka();

        $this->assertFalse((bool) $group->required);
        $this->assertTrue((bool) $group->fields->first()->required);

        $this->cart()->add($product, 1, []);

        $line = $this->cart()->lines($shop->id)->first();
        $this->assertSame(24.90, $line['unit_price']);   // bez dopłaty
        $this->assertSame([], $line['personalisation']);
    }

    /**
     * Limit znaków wynika z fizyki produktu. Przycięcie „Konstantyna" do
     * „Konstanty" wyprodukowałoby magnes z uciętym imieniem, za który klient
     * zapłacił i którego NIE MOŻE ZWRÓCIĆ — produkt personalizowany jest
     * wyłączony z prawa odstąpienia (art. 38 pkt 3 u.p.k.). Dlatego odrzucamy,
     * zamiast poprawiać.
     */
    public function test_a_too_long_value_is_rejected_not_truncated(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka();

        $this->cart()->add($product, 1, $this->imie($group, 'Konstantynopolitanka'));

        $this->assertSame(0, $this->cart()->count($shop->id));
    }

    public function test_a_group_from_another_product_is_refused(): void
    {
        [$shop, $product] = $this->sklepZFormatka();

        $obca = $shop->optionGroups()->create(['name' => 'Cudza', 'kind' => OptionGroupKind::Text]);
        $obca->fields()->create(['label' => 'Cokolwiek', 'max_length' => 10]);

        $this->cart()->add($product, 1, [$obca->id => ['fields' => [$obca->fields->first()->id => 'X']]]);

        $this->assertSame(0, $this->cart()->count($shop->id));
    }

    public function test_a_withdrawn_choice_cannot_be_bought(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false]);

        $group = $shop->optionGroups()->create(['name' => 'Grawer', 'kind' => OptionGroupKind::Choice]);
        $choice = $group->choices()->create(['label' => 'Kotwica', 'surcharge_gross' => 20.00]);
        $product->optionGroups()->attach($group);

        $choice->update(['is_active' => false]);

        $this->cart()->add($product->fresh(), 1, [$group->id => ['choice' => $choice->id]]);

        $this->assertSame(0, $this->cart()->count($shop->id));
    }

    /**
     * „Grawer to grafika ALBO tekst, nigdy oba" — wymaganie klienta, obsłużone
     * generycznie przez wykluczanie się grup.
     */
    public function test_mutually_exclusive_groups_cannot_both_be_filled(): void
    {
        [$shop, $product, $tekst] = $this->sklepZFormatka();

        $grafika = $shop->optionGroups()->create(['name' => 'Grawer graficzny', 'kind' => OptionGroupKind::Choice]);
        $choice = $grafika->choices()->create(['label' => 'Kotwica', 'surcharge_gross' => 20.00]);
        $product->optionGroups()->attach($grafika);
        $tekst->update(['excludes_group_id' => $grafika->id]);

        $this->cart()->add($product->fresh(), 1, [
            ...$this->imie($tekst, 'Zosia'),
            $grafika->id => ['choice' => $choice->id],
        ]);

        $this->assertSame(0, $this->cart()->count($shop->id));

        // Każda z osobna nadal działa.
        $this->cart()->add($product->fresh(), 1, $this->imie($tekst, 'Zosia'));
        $this->assertSame(1, $this->cart()->count($shop->id));
    }

    /**
     * Sprzedawca wycofał grafikę już PO włożeniu jej do koszyka. Pozycja jest
     * niewykonalna, więc wypada z komunikatem — lepiej powiedzieć to teraz niż
     * przyjąć zamówienie, którego nie da się zrealizować.
     */
    public function test_a_configuration_that_stopped_being_available_drops_out_with_a_notice(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka();

        $this->cart()->add($product, 1, $this->imie($group, 'Konstanty'));
        $this->assertSame(1, $this->cart()->count($shop->id));

        // Sprzedawca zacieśnia limit — wpisane imię już się nie mieści.
        $group->fields()->update(['max_length' => 5]);

        ['lines' => $lines, 'notices' => $notices] = $this->cart()->reconcile($shop->id);

        $this->assertCount(0, $lines);
        $this->assertNotEmpty($notices);
        $this->assertStringContainsString('Personalizacja', $notices[0]);
    }

    // --- Stan magazynowy ---------------------------------------------------

    /**
     * Stan dzieli się między WSZYSTKIE konfiguracje. Przycinanie każdej osobno
     * pozwoliłoby przy stanie 3 sztuk mieć 3 „dla Zosi" i 3 „dla Antka" —
     * sklep sprzedałby sześć sztuk czegoś, czego ma trzy.
     */
    public function test_stock_is_shared_between_configurations(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka(['track_stock' => true, 'stock' => 3]);

        $this->cart()->add($product, 2, $this->imie($group, 'Zosia'));
        $this->cart()->add($product, 2, $this->imie($group, 'Antek'));

        $this->assertSame(3.0, $this->cart()->quantityOfProduct($shop->id, $product->id));
    }

    public function test_reconcile_also_shares_stock_between_configurations(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka(['track_stock' => true, 'stock' => 10]);

        $this->cart()->add($product, 4, $this->imie($group, 'Zosia'));
        $this->cart()->add($product, 4, $this->imie($group, 'Antek'));

        $product->update(['stock' => 5]);

        $this->assertSame(5.0, $this->cart()->lines($shop->id)->sum('quantity'));
    }

    // --- Zgodność wstecz ---------------------------------------------------

    /**
     * Sesje żyją tygodniami, więc w dniu wdrożenia część ludzi ma w przeglądarce
     * koszyk w starym kształcie `[product_id => qty]`. Najgorszy moment na awarię
     * koszyka to ten, w którym ktoś właśnie kupuje.
     */
    public function test_a_cart_from_before_personalisation_still_works(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'price_gross' => 24.90,
        ]);

        session()->put('carts.'.$shop->id, [$product->id => 2.0]);

        $line = $this->cart()->lines($shop->id)->first();

        $this->assertSame('p'.$product->id, $line['key']);
        $this->assertSame(2.0, $line['quantity']);
        $this->assertSame(49.80, $line['line_total']);
    }

    // --- Zamówienie --------------------------------------------------------

    /**
     * Po złożeniu zamówienia personalizacja musi być na POZYCJI. Bez tego
     * sklep przyjął pieniądze za magnes z imieniem i nie wie, jakie to imię.
     */
    public function test_the_order_remembers_what_was_typed(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka();

        $this->cart()->add($product, 2, $this->imie($group, 'Zosia'));

        app(OrderService::class)->place($shop, [
            'buyer_name' => 'Anna',
            'buyer_surname' => 'Nowak',
            'buyer_email' => 'anna@example.com',
            'buyer_phone' => '600100200',
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ]);

        $item = Order::query()->sole()->items()->sole();

        $this->assertSame('34.90', $item->unit_price_gross);
        $this->assertSame('10.00', $item->personalisation_surcharge_gross);
        $this->assertSame('69.80', $item->line_total_gross);

        // Migawka dla człowieka — czytelna bez zaglądania do katalogu.
        $this->assertSame('Nadruk — Imię', $item->personalisation[0]['label']);
        $this->assertSame('Zosia', $item->personalisation[0]['value']);

        // Odpowiedź maszynowa — do odtworzenia zamówienia i pliku produkcyjnego.
        $this->assertSame('Zosia', $item->configuration[$group->id]['fields'][$group->fields->first()->id]);
    }

    /**
     * Migawka ma przeżyć skasowanie grupy — zamówienie sprzed miesiąca musi
     * nadal mówić, co wykonano, także gdy sprzedawca posprzątał bibliotekę.
     */
    public function test_the_snapshot_survives_the_group_being_deleted(): void
    {
        [$shop, $product, $group] = $this->sklepZFormatka();

        $this->cart()->add($product, 1, $this->imie($group, 'Zosia'));

        app(OrderService::class)->place($shop, [
            'buyer_name' => 'Anna',
            'buyer_surname' => 'Nowak',
            'buyer_email' => 'anna@example.com',
            'buyer_phone' => '600100200',
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ]);

        $group->delete();

        $item = Order::query()->sole()->items()->sole();
        $this->assertSame('Zosia', $item->personalisation[0]['value']);
        $this->assertSame('34.90', $item->unit_price_gross);
    }
}
