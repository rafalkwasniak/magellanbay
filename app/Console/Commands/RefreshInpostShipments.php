<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderMailer;
use App\Services\Shipping\ShipxClient;
use Illuminate\Console\Command;

/**
 * Dopytuje InPost o stan świeżo nadanych przesyłek. Zakup przesyłki jest
 * ASYNCHRONICZNY: po utworzeniu wisi ona w `created`/`offer_selected`, a dopiero
 * po opłaceniu z salda sprzedawcy wpada w `confirmed` — wtedy pojawia się numer
 * do śledzenia i można pobrać etykietę.
 *
 * Dlaczego odpytywanie, a nie webhook: ShipX wymagałby publicznego adresu
 * zwrotnego skonfigurowanego po stronie KAŻDEGO sprzedawcy w jego panelu
 * InPostu (jak przy Paynow). To kolejny krok w onboardingu, który większość
 * pominie — a paczek na sklep jest kilka dziennie, więc odpytywanie kosztuje
 * tyle co nic.
 *
 * Bierzemy tylko przesyłki NIEDOKOŃCZONE i świeże (48 h) — starsze albo już
 * dojechały, albo mają zapisany błąd i czekają na decyzję sprzedawcy.
 */
class RefreshInpostShipments extends Command
{
    protected $signature = 'shipments:refresh {--deliveries : Zamiast świeżych nadań śledź paczki w drodze (rzadki przebieg)}';

    protected $description = 'Odświeża statusy przesyłek InPost (nadania oraz doręczenia)';

    public function handle(ShipxClient $shipx, OrderMailer $mailer): int
    {
        $deliveries = (bool) $this->option('deliveries');

        // Odblokowanie utkniętych zleceń należy do CZĘSTEGO przebiegu — to on
        // pilnuje świeżych nadań.
        if (! $deliveries) {
            $this->releaseStuckQueued();
            $this->refreshDispatchOrders($shipx);
        }

        $orders = $deliveries ? $this->parcelsInTransit() : $this->awaitingPurchase();

        foreach ($orders as $order) {
            if ($order->shop === null || ! $order->shop->shipxConfigured()) {
                continue;
            }

            $shipment = $shipx->shipment($order->shop, (int) $order->shipment_id);

            // null = „nie wiem" (błąd sieci albo sporadyczne 404 na istniejącej
            // przesyłce). NIE kasujemy śladu i NIE zapisujemy błędu — spróbujemy
            // ponownie w kolejnym przebiegu.
            if ($shipment === null) {
                continue;
            }

            $status = $shipment['status'] ?? $order->shipment_status;

            // Przejścia wyłapujemy PRZED zapisem — po nim nie da się już
            // odróżnić „właśnie się zmieniło" od „było tak od godziny".
            $becameReady = ! $order->isShipmentReady() && ShipxClient::isReady($shipment);
            $justDelivered = $status === 'delivered' && $order->delivered_at === null;

            $changes = [
                'shipment_status' => $status,
                'shipment_tracking_number' => $shipment['tracking_number'] ?? $order->shipment_tracking_number,
                'shipment_error' => ShipxClient::failureReason($shipment),
            ];

            // Data odbioru zapisywana RAZ, przy pierwszym zobaczeniu doręczenia.
            // Dla paczkomatu „delivered" = klient wyjął paczkę ze skrytki, więc
            // to dokładnie moment, od którego biegnie termin na odstąpienie.
            // Świadomie NIE ruszamy statusu zamówienia — o tym, że jest
            // „Zrealizowane", decyduje sprzedawca (decyzja Rafała 07.08).
            if ($justDelivered) {
                $changes['delivered_at'] = now();
            }

            $order->forceFill($changes)->save();

            // Maile PO zapisie, żeby niosły aktualne dane (numer przesyłki,
            // datę odbioru, a przez nią termin na odstąpienie).
            if ($becameReady) {
                $mailer->shipmentDispatched($order->fresh());
            }

            if ($justDelivered) {
                $mailer->shipmentDelivered($order->fresh());
            }
        }

        $this->info('Sprawdzono przesyłek: '.$orders->count());

        return self::SUCCESS;
    }

