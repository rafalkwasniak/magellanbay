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
 * Karta STANU faktury w kolumnie bocznej: pokazuje wynik/postęp (gotowa /
 * w przygotowaniu / błąd + ponowienie), a w stanie idle jest ukryta (zlecenie
 * robi wtedy przycisk przy danych kupującego — {@see OrderInvoiceTriggerTest}).
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
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://sklep.fakturownia.pl', 'api_token' => 'SECRET'],
        ]);

        return [$seller, $shop->fresh()];
    }

    public function test_card_is_hidden_when_idle(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order])
            ->assertDontSee('Faktura VAT');
    }

    public function test_card_shows_download_link_when_invoice_exists(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create();
        $order->forceFill(['invoice_id' => 123, 'invoice_number' => '9/2026', 'invoice_token' => 'tok'])->save();

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order->fresh()])
            ->assertSee('Pobierz fakturę VAT')
            ->assertSee('https://sklep.fakturownia.pl/invoice/tok.pdf', false)
            ->assertSee('nr 9/2026');
    }

    public function test_card_shows_pending_state(): void
    {
        [$seller, $shop] = $this->sellerWithFakturownia();
        $order = Order::factory()->for($shop)->create(['invoice_status' => InvoiceStatus::Pending]);

        Livewire::actingAs($seller)
            ->test(OrderInvoice::class, ['order' => $order])
            ->assertSee('w przygotowaniu');
    }

    public function test_card_shows_failed_state_with_retry(): void
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
}
