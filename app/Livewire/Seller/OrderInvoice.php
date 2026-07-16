<?php

namespace App\Livewire\Seller;

use App\Models\Order;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Karta stanu faktury VAT w kolumnie bocznej szczegółu zamówienia. Pokazuje
 * WYNIK/POSTĘP: w przygotowaniu / gotowa (link do PDF) / błąd (z ponowieniem).
 * Samo ZLECENIE po raz pierwszy robi kompaktowy przycisk przy danych kupującego
 * ({@see OrderInvoiceTrigger}); tutaj zostaje ponowienie po błędzie.
 *
 * W stanie „idle" (można wystawić, ale jeszcze nie zlecono) karta się NIE
 * pokazuje — całą uwagę bierze wtedy przycisk przy danych. Gdy przycisk zleci
 * FV, wysyła event `invoice-status-changed`, a ta karta odświeża się i od razu
 * pokazuje „w przygotowaniu" (bez przeładowania strony).
 */
class OrderInvoice extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    /**
     * Nasłuch zlecenia z przycisku przy danych kupującego — odświeża zamówienie,
     * dzięki czemu karta natychmiast przełącza się na „w przygotowaniu".
     */
    #[On('invoice-status-changed')]
    public function syncInvoice(): void
    {
        $this->order->refresh();
    }

    /**
     * Ponowienie po nieudanej próbie (stan `failed` znów przepuszcza gard).
     * Pierwsze zlecenie idzie przez przycisk przy danych kupującego.
     */
    public function create(): void
    {
        abort_unless($this->order->shop_id === auth()->user()?->shop?->id, 403);

        $this->order->requestInvoice();
    }

    public function render()
    {
        // Karta pokazuje tylko realny stan: FV gotowa, w toku albo nieudana próba.
        // Idle (można wystawić, nic jeszcze nie zlecono) obsługuje przycisk przy
        // danych kupującego, więc tutaj karta zostaje ukryta.
        $visible = $this->order->hasInvoice()
            || $this->order->isInvoicePending()
            || $this->order->invoiceFailed();

        return view('livewire.seller.order-invoice', [
            'visible' => $visible,
        ]);
    }
}
