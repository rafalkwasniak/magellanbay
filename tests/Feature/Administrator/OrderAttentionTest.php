<?php

namespace Tests\Feature\Administrator;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Support\OrderAttention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsola admina — zamówienia, które się zacięły.
 *
 * Sens listy: sprzedawca widzi tylko własny sklep i tylko wtedy, gdy zagląda.
 * Nieudane nadanie w InPoście potrafi przeleżeć tygodnie, bo nic o sobie nie
 * krzyczy. Fałszywy alarm jest tu groźniejszy niż brak listy — po kilku dniach
 * świecenia bez powodu nikt jej nie czyta.
 */
class OrderAttentionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function groupKeys(): array
    {
        return array_map(fn (array $group): string => $group['key'], OrderAttention::groups());
    }

    public function test_healthy_orders_stay_silent(): void
    {
        $shop = Shop::factory()->create();
        Order::factory()->for($shop)->create(['status' => OrderStatus::Completed]);
        Order::factory()->for($shop)->create(['status' => OrderStatus::New]);

        $this->assertSame([], OrderAttention::groups());
    }

    public function test_failed_shipment_is_the_most_urgent_signal(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create(['name' => 'Kwiaciarnia Zosia']);
        Order::factory()->for($shop)->create([
            'number' => 'ZAM-BLAD',
            'shipment_error' => 'Nie udało się nadać przesyłki w InPoście.',
        ]);

        $this->assertSame(['shipment_failed'], $this->groupKeys());

        $this->actingAs($admin)
            ->get(route('administrator.orders.index'))
            ->assertOk()
            ->assertSee('Nadanie nie powiodło się')
            ->assertSee('ZAM-BLAD')
            ->assertSee('Kwiaciarnia Zosia');
    }

    public function test_cancelled_order_never_reports_a_shipment_problem(): void
    {
        // Anulowanego zamówienia nikt już nie nada — wołanie o nie byłoby
        // fałszywym alarmem, a te zabijają wiarygodność całej listy.
        $shop = Shop::factory()->create();
        Order::factory()->for($shop)->create([
            'shipment_error' => 'Nie udało się nadać przesyłki.',
            'status' => OrderStatus::Cancelled,
        ]);

        $this->assertSame([], OrderAttention::groups());
    }

    public function test_failed_invoice_is_reported(): void
    {
        $shop = Shop::factory()->create();
        Order::factory()->for($shop)->create(['invoice_status' => InvoiceStatus::Failed]);

        $this->assertSame(['invoice_failed'], $this->groupKeys());
    }

    public function test_pending_invoice_is_not_a_problem_yet(): void
    {
        $shop = Shop::factory()->create();
        Order::factory()->for($shop)->create(['invoice_status' => InvoiceStatus::Pending]);

        $this->assertSame([], OrderAttention::groups());
    }

    public function test_paid_order_is_flagged_only_after_the_configured_delay(): void
    {
        $shop = Shop::factory()->create();
        $fresh = Order::factory()->for($shop)->create(['status' => OrderStatus::Paid]);

        $this->assertSame([], OrderAttention::groups());

        $fresh->forceFill(['created_at' => now()->subDays(5)])->save();

        $this->assertSame(['stalled'], $this->groupKeys());
    }

    public function test_order_waiting_for_the_customer_is_not_blamed_on_the_seller(): void
    {
        // „Gotowe do odbioru" znaczy, że sprzedawca zrobił swoje, a paczka czeka
        // na klienta. Wołanie sprzedawcy byłoby tu po prostu niesłuszne.
        $shop = Shop::factory()->create();
        Order::factory()->for($shop)->create([
            'status' => OrderStatus::ReadyForPickup,
            'created_at' => now()->subDays(20),
        ]);

        $this->assertSame([], OrderAttention::groups());
    }

    public function test_abandoned_payment_is_flagged_after_a_day(): void
    {
        $shop = Shop::factory()->create();
        $order = Order::factory()->for($shop)->create(['status' => OrderStatus::AwaitingPayment]);

        $this->assertSame([], OrderAttention::groups());

        $order->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->assertSame(['unpaid'], $this->groupKeys());
    }

    public function test_groups_are_ordered_by_urgency(): void
    {
        $shop = Shop::factory()->create();
        Order::factory()->for($shop)->create(['status' => OrderStatus::AwaitingPayment, 'created_at' => now()->subDays(2)]);
        Order::factory()->for($shop)->create(['status' => OrderStatus::Paid, 'created_at' => now()->subDays(5)]);
        Order::factory()->for($shop)->create(['invoice_status' => InvoiceStatus::Failed]);
        Order::factory()->for($shop)->create(['shipment_error' => 'Błąd nadania.']);

        $this->assertSame(
            ['shipment_failed', 'invoice_failed', 'stalled', 'unpaid'],
            $this->groupKeys()
        );
    }

    public function test_attention_ignores_the_screen_filters(): void
    {
        // Lista odpowiada na pytanie „co się pali", a nie „co się pali w tym, co
        // przefiltrowałem". Gdyby reagowała na filtry, dałoby się ją przypadkiem
        // wyciszyć, szukając czegoś innego.
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create();
        Order::factory()->for($shop)->create([
            'number' => 'ZAM-BLAD',
            'shipment_error' => 'Błąd nadania.',
        ]);

        $this->actingAs($admin)
            ->get(route('administrator.orders.index', ['szukaj' => 'nic-takiego-nie-ma']))
            ->assertOk()
            ->assertSee('Żadne zamówienie nie pasuje do tych filtrów')
            ->assertSee('Nadanie nie powiodło się')
            ->assertSee('ZAM-BLAD');
    }
}
