<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Enums\PriceComponentKind;
use App\Models\Licensor;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\LicensorSettlement;
use App\Support\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

/**
 * Rozliczenia z partnerami licencyjnymi (Etap 2, krok G).
 *
 * „Ile sztuk sprzedano z logo Biegu Gdańskiego w marcu i ile się im należy" —
 * pytanie postawione przez klienta, na które ten moduł odpowiada.
 *
 * Tu chodzi o PIENIĄDZE WYPŁACANE NA ZEWNĄTRZ, więc testy pilnują nie tego,
 * czy ekran się wyświetla, tylko czy kwota jest właściwa: co wchodzi, co
 * odpada i co odejmuje.
 */
class LicensorSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private Licensor $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->consented()->create();
        $this->shop = Shop::factory()->create(['owner_id' => $this->owner->id]);
        $this->partner = $this->shop->licensors()->create(['name' => 'Bieg Gdański', 'slug' => 'bieg-gdanski']);
    }

    /**
     * Zamówienie z jedną pozycją i jednym składnikiem licencyjnym.
     */
    private function sprzedaz(
        float $unitFee,
        float $quantity = 1,
        OrderStatus $status = OrderStatus::Paid,
        ?Carbon $when = null,
        float $returned = 0,
        ?Licensor $licensor = null,
        ?string $snapshotName = null,
    ): Order {
        $when ??= Carbon::parse('2026-03-15 12:00');
        $licensor ??= $this->partner;

        $order = Order::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => $status,
            'created_at' => $when,
        ]);

        $product = Product::factory()->create(['shop_id' => $this->shop->id]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => $quantity,
            'returned_quantity' => $returned,
        ]);

        $item->components()->create([
            'kind' => PriceComponentKind::Licence,
            'label' => 'Logotyp — '.$licensor->name,
            'licensor_id' => $licensor->id,
            'licensor_name' => $snapshotName ?? $licensor->name,
            'unit_amount_gross' => $unitFee,
            'position' => 0,
        ]);

        return $order;
    }

    private function marzec(): array
    {
        return [Carbon::parse('2026-03-01'), Carbon::parse('2026-04-01')];
    }

    private function rozliczenie(): LicensorSettlement
    {
        return app(LicensorSettlement::class);
    }

    // --- Co wchodzi do kwoty -------------------------------------------------

    public function test_fee_is_multiplied_by_quantity(): void
    {
        $this->sprzedaz(unitFee: 25, quantity: 3);

        [$from, $to] = $this->marzec();
        $summary = $this->rozliczenie()->summary($this->shop, $from, $to);

        $this->assertCount(1, $summary);
        $this->assertSame('Bieg Gdański', $summary[0]->name);
        $this->assertSame(75.0, $summary[0]->amount);
        $this->assertSame(3.0, $summary[0]->quantity);
    }

    /**
     * Klient oddał magnes, umowa się cofnęła — licencja się nie należy.
     */
    public function test_a_return_reduces_what_is_due(): void
    {
        $this->sprzedaz(unitFee: 25, quantity: 3, returned: 1);

        [$from, $to] = $this->marzec();
        $summary = $this->rozliczenie()->summary($this->shop, $from, $to);

        $this->assertSame(50.0, $summary[0]->amount);
    }

    public function test_a_fully_returned_item_disappears_from_the_settlement(): void
    {
        $this->sprzedaz(unitFee: 25, quantity: 2, returned: 2);

        [$from, $to] = $this->marzec();

        $this->assertCount(0, $this->rozliczenie()->summary($this->shop, $from, $to));
    }

    public function test_cancelled_orders_do_not_count_at_all(): void
    {
        $this->sprzedaz(unitFee: 25, status: OrderStatus::Cancelled);

        [$from, $to] = $this->marzec();

        $this->assertCount(0, $this->rozliczenie()->summary($this->shop, $from, $to));
    }

    /**
     * Sprzedaż jest, pieniędzy jeszcze nie ma. Kwota wchodzi, ale musi być
     * wykazana osobno — decyzja, czy płacić z góry, należy do właściciela.
     */
    public function test_unpaid_orders_count_but_are_shown_separately(): void
    {
        $this->sprzedaz(unitFee: 25, status: OrderStatus::Paid);
        $this->sprzedaz(unitFee: 10, status: OrderStatus::AwaitingPayment);

        [$from, $to] = $this->marzec();
        $summary = $this->rozliczenie()->summary($this->shop, $from, $to);

        $this->assertSame(35.0, $summary[0]->amount);
        $this->assertSame(10.0, $summary[0]->unpaid);
    }

    public function test_only_the_chosen_month_is_counted(): void
    {
        $this->sprzedaz(unitFee: 25, when: Carbon::parse('2026-03-15'));
        $this->sprzedaz(unitFee: 99, when: Carbon::parse('2026-02-28 23:59'));
        $this->sprzedaz(unitFee: 77, when: Carbon::parse('2026-04-01 00:01'));

        [$from, $to] = $this->marzec();
        $summary = $this->rozliczenie()->summary($this->shop, $from, $to);

        $this->assertSame(25.0, $summary[0]->amount);
    }

    /**
     * Zakres jest domknięty od lewej i otwarty od prawej. Zamówienie z 31 marca
     * o 23:30 musi wpaść do marca, a nie wypaść ze wszystkich rozliczeń.
     */
    public function test_the_last_evening_of_the_month_belongs_to_that_month(): void
    {
        $this->sprzedaz(unitFee: 25, when: Carbon::parse('2026-03-31 23:30'));

        [$from, $to] = $this->marzec();

        $this->assertSame(25.0, $this->rozliczenie()->summary($this->shop, $from, $to)[0]->amount);
    }

    public function test_another_shops_sales_never_leak_in(): void
    {
        $obcy = Shop::factory()->create();
        $obcyPartner = $obcy->licensors()->create(['name' => 'Cudzy Bieg', 'slug' => 'cudzy-bieg']);

        $order = Order::factory()->create(['shop_id' => $obcy->id, 'status' => OrderStatus::Paid, 'created_at' => Carbon::parse('2026-03-10')]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 5]);
        $item->components()->create([
            'kind' => PriceComponentKind::Licence,
            'label' => 'Cudza licencja',
            'licensor_id' => $obcyPartner->id,
            'licensor_name' => 'Cudzy Bieg',
            'unit_amount_gross' => 40,
            'position' => 0,
        ]);

        [$from, $to] = $this->marzec();

        $this->assertCount(0, $this->rozliczenie()->summary($this->shop, $from, $to));
    }

    /**
     * Partner mógł zmienić nazwę albo wypaść z kartoteki. Rozliczenie za marzec
     * pokazuje, komu należały się pieniądze W MARCU.
     */
    public function test_the_snapshot_name_from_the_sale_is_used(): void
    {
        $this->sprzedaz(unitFee: 25, snapshotName: 'Bieg Gdański 2026');

        [$from, $to] = $this->marzec();

        $this->assertSame('Bieg Gdański 2026', $this->rozliczenie()->summary($this->shop, $from, $to)[0]->name);
    }

    public function test_two_partners_are_settled_separately(): void
    {
        $drugi = $this->shop->licensors()->create(['name' => 'Maraton Wałbrzych', 'slug' => 'maraton-walbrzych']);

        $this->sprzedaz(unitFee: 25);
        $this->sprzedaz(unitFee: 40, licensor: $drugi);

        [$from, $to] = $this->marzec();
        $summary = $this->rozliczenie()->summary($this->shop, $from, $to);

        $this->assertCount(2, $summary);
        // Najwięcej należnych na górze — to pierwsze pytanie właściciela.
        $this->assertSame('Maraton Wałbrzych', $summary[0]->name);
        $this->assertSame(40.0, $summary[0]->amount);
    }

    // --- Ekran ---------------------------------------------------------------

    public function test_the_screen_shows_the_settlement(): void
    {
        $this->sprzedaz(unitFee: 25, quantity: 2);

        $this->actingAs($this->owner)
            ->get(route('seller.settlements.index', ['miesiac' => '2026-03']))
            ->assertOk()
            ->assertSee('Bieg Gdański')
            ->assertSee('50,00');
    }

    public function test_a_broken_month_in_the_url_does_not_break_the_screen(): void
    {
        $this->actingAs($this->owner)
            ->get(route('seller.settlements.index', ['miesiac' => 'kiedys']))
            ->assertOk();
    }

    // --- Arkusz --------------------------------------------------------------

    /**
     * Plik ma się DAĆ OTWORZYĆ, nie tylko pobrać. Rozpakowujemy go i sprawdzamy
     * strukturę — uszkodzony .xlsx wyjdzie dopiero u klienta, przy partnerze
     * czekającym na zestawienie.
     */
    public function test_the_workbook_is_a_real_openable_file(): void
    {
        $this->sprzedaz(unitFee: 25, quantity: 2);

        [$from, $to] = $this->marzec();
        $binary = $this->rozliczenie()->workbook($this->shop, $from, $to);

        $path = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($path, $binary);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Plik nie jest poprawnym archiwum.');

        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $part) {
            $this->assertNotFalse($zip->locateName($part), 'Brakuje części: '.$part);
        }

        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Bieg Gdański', $sheet);
        $this->assertStringContainsString('<v>50</v>', $sheet);

        // Każdy XML musi być poprawny — Excel odmawia otwarcia całego pliku
        // przy jednym złym znaku, nie mówiąc, gdzie.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $this->assertNotFalse(simplexml_load_string((string) $zip->getFromName($name)), 'Zły XML: '.$name);
        }

        $zip->close();
        @unlink($path);
    }

    public function test_download_returns_a_spreadsheet(): void
    {
        $this->sprzedaz(unitFee: 25);

        $response = $this->actingAs($this->owner)
            ->get(route('seller.settlements.download', ['miesiac' => '2026-03']))
            ->assertOk();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
        $this->assertStringContainsString('rozliczenie-2026-03.xlsx', (string) $response->headers->get('Content-Disposition'));
        // Sygnatura archiwum ZIP — plik nie jest pustką z właściwym nagłówkiem.
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    /**
     * Znak sterujący wklejony z Worda unieważnia CAŁY plik — Excel odmawia
     * otwarcia, nie wskazując miejsca.
     */
    public function test_control_characters_do_not_corrupt_the_file(): void
    {
        $this->sprzedaz(unitFee: 25, snapshotName: "Bieg \x07Gdański");

        [$from, $to] = $this->marzec();
        $binary = $this->rozliczenie()->workbook($this->shop, $from, $to);

        $path = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($path, $binary);

        $zip = new ZipArchive;
        $zip->open($path);
        $this->assertNotFalse(simplexml_load_string((string) $zip->getFromName('xl/worksheets/sheet1.xml')));
        $zip->close();
        @unlink($path);
    }

    public function test_sheet_names_are_trimmed_to_what_excel_accepts(): void
    {
        $binary = (new Xlsx)
            ->sheet('Nazwa z [nawiasami] i / ukośnikiem, dłuższa niż trzydzieści jeden znaków', [['a']])
            ->contents();

        $path = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($path, $binary);

        $zip = new ZipArchive;
        $zip->open($path);
        $workbook = (string) $zip->getFromName('xl/workbook.xml');

        preg_match('/name="([^"]*)"/', $workbook, $m);
        $this->assertLessThanOrEqual(31, mb_strlen(html_entity_decode($m[1])));
        $this->assertStringNotContainsString('[', $m[1]);
        $this->assertStringNotContainsString('/', $m[1]);

        $zip->close();
        @unlink($path);
    }
}
