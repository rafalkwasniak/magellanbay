<?php

namespace App\Livewire\Seller;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Component;

/**
 * Karta „Status" na szczególe zamówienia: bieżący status, oś czasu przejść i
 * zmiana statusu. Podpowiada sensowne następne kroki (wg metody dostawy), ale
 * jest wybaczająca — pod „inny status…" jest pełna lista. Autoryzacja twarda:
 * każde przejście sprawdza, że zamówienie należy do sklepu sprzedawcy.
 */
class OrderStatusManager extends Component
{
    public Order $order;

    public string $note = '';

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function changeTo(string $status): void
    {
        abort_unless($this->order->shop_id === auth()->user()?->shop?->id, 403);

        $target = OrderStatus::tryFrom($status);

        if ($target === null) {
            return;
        }

        $this->order->changeStatus($target, $this->note !== '' ? trim($this->note) : null);
        $this->note = '';
    }

    public function render()
    {
        $choices = $this->order->status->transitionChoices($this->order->delivery_method);

        return view('livewire.seller.order-status-manager', [
            'likely' => $choices['likely'],
            'others' => $choices['others'],
            'canCancel' => $this->order->status !== OrderStatus::Cancelled,
            'events' => $this->order->statusEvents,
        ]);
    }
}
