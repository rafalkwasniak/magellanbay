<?php

namespace App\Jobs;

use App\Enums\SendingMethod;
use App\Models\Order;
use App\Services\Shipping\ParcelSpec;
use App\Services\Shipping\ShipxClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Nadaje przesyłkę paczkomatową w InPoście (kolejka `database`, drenowana
 * krótkim `queue:work --stop-when-empty` z crona — bez demona, LVE-safe).
 *
 * `tries = 1`: ŻADNYCH automatycznych ponowień — dokładnie jak przy fakturach,
 * i z tego samego powodu. Nadanie zdejmuje opłatę z salda sprzedawcy, więc
 * ślepe ponowienie mogłoby nadać (i opłacić) drugą paczkę, gdyby żądanie doszło,
 * a odpowiedź nie wróciła. Porażka zostawia `shipment_error` — sprzedawca
 * ponawia świadomie przyciskiem.
 *
 * Idempotencja: guard `hasShipment()` na wejściu.
 *
 * Zakupu NIE wołamy: przy zasilonym koncie InPost sam przechodzi z
 * `offer_selected` na `confirmed` (zweryfikowane na sandboxie 2026-08-07).
 * Dopytaniem o status zajmuje się `RefreshInpostShipments`.
 */
class CreateInpostShipment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly Order $order,
        public readonly ParcelSpec $parcel,
        public readonly SendingMethod $sending,
    ) {}

    public function handle(ShipxClient $shipx): void
    {
        $order = $this->order->fresh();

        if ($order === null || $order->hasShipment()) {
            return;
        }

        // Sposób nadania przychodzi z panelu (domyślnie z Ustawień sklepu) i tą
        // samą wartością lecimy do InPostu ORAZ do migawki zamówienia. Rozjazd
        // między nimi znaczyłby, że panel pokazuje co innego, niż sprzedawca
        // zadeklarował — a deklaracji nie da się później zmienić.
        $shipment = $shipx->createShipment($order, $this->parcel, $this->sending);

        if ($shipment === null || blank($shipment['id'] ?? null)) {
            $order->forceFill([
                'shipment_error' => 'Nie udało się nadać przesyłki w InPoście. Sprawdź dane integracji i spróbuj ponownie.',
            ])->save();

            return;
        }

        // Zapis śladu NAJPIERW — od tej chwili `hasShipment()` = true, więc nawet
        // ponowny przebieg nie nada (i nie opłaci) drugiej paczki.
        $order->forceFill($this->parcel->toOrderColumns() + [
            'shipment_id' => $shipment['id'],
            'shipment_status' => $shipment['status'] ?? null,
            'shipment_tracking_number' => $shipment['tracking_number'] ?? null,
            'shipment_sending_method' => $this->sending,
            'shipment_error' => ShipxClient::failureReason($shipment),
            'shipped_at' => now(),
        ])->save();
    }

    /**
     * Job padł twardo (wyjątek poza obsłużonymi) — zostawiamy czytelny ślad,
     * żeby sprzedawca nie patrzył na wieczne „Nadawanie…”.
     */
    public function failed(\Throwable $exception): void
    {
        $this->order->fresh()?->forceFill([
            'shipment_error' => 'Nadawanie przesyłki nie powiodło się. Spróbuj ponownie.',
        ])->save();
    }
}
