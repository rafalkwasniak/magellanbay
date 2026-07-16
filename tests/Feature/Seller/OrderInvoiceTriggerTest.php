<?php

namespace Tests\Feature\Seller;

use App\Enums\IntegrationType;
use App\Enums\InvoiceStatus;
use App\Jobs\GenerateInvoice;
use App\Livewire\Seller\OrderInvoiceTrigger;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kompaktowy przycisk „Stwórz fakturę VAT" przy danych kupującego: widoczny
 * tylko w stanie „można wystawić", zleca FV i wysyła event, którym karta stanu
 * w sidebarze od razu pokazuje „w przygotowaniu".
 */
class OrderInvoiceTriggerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithFakturownia(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://sklep.fakturownia.pl', 'api_token' => 'SECRET'],
        ]);

        return [$seller, $shop->fresh()];
    }

    public function test_button_shows_when_order_can_be_invoiced(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();

        Livewire::actingAs($seller)
            ->test(OrderInvoiceTrigger::class, ['order' => $order])
            ->assertSee('Stwórz fakturę VAT');
    }

    public function test_create_dispatches_job_marks_pending_and_emits_event(): void
    {
        Bus::fake();
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();

        Livewire::actingAs($seller)
            ->test(OrderInvoiceTrigger::class, ['order' => $order])
            ->call('create')
            ->assertDispatched('invoice-status-changed');

        $this->assertSame(InvoiceStatus::Pending, $order->fresh()->invoice_status);
        Bus::assertDispatched(GenerateInvoice::class);
    }

    public function test_button_hidden_when_already_invoiced(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();
        $order->forceFill(['invoice_id' => 123])->save();

        Livewire::actingAs($seller)
            ->test(OrderInvoiceTrigger::class, ['order' => $order->fresh()])
            ->assertDontSee('Stwórz fakturę VAT');
    }

    public function test_button_hidden_when_failed_retry_lives_in_status_card(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create(['invoice_status' => InvoiceStatus::Failed]);

        Livewire::actingAs($seller)
            ->test(OrderInvoiceTrigger::class, ['order' => $order])
            ->assertDontSee('Stwórz fakturę VAT');
    }

    public function test_button_hidden_when_shop_does_not_use_fakturownia(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]); // bez integracji
        $order = Order::factory()->for($shop)->create();

        Livewire::actingAs($seller)
            ->test(OrderInvoiceTrigger::class, ['order' => $order])
            ->assertDontSee('Stwórz fakturę VAT');
    }

    public function test_create_blocked_when_already_invoiced(): void
    {
        Bus::fake();
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();
        $order->forceFill(['invoice_id' => 123])->save();

        Livewire::actingAs($seller)
            ->test(OrderInvoiceTrigger::class, ['order' => $order->fresh()])
            ->call('create');

        Bus::assertNotDispatched(GenerateInvoice::class);
    }

    public function test_create_forbidden_for_foreign_shop(): void
    {
        Bus::fake();
        [, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();

        $intruder = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $intruder->id]);

        Livewire::actingAs($intruder)
            ->test(OrderInvoiceTrigger::class, ['order' => $order])
            ->call('create')
            ->assertForbidden();

        Bus::assertNotDispatched(GenerateInvoice::class);
    }
}
