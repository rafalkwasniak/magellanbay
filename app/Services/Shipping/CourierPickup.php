<?php

namespace App\Services\Shipping;

use App\Enums\SendingMethod;
use App\Models\DispatchOrder;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Zamawianie odbioru paczek przez kuriera InPostu.
 *
 * Sedno: JEDNO zlecenie obejmuje WIELE przesyłek, bo dopłata jest za przyjazd
 * kuriera, a nie za paczkę. Sprzedawca z ruchem nadaje przez cały dzień, a
 * wieczorem zamawia jeden przyjazd na wszystko — i to jest jedyny tryb pracy,
 * który przy trzydziestu paczkach dziennie ma sens.
 *
 * Do zlecenia trafiają WYŁĄCZNIE przesyłki zadeklarowane jako `dispatch_order`.
 * Deklaracja zapada w chwili nadania i jest nieodwracalna: paczka nadana jako
 * „wrzucę do Paczkomatu" wchodzi w stan `CustomerDelivering`, a zlecenie na nią
 * InPost odrzuca (zweryfikowane na sandboxie 2026-08-08). Filtr niżej pilnuje,
 * żeby taka paczka nigdy nie zatruła całego zlecenia — odrzucenie dotyczy
 * bowiem CAŁOŚCI, nie pojedynczej pozycji.
 */
class CourierPickup
{
    public function __construct(private readonly ShipxClient $shipx) {}

    /**
     * Paczki czekające na kuriera: nadane i opłacone, zadeklarowane do odbioru,
     * jeszcze nieobjęte żadnym zleceniem.
     *
     * @return Collection<int, Order>
     */
    public function awaiting(Shop $shop): Collection
    {
        return Order::query()
            ->where('shop_id', $shop->id)
            ->whereNotNull('shipment_id')
            ->whereNull('dispatch_order_id')
            ->where('shipment_sending_method', SendingMethod::DispatchOrder->value)
            ->whereIn('shipment_status', ShipxClient::READY_STATUSES)
            // Doręczonych nikt już nie odbierze.
            ->whereNull('delivered_at')
            ->orderBy('shipped_at')
            ->get();
    }

    /**
     * Zamawia jeden przyjazd kuriera po wskazane zamówienia. Zwraca zapisane
     * zlecenie albo null, gdy nie było czego zamawiać lub InPost odmówił.
     *
     * @param  array<int, int>  $orderIds
     */
    public function request(Shop $shop, array $orderIds, ?string $comment = null): ?DispatchOrder
    {
        $address = $shop->pickupAddress();

        if ($address === null) {
            Log::channel('shipx')->warning('Odbiór kuriera pominięty: sklep bez adresu.', ['shop_id' => $shop->id]);

            return null;
        }

        // Filtrujemy przez `awaiting()`, a nie po samym wejściu z formularza:
        // między wyświetleniem listy a kliknięciem paczka mogła trafić do innego
        // zlecenia, a jedna nieprawidłowa pozycja wywraca CAŁE zlecenie.
        $orders = $this->awaiting($shop)->whereIn('id', $orderIds);

        if ($orders->isEmpty()) {
            return null;
        }

        $response = $this->shipx->createDispatchOrder(
            $shop,
            $orders->pluck('shipment_id')->map(fn ($id) => (int) $id)->all(),
            $address,
            $comment
        );

        if ($response === null || blank($response['id'] ?? null)) {
            return null;
        }

        return DB::transaction(function () use ($shop, $orders, $response) {
            $dispatchOrder = $shop->dispatchOrders()->create([
                'shipx_id' => (int) $response['id'],
                'status' => $response['status'] ?? null,
                'error' => ShipxClient::dispatchFailureReason($response),
            ]);

            // Przypięcie zamówień w tej samej transakcji: bez tego przy błędzie
            // zapisu zostałoby zlecenie „bez paczek", a paczki wróciłyby na
            // listę oczekujących i sprzedawca zamówiłby (i opłacił) drugi przyjazd.
            Order::whereIn('id', $orders->pluck('id'))->update(['dispatch_order_id' => $dispatchOrder->id]);

            return $dispatchOrder;
        });
    }
}
