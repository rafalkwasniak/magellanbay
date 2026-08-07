<?php

namespace App\Livewire\Seller;

use App\Enums\ParcelSize;
use App\Models\Order;
use Livewire\Component;

/**
 * Nadanie przesyłki InPost przy danych dostawy na szczególe zamówienia.
 * Bliźniak {@see OrderInvoice}: cały cykl w jednym miejscu — wybór gabarytu →
 * „Nadaj przesyłkę" → „Nadawanie…" → numer przesyłki i „Pobierz etykietę",
 * a przy błędzie czytelny powód i ponowienie.
 *
 * Sama robota dzieje się w tle ({@see \App\Jobs\CreateInpostShipment}), a stan
 * „Nadawanie…" odświeża `wire:poll`, aż komenda `shipments:refresh` zobaczy, że
 * InPost opłacił przesyłkę.
 */
class OrderShipment extends Component
{
    public Order $order;

    /** Wybrany gabaryt (wartość szablonu ShipX: small/medium/large). */
    public string $size = 'small';

    /** Czy pokazujemy potwierdzenie w miejscu (zamiast wyboru gabarytu). */
    public bool $confirming = false;

    public function mount(Order $order): void
    {
        $this->order = $order;
        // Podpowiadamy gabaryt z poprzedniego nadania tego zamówienia, jeśli był.
        $this->size = $order->shipment_size?->value ?? 'small';
    }

    /**
     * Otwiera potwierdzenie. Nadanie kosztuje sprzedawcę realne pieniądze i jest
     * nieodwracalne z poziomu panelu, więc — jak przy zmianie statusu — pytamy,
     * pokazując WYBRANY GABARYT i paczkomat docelowy.
     */
    public function ask(): void
    {
        $this->authorizeOwnership();

        $this->confirming = true;
    }

    public function dismiss(): void
    {
        $this->confirming = false;
    }

    public function create(): void
    {
        $this->authorizeOwnership();

        $this->confirming = false;

        $size = ParcelSize::tryFrom($this->size) ?? ParcelSize::A;

        $this->order->requestShipment($size);
    }

    /**
     * Gabaryt wybrany w tej chwili — do treści potwierdzenia.
     */
    public function selectedSize(): ParcelSize
    {
        return ParcelSize::tryFrom($this->size) ?? ParcelSize::A;
    }

    private function authorizeOwnership(): void
    {
        abort_unless($this->order->shop_id === auth()->user()?->shop?->id, 403);
    }

    public function render()
    {
        // Widoczne dla dostawy do paczkomatu, gdy sklep nadaje przez InPost —
        // albo gdy przesyłka już istnieje (żeby etykieta została dostępna nawet
        // po późniejszym wyłączeniu integracji).
        $visible = $this->order->hasShipment()
            || ($this->order->delivery_method?->requiresParcelLocker() === true
                && $this->order->shop?->shipxEnabled() === true);

        return view('livewire.seller.order-shipment', [
            'visible' => $visible,
            'sizes' => ParcelSize::cases(),
            // Konto testowe: nadania są próbne, więc śledzenie InPostu ich nie zna.
            'sandbox' => $this->order->shop?->shipxEnvironment() === 'sandbox',
        ]);
    }
}
