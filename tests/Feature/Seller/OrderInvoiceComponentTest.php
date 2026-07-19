<?php

namespace Tests\Feature\Seller;

use App\Enums\IntegrationType;
use App\Enums\InvoiceStatus;
use App\Jobs\GenerateInvoice;
use App\Livewire\Seller\OrderInvoice;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Faktura VAT przy danych kupującego — jeden komponent na cały cykl: przycisk
 * z potwierdzeniem w miejscu, „w przygotowaniu", „Pobierz PDF" i ponowienie po
 * błędzie. Sama robota jest w tle (job), więc tu sprawdzamy zlecanie, stany i
 * autoryzację.
 */
class OrderInvoiceComponentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithFakturownia(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->withInvoicing()->create(['owner_id' => $seller->id]);
        $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://sklep.fakturownia.pl', 'api_token' => 'SECRET'],
        ]);

        return [$seller, $shop->fresh()];
    }

    public function test_shows_button_when_order_can_be_invoiced(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order])
            ->assertSee('Stwórz fakturę VAT')
            ->assertDontSee('Tak, wystaw fakturę');
    }

    public function test_consumer_order_shows_imienna_invoice_hint(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create(['is_company' => false]);

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order])
            ->assertSee('faktura imienna');
    }

    public function test_company_order_has_no_imienna_hint(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create([
            'is_company' => true,
            'company_name' => 'Firma Sp. z o.o.',
            'company_nip' => '5252445429',
        ]);

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order])
            ->assertSee('Stwórz fakturę VAT')
            ->assertDontSee('faktura imienna');
    }

    public function test_ask_create_opens_in_place_confirmation(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order])
            ->call('askCreate')
            ->assertSet('confirming', true)
            ->assertSee('Wystawić fakturę VAT')
            ->assertSee('Tak, wystaw fakturę');
    }

    public function test_dismiss_closes_confirmation_without_dispatch(): void
    {
        Bus::fake();
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order])
            ->call('askCreate')
            ->call('dismiss')
            ->assertSet('confirming', false);

        Bus::assertNotDispatched(GenerateInvoice::class);
    }

    public function test_create_dispatches_job_and_marks_pending(): void
    {
        Bus::fake();
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order])
            ->call('create')
            ->assertSet('confirming', false);

        $this->assertSame(InvoiceStatus::Pending, $order->fresh()->invoice_status);
        Bus::assertDispatched(GenerateInvoice::class);
    }

    public function test_create_is_blocked_when_already_invoiced(): void
    {
        Bus::fake();
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();
        $order->forceFill(['invoice_id' => 123])->save();

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order->fresh()])
            ->call('create');

        Bus::assertNotDispatched(GenerateInvoice::class);
    }

    public function test_shows_download_link_when_invoice_exists(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();
        $order->forceFill(['invoice_id' => 123, 'invoice_number' => '9/2026', 'invoice_token' => 'tok'])->save();

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order->fresh()])
            ->assertSee('Pobierz fakturę VAT')
            ->assertSee('https://sklep.fakturownia.pl/invoice/tok.pdf', false)
            ->assertSee('nr 9/2026')
            ->assertDontSee('Stwórz fakturę VAT');
    }

    public function test_shows_pending_state(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create(['invoice_status' => InvoiceStatus::Pending]);

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order])
            ->assertSee('w przygotowaniu')
            ->assertDontSee('Stwórz fakturę VAT');
    }

    public function test_shows_failed_state_with_retry(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create(['invoice_status' => InvoiceStatus::Failed]);

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order])
            ->assertSee('Nie udało się wystawić faktury')
            ->assertSee('Spróbuj ponownie');
    }

    public function test_retry_dispatches_job(): void
    {
        Bus::fake();
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create(['invoice_status' => InvoiceStatus::Failed]);

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order])
            ->call('create');

        $this->assertSame(InvoiceStatus::Pending, $order->fresh()->invoice_status);
        Bus::assertDispatched(GenerateInvoice::class);
    }

    public function test_hidden_when_shop_does_not_use_fakturownia(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]); // bez integracji
        $order = Order::factory()->for($shop)->create();

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order])
            ->assertDontSee('Faktura VAT')
            ->assertDontSee('Stwórz fakturę VAT');
    }

    public function test_create_is_forbidden_for_foreign_shop(): void
    {
        Bus::fake();
        [, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();

        $intruder = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $intruder->id]);

        Livewire::actingAs($intruder)
            ->test(OrderInvoice::class, ['order' => $order])
            ->call('create')
            ->assertForbidden();

        Bus::assertNotDispatched(GenerateInvoice::class);
    }
}
