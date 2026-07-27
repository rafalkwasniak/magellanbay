<?php

namespace Tests\Feature\Discount;

use App\Enums\DeliveryMethod;
use App\Enums\VatRate;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\FakturowniaService;
use App\Services\OrderMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rabat na dokumentach: mail do klienta, panel sprzedawcy i faktura VAT.
 * Wszędzie musi się zgadzać ta sama arytmetyka — produkty − rabat + dostawa.
 */
class DiscountOnDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function discountedOrder(?Shop $shop = null): Order
    {
        $shop ??= Shop::factory()->create();

        $order = Order::factory()->for($shop)->create([
            'delivery_method' => DeliveryMethod::Courier,
            'delivery_cost' => 20,
            'items_total' => 400,
            'discount_code' => 'LATO100',
            'discount_amount' => 100,
            'total_gross' => 320,
        ]);

        $order->items()->create([
            'name' => 'Rower', 'unit_price_gross' => 300, 'vat_rate' => VatRate::R23->value,
            'quantity' => 1, 'line_total_gross' => 300,
        ]);
        $order->items()->create([
            'name' => 'Bidon', 'unit_price_gross' => 100, 'vat_rate' => VatRate::R5->value,
            'quantity' => 1, 'line_total_gross' => 100,
        ]);

        return $order->fresh('items');
    }

    /**
     * Treść maila jako jeden tekst (bloki linii).
     */
    private function mailText(): string
    {
        $mail = EmailMessage::latest('id')->first();

        return implode(' ', array_merge(...array_map(fn ($b) => (array) $b, $mail->intro_lines)));
    }

    public function test_confirmation_mail_itemises_the_discount_and_delivery(): void
    {
        $order = $this->discountedOrder();

        app(OrderMailer::class)->confirmToCustomer($order);

        $text = $this->mailText();
        $this->assertStringContainsString('Produkty: 400,00 zł', $text);
        $this->assertStringContainsString('Rabat LATO100: −100,00 zł', $text);
        $this->assertStringContainsString('Dostawa: 20,00 zł', $text);
        $this->assertStringContainsString('Razem do zapłaty: **320,00 zł**', $text);
    }

    public function test_seller_notification_shows_the_discount_too(): void
    {
        $order = $this->discountedOrder();

        app(OrderMailer::class)->notifySeller($order);

        $this->assertStringContainsString('Rabat LATO100: −100,00 zł', $this->mailText());
    }

    public function test_order_without_a_discount_or_delivery_keeps_the_short_summary(): void
    {
        $shop = Shop::factory()->create();
        $order = Order::factory()->for($shop)->create([
            'delivery_method' => DeliveryMethod::Pickup,
            'delivery_cost' => 0,
            'items_total' => 100,
            'total_gross' => 100,
        ]);
        $order->items()->create([
            'name' => 'Bidon', 'unit_price_gross' => 100, 'vat_rate' => VatRate::R23->value,
            'quantity' => 1, 'line_total_gross' => 100,
        ]);

        app(OrderMailer::class)->confirmToCustomer($order->fresh('items'));

        $text = $this->mailText();
        // Bez rabatu i dostawy wiersz „Produkty" tylko powtarzałby sumę pozycji.
        $this->assertStringNotContainsString('Produkty:', $text);
        $this->assertStringNotContainsString('Rabat', $text);
        $this->assertStringContainsString('Razem do zapłaty: **100,00 zł**', $text);
    }

    public function test_seller_panel_shows_the_discount_row(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        $order = $this->discountedOrder($shop);

        $this->actingAs($seller)->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('LATO100')
            ->assertSee('−100,00 zł', escape: false);
    }

    public function test_invoice_positions_are_priced_after_the_discount(): void
    {
        $order = $this->discountedOrder();

        $payload = app(FakturowniaService::class)->buildInvoicePayload($order)['invoice'];
        $positions = collect($payload['positions']);

        // Rabat 100 zł z 400 zł rozłożony proporcjonalnie: 75 zł i 25 zł.
        $this->assertSame(225.00, $positions->firstWhere('name', 'Rower')['total_price_gross']);
        $this->assertSame(75.00, $positions->firstWhere('name', 'Bidon')['total_price_gross']);

        // Suma pozycji (z dostawą) = kwota zamówienia.
        $this->assertSame(320.00, round($positions->sum('total_price_gross'), 2));

        // Ceny są po rabacie, więc dokument musi o nim wspominać.
        $this->assertStringContainsString('LATO100', $payload['description']);
    }

    public function test_invoice_without_a_discount_keeps_full_prices_and_no_note(): void
    {
        $shop = Shop::factory()->create();
        $order = Order::factory()->for($shop)->create([
            'delivery_method' => DeliveryMethod::Pickup,
            'delivery_cost' => 0,
            'items_total' => 100,
            'total_gross' => 100,
        ]);
        $order->items()->create([
            'name' => 'Bidon', 'unit_price_gross' => 100, 'vat_rate' => VatRate::R23->value,
            'quantity' => 1, 'line_total_gross' => 100,
        ]);

        $payload = app(FakturowniaService::class)->buildInvoicePayload($order->fresh('items'))['invoice'];

        $this->assertSame(100.00, $payload['positions'][0]['total_price_gross']);
        $this->assertArrayNotHasKey('description', $payload);
    }
}
