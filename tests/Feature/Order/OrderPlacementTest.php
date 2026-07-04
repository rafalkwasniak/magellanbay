<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Exceptions\CartNeedsReviewException;
use App\Models\EmailMessage;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Składanie zamówienia (OrderService): migawka pozycji + sumy VAT, atomowe
 * zdjęcie stanu, czyszczenie koszyka oraz finalna weryfikacja dostępności
 * (spec „Finalna weryfikacja zamówienia").
 */
class OrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    private function shop(): Shop
    {
        return Shop::factory()->create([
            'street' => 'Kwiatowa', 'building_number' => '5', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'province' => 'mazowieckie',
            'pickup_enabled' => true, 'pay_on_pickup_enabled' => true,
        ]);
    }

    private function buyerData(): array
    {
        return [
            'buyer_name' => 'Jan',
            'buyer_surname' => 'Kowalski',
            'buyer_email' => 'jan@example.com',
            'buyer_phone' => '123456789',
            'is_company' => false,
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
            'note' => null,
        ];
    }

    public function test_place_creates_order_with_snapshot_and_decrements_stock(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true,
            'track_stock' => true, 'stock' => 5, 'price_gross' => 123.00, 'vat_rate' => '23',
        ]);

        app(CartService::class)->add($product, 2);

        $order = app(OrderService::class)->place($shop, $this->buyerData());

        $this->assertSame(1, $order->number);
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertCount(1, $order->items);

        $item = $order->items->first();
        $this->assertSame('123.00', $item->unit_price_gross);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('246.00', $item->line_total_gross);

        // Sumy: 246 brutto → netto 200, VAT 46.
        $this->assertSame('246.00', $order->total_gross);
        $this->assertSame('200.00', $order->total_net);
        $this->assertSame('46.00', $order->total_vat);

        // Stan zdjęty i koszyk pusty.
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(0, app(CartService::class)->count($shop->id));
    }

    public function test_place_aborts_and_adjusts_when_stock_dropped(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true,
            'track_stock' => true, 'stock' => 5, 'price_gross' => 50.00,
        ]);
        app(CartService::class)->add($product, 5);

        // Stan spadł zanim klient kliknął „Zamawiam".
        $product->update(['stock' => 2]);

        try {
            app(OrderService::class)->place($shop, $this->buyerData());
            $this->fail('Spodziewano się CartNeedsReviewException.');
        } catch (CartNeedsReviewException $e) {
            $this->assertNotEmpty($e->messages);
        }

        // Zamówienie NIE powstało, koszyk uzgodniony do dostępnych 2, brak maili.
        $this->assertSame(0, $shop->orders()->count());
        $this->assertSame(2, app(CartService::class)->count($shop->id));
        $this->assertSame(2, $product->fresh()->stock);   // stan nietknięty przez zamówienie
        $this->assertSame(0, EmailMessage::count());
    }

    public function test_place_enqueues_customer_and_seller_emails(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 30.00,
        ]);
        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, $this->buyerData());

        $this->assertSame(2, EmailMessage::count());
        // Potwierdzenie do kupującego + powiadomienie do właściciela sklepu, priorytet Mid.
        $this->assertDatabaseHas('email_messages', [
            'to_email' => 'jan@example.com',
            'subject' => 'Potwierdzenie zamówienia #'.$order->number.' — '.$shop->name,
            'priority' => \App\Enums\MailPriority::Mid->value,
        ]);
        $this->assertDatabaseHas('email_messages', ['to_email' => $shop->owner->email]);
    }

    public function test_order_emails_carry_shop_sender_identity(): void
    {
        $shop = $this->shop();
        $shop->update(['name' => 'I like my bike', 'contact_email' => 'kontakt@bike.test']);
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 30.00,
        ]);
        app(CartService::class)->add($product, 1);

        app(OrderService::class)->place($shop, $this->buyerData());

        // Oba maile (do klienta i do sprzedawcy) niosą tożsamość sklepu: nadawca =
        // nazwa sklepu, Reply-To = e-mail kontaktowy sklepu.
        $this->assertSame(2, EmailMessage::count());
        foreach (EmailMessage::all() as $email) {
            $this->assertSame('I like my bike', $email->from_name);
            $this->assertSame('kontakt@bike.test', $email->reply_to);
        }
    }

    public function test_customer_email_bolds_full_transfer_title_and_amount(): void
    {
        $shop = $this->shop();
        $shop->update([
            'bank_transfer_enabled' => true,
            'bank_account_number' => '43114020040000350275155558',
            'bank_name' => 'mBank',
        ]);
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 20.00,
        ]);
        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, array_merge($this->buyerData(), [
            'payment_method' => 'bank_transfer',
        ]));

        $customerEmail = EmailMessage::where('to_email', 'jan@example.com')->firstOrFail();
        $lines = collect($customerEmail->intro_lines)->flatten()->all();

        // Cały tytuł przelewu (nie samo #N) i kwota są pogrubione — to wartości do skopiowania.
        $this->assertContains('Tytuł przelewu: **Zamówienie #'.$order->number.'**', $lines);
        $this->assertContains('Kwota: **'.\App\Support\Money::pln($order->total_gross).'**', $lines);
        // Fraza „zamówienie #N" w zdaniu jest pogrubiona w całości, nie samo #N.
        $this->assertContains('Otrzymaliśmy Twoje **zamówienie #'.$order->number.'** i już się nim zajmujemy.', $lines);
    }

    public function test_seller_email_formats_phone_and_includes_company_address(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 10.00,
        ]);
        app(CartService::class)->add($product, 1);

        $data = array_merge($this->buyerData(), [
            'buyer_phone' => '668196229',
            'is_company' => true,
            'company_name' => 'ACME sp. z o.o.',
            'company_nip' => '5252248481',
            'company_street' => 'Polna',
            'company_building_number' => '3',
            'company_postal_code' => '00-002',
            'company_city' => 'Kraków',
        ]);

        app(OrderService::class)->place($shop, $data);

        $sellerEmail = EmailMessage::where('to_email', $shop->owner->email)->firstOrFail();

        // intro_lines to teraz bloki (tablice linii) — spłaszczamy przed szukaniem.
        $lines = collect($sellerEmail->intro_lines)->flatten()->all();
        $this->assertContains('Telefon: +48 668 196 229', $lines);
        $this->assertContains('Adres: Polna 3, 00-002 Kraków', $lines);
        // Nagłówki sekcji są pogrubiane zapisem **...**.
        $this->assertContains('**Dane kupującego:**', $lines);
        $this->assertContains('**Dane do faktury:**', $lines);
        // Fraza „zamówienie #N" w zdaniu jest pogrubiona w całości, nie samo #N.
        $order = $shop->orders()->firstOrFail();
        $this->assertContains('W Twoim sklepie **'.$shop->name.'** pojawiło się nowe **zamówienie #'.$order->number.'**.', $lines);
    }

    public function test_place_stores_company_data_when_buying_as_company(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 10.00,
        ]);
        app(CartService::class)->add($product, 1);

        $data = array_merge($this->buyerData(), [
            'is_company' => true,
            'company_name' => 'ACME sp. z o.o.',
            'company_nip' => '5252248481',
            'company_street' => 'Kwiatowa',
            'company_building_number' => '5',
            'company_postal_code' => '00-001',
            'company_city' => 'Warszawa',
        ]);

        $order = app(OrderService::class)->place($shop, $data);

        $this->assertTrue($order->is_company);
        $this->assertSame('ACME sp. z o.o.', $order->company_name);
        $this->assertSame('5252248481', $order->company_nip);
        $this->assertSame('Kwiatowa', $order->company_street);
        $this->assertSame('Warszawa', $order->company_city);
    }

    public function test_company_fields_are_null_when_not_company(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 10.00,
        ]);
        app(CartService::class)->add($product, 1);

        // Dane firmy podane, ale is_company = false → nie zapisujemy ich.
        $data = array_merge($this->buyerData(), [
            'is_company' => false,
            'company_name' => 'Nie zapisać',
            'company_street' => 'Nie zapisać',
        ]);

        $order = app(OrderService::class)->place($shop, $data);

        $this->assertNull($order->company_name);
        $this->assertNull($order->company_street);
    }
}
