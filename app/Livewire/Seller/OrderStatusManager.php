<?php

namespace App\Livewire\Seller;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderStatusChanger;
use Livewire\Component;

/**
 * Karta „Status" na szczególe zamówienia: bieżący status, oś czasu przejść i
 * zmiana statusu po ścieżce zamówienia (`OrderFlow`). Sprzedawca widzi tylko
 * statusy, które dla TEGO zamówienia mają sens — reszta nie istnieje, zamiast
 * być chowana. Zalecany jest kolejny krok; każdy inny wymaga potwierdzenia.
 *
 * Ten komponent odpowiada wyłącznie za autoryzację (własność sklepu) i widok —
 * reguły przejść i mail do kupującego trzyma `OrderStatusChanger`.
 */
class OrderStatusManager extends Component
{
    public Order $order;

    public string $note = '';

    /** Czy pokazujemy panel potwierdzenia anulowania (zamiast listy statusów). */
    public bool $confirmingCancel = false;

    /** Powód anulowania — trafia do maila i na oś czasu. Opcjonalny. */
    public string $cancelReason = '';

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function changeTo(string $status, OrderStatusChanger $changer): void
    {
        $this->authorizeOwnership();

        $target = OrderStatus::tryFrom($status);

        // Anulowanie nigdy nie idzie tędy — ma własną, potwierdzaną ścieżkę.
        if ($target === null || $target === OrderStatus::Cancelled) {
            return;
        }

        // Reguły ścieżki i mail do kupującego — w serwisie, żeby nie dało się
        // ich ominąć innym punktem wejścia.
        $changer->change($this->order, $target, $this->note !== '' ? trim($this->note) : null);
        $this->note = '';

        $this->announceChange();
    }

    public function askCancel(): void
    {
        $this->authorizeOwnership();

        $this->confirmingCancel = true;
    }

    public function dismissCancel(): void
    {
        $this->confirmingCancel = false;
        $this->cancelReason = '';
    }

    /**
     * Anulowanie — świadomie osobna akcja, nie kolejny przycisk statusu. Jako
     * jedyna zmiana coś nieodwracalnie robi poza statusem (oddaje towar na stan),
     * więc wymaga potwierdzenia i ma własne pole powodu.
     */
    public function cancel(OrderStatusChanger $changer): void
    {
        $this->authorizeOwnership();

        $changer->change(
            $this->order,
            OrderStatus::Cancelled,
            $this->cancelReason !== '' ? trim($this->cancelReason) : null,
        );

        $this->dismissCancel();
        $this->announceChange();
    }

    private function authorizeOwnership(): void
    {
        abort_unless($this->order->shop_id === auth()->user()?->shop?->id, 403);
    }

    /**
     * Karta „Pozycje" to osobny komponent — bez tego sygnału nie dowiedziałaby
     * się, że zamówienie właśnie zostało anulowane, i nadal oferowałaby edycję.
     */
    private function announceChange(): void
    {
        $this->order->refresh();
        $this->dispatch('order-status-changed');
    }

    public function render()
    {
        $flow = $this->order->flow();
        $events = $this->order->statusEvents;

        return view('livewire.seller.order-status-manager', [
            'statuses' => $flow->statuses(),
            'suggested' => $flow->next($this->order->status),
            'canChange' => ! $this->order->status->isTerminal(),
            'initialStatus' => $this->initialStatus(),
            'events' => $events,
        ]);
    }

    /**
     * Status, w którym zamówienie się urodziło. Nie ma go wśród zdarzeń, bo nikt
     * go nie „zmienił" — dlatego oś czasu musi go dołożyć sama, inaczej zjadałaby
     * pierwszy status zamówienia.
     *
     * Bierzemy go z `from_status` pierwszego zdarzenia, a nie z `flow()->initial()`:
     * oś czasu to zapis historii, więc ma pokazywać, co FAKTYCZNIE było — nawet
     * gdy zamówienie powstało przed dzisiejszymi ścieżkami (stare zamówienia z
     * przelewem startowały w „Nowe"). Bez zdarzeń nic się jeszcze nie zmieniło,
     * więc statusem początkowym jest po prostu bieżący.
     */
    private function initialStatus(): OrderStatus
    {
        return $this->order->statusEvents->first()?->from_status ?? $this->order->status;
    }
}