    /**
     * Dopytuje o zlecenia odbioru kuriera, które jeszcze nie zostały
     * rozstrzygnięte. To NIE jest ozdobnik: `POST /dispatch_orders` zwraca 201
     * nawet wtedy, gdy InPost chwilę później odrzuci zlecenie (np. bo któraś
     * paczka była zadeklarowana do wrzucenia w paczkomacie). Bez tego przebiegu
     * sprzedawca czekałby w domu na kuriera, który nigdy nie przyjedzie.
     *
     * Okno 7 dni: zlecenie rozstrzyga się w sekundy, a starsze i tak nic nie
     * wnosi — jeśli po tygodniu nadal wisi na `new`, to i tak przepadło.
     */
    private function refreshDispatchOrders(ShipxClient $shipx): void
    {
        $pending = \App\Models\DispatchOrder::query()
            ->whereNotNull('shipx_id')
            ->whereNotIn('status', ['sent', 'confirmed', 'accepted', 'rejected', 'canceled'])
            ->where('created_at', '>=', now()->subDays(7))
            ->with('shop')
            ->get();

        foreach ($pending as $dispatchOrder) {
            if ($dispatchOrder->shop === null || ! $dispatchOrder->shop->shipxConfigured()) {
                continue;
            }

            $data = $shipx->dispatchOrder($dispatchOrder->shop, (int) $dispatchOrder->shipx_id);

            // null = „nie wiem" — spróbujemy w kolejnym przebiegu.
            if ($data === null) {
                continue;
            }

            $dispatchOrder->update([
                'status' => $data['status'] ?? $dispatchOrder->status,
                'error' => ShipxClient::dispatchFailureReason($data),
            ]);

            // Odrzucone zlecenie odpinamy od paczek, żeby wróciły na listę
            // oczekujących i dało się zamówić kuriera jeszcze raz. Sam wiersz
            // ZOSTAJE — niesie powód odrzucenia, który sprzedawca musi zobaczyć.
            if ($dispatchOrder->fresh()->isRejected()) {
                $dispatchOrder->orders()->update(['dispatch_order_id' => null]);
            }
        }

        if ($pending->isNotEmpty()) {
            $this->info('Sprawdzono zleceń odbioru: '.$pending->count());
        }
    }

    /**
     * Świeże nadania czekające na opłacenie przez InPost — sprzedawca stoi nad
     * panelem i czeka na etykietę, więc przebieg jest częsty (co minutę), a
     * okno wąskie (48 h).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    private function awaitingPurchase()
    {
        return Order::query()
            ->whereNotNull('shipment_id')
            ->whereNull('shipment_error')
            ->whereNotIn('shipment_status', ['confirmed', 'delivered'])
            ->where('shipped_at', '>=', now()->subHours(48))
            ->with('shop')
            ->get();
    }

    /**
     * Paczki w drodze — pilnujemy tylko momentu odbioru. Przebieg RZADKI (raz na
     * godzinę): nikt nie czeka nad ekranem, a paczka leży w paczkomacie dniami,
     * więc częstsze pytanie to same puste wywołania API.
     *
     * Okno 21 dni: klient ma tydzień na odbiór, a po trzech tygodniach paczka
     * albo dojechała, albo wróciła do nadawcy i nie ma czego śledzić.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    private function parcelsInTransit()
    {
        return Order::query()
            ->whereNotNull('shipment_id')
            ->whereNull('delivered_at')
            ->whereNull('shipment_error')
            ->where('shipped_at', '>=', now()->subDays(21))
            ->with('shop')
            ->get();
    }

    /**
     * Odblokowuje zamówienia, które utknęły w naszym `queued` — czyli zadanie
     * zostało zlecone, ale nigdy się nie wykonało (padła kolejka, cron nie
     * chodził). Bez tego sprzedawca oglądałby „Nadajemy przesyłkę…” w
     * nieskończoność, bez możliwości ponowienia.
     *
     * 15 minut to z zapasem więcej niż minuta cyklu kolejki, więc nie złapiemy
     * zadania, które po prostu czeka na swoją kolej. Liczymy od
     * `shipment_queued_at`, a NIE od `updated_at` — ten drugi podbija każda inna
     * zmiana zamówienia (np. edycja notatki) i przesuwałby okno wykrywania.
     */
    private function releaseStuckQueued(): void
    {
        Order::query()
            ->whereNull('shipment_id')
            ->where('shipment_status', Order::SHIPMENT_QUEUED)
            ->where('shipment_queued_at', '<', now()->subMinutes(15))
            ->update([
                'shipment_status' => null,
                'shipment_error' => 'Nadawanie nie ruszyło. Spróbuj ponownie.',
            ]);
    }
}
