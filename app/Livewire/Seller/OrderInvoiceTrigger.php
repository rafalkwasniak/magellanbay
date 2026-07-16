<?php

namespace App\Livewire\Seller;

use App\Models\Order;
use Livewire\Component;

/**
 * Kompaktowy przycisk „Stwórz fakturę VAT" przy danych kupującego — tam, gdzie
 * sprzedawca patrzy na dane do faktury, więc akcja jest pod ręką. Sam PROGRES i
 * wynik (w przygotowaniu / pobierz / błąd) pokazuje karta {@see OrderInvoice} w
 * kolumnie bocznej; ten komponent tylko zleca i po zleceniu chowa przycisk.
 *
 * Widoczny wyłącznie w stanie „można wystawić i jeszcze nie próbowaliśmy" —
 * ponowienie po błędzie żyje w karcie stanu, żeby nie dublować wezwania.
 */
class OrderInvoiceTrigger extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function create(): void
    {
        abort_unless($this->order->shop_id === auth()->user()?->shop?->id, 403);

        if ($this->order->requestInvoice()) {
            // Karta stanu w sidebarze nasłuchuje i od razu pokaże „w przygotowaniu".
            $this->dispatch('invoice-status-changed');
        }
    }

    public function render()
    {
        $showButton = $this->order->canBeInvoiced() && ! $this->order->invoiceFailed();

        return view('livewire.seller.order-invoice-trigger', [
            'showButton' => $showButton,
        ]);
    }
}
