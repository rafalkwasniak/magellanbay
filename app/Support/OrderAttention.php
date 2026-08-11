<?php

namespace App\Support;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Zamówienia, które się zacięły — przekrój przez WSZYSTKIE sklepy.
 *
 * Powód istnienia: sprzedawca widzi tylko własny sklep i tylko wtedy, gdy
 * zagląda. Nieudane nadanie w InPoście albo faktura, która nie powstała, potrafi
 * przeleżeć tygodnie, bo nic o sobie nie krzyczy — a klient już czeka. Ta lista
 * jest po to, żeby platforma zauważyła to przed klientem.
 *
 * Ekran zamówień jest TYLKO DO ODCZYTU, więc lista niczego nie naprawia. Jej
 * produktem jest telefon do sprzedawcy — dlatego każda pozycja niesie nazwę
 * sklepu, a nie tylko numer zamówienia.
 *
 * Świadomie NIE filtrujemy jej filtrami ekranu: „co się pali" ma być tą samą
 * odpowiedzią niezależnie od tego, czego akurat szukasz.
 */
class OrderAttention
{
    /**
     * Grupy w kolejności PILNOŚCI: najpierw to, co zatrzymało towar w drodze do
     * klienta, na końcu porzucony koszyk, który zwykle nic nie znaczy.
     *
     * Grupy puste wypadają z wyniku — lista ma pokazywać robotę do zrobienia.
     *
     * @return list<array{key: string, label: string, hint: string, tone: string, items: list<array{title: string, subtitle: string, note: string, url: ?string}>}>
     */
    public static function groups(): array
    {
        $stalledDays = (int) config('shop.orders.attention.stalled_days');
        $unpaidHours = (int) config('shop.orders.attention.unpaid_hours');

        $groups = [
            [
                'key' => 'shipment_failed',
                'label' => 'Nadanie nie powiodło się',
                'hint' => 'InPost odrzucił przesyłkę. Towar stoi, a sprzedawca mógł tego nie zauważyć.',
                'tone' => 'rose',
                'items' => self::items(
                    Order::query()
                        ->whereNotNull('shipment_error')
                        ->where('status', '!=', OrderStatus::Cancelled->value)
                        ->with('shop')
                        ->latest('updated_at')
                        ->get(),
                    // Powód prosto od InPostu — bez niego admin dzwoniłby zapytać
                    // o to samo, co system już wie. Ucinamy po pełnym słowie,
                    // zgodnie z konwencją skracania tekstu w projekcie.
                    fn (Order $order): string => Str::limit((string) $order->shipment_error, 70, '…', preserveWords: true),
                ),
            ],
            [
                'key' => 'invoice_failed',
                'label' => 'Faktura nie powstała',
                'hint' => 'Wywołanie Fakturowni nie powiodło się. Dokumentu nie ma, a klient mógł o niego prosić.',
                'tone' => 'rose',
                'items' => self::items(
                    Order::query()
                        ->where('invoice_status', InvoiceStatus::Failed->value)
                        ->with('shop')
                        ->latest('updated_at')
                        ->get(),
                    fn (Order $order): string => 'Zamówienie z '.$order->created_at->format('d.m.Y'),
                ),
            ],
            [
                'key' => 'stalled',
                'label' => 'Utknęło w realizacji',
                'hint' => 'Opłacone ponad '.$stalledDays.' dni temu i wciąż niewydane klientowi.',
                'tone' => 'amber',
                'items' => self::items(
                    Order::query()
                        // Tylko stany PO STRONIE SPRZEDAWCY. „Gotowe do odbioru"
                        // świadomie odpada: tam piłka jest u klienta, który ma
                        // przyjść po paczkę, a wołanie sprzedawcy byłoby niesłuszne.
                        ->whereIn('status', [
                            OrderStatus::Paid->value,
                            OrderStatus::Processing->value,
                            OrderStatus::ReadyForShipment->value,
                        ])
                        ->where('created_at', '<', now()->subDays($stalledDays))
                        ->with('shop')
                        ->oldest('created_at')
                        ->get(),
                    fn (Order $order): string => 'Czeka '.$order->created_at->diffInDays(now()).' dni · '
                        .$order->status->label(),
                ),
            ],
            [
                'key' => 'unpaid',
                'label' => 'Płatność wisi',
                'hint' => 'Klient nie wrócił z bramki. Zwykle porzucony koszyk, ale towar bywa zablokowany.',
                'tone' => 'stone',
                'items' => self::items(
                    Order::query()
                        ->where('status', OrderStatus::AwaitingPayment->value)
                        ->where('created_at', '<', now()->subHours($unpaidHours))
                        ->with('shop')
                        ->latest('created_at')
                        ->get(),
                    fn (Order $order): string => 'Rozpoczęte '.$order->created_at->format('d.m.Y')
                        .' · '.Money::pln($order->total_gross),
                ),
            ],
        ];

        return array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== []));
    }

    /**
     * Ile spraw łącznie — do plakietki, bez budowania całej listy po stronie
     * wywołującego.
     */
    public static function count(): int
    {
        return array_sum(array_map(fn (array $group): int => count($group['items']), self::groups()));
    }

    /**
     * Tytułem jest NUMER zamówienia, a podtytułem sklep: numer identyfikuje
     * sprawę, a sklep mówi, do kogo zadzwonić. Odwrotna kolejność zmuszałaby do
     * czytania drugiej linijki przy każdej pozycji.
     *
     * @param  Collection<int, Order>  $orders
     * @param  callable(Order): string  $note
     * @return list<array{title: string, subtitle: string, note: string, url: ?string}>
     */
    private static function items(Collection $orders, callable $note): array
    {
        return $orders
            // Zamówienie osieroconego sklepu nie ma do kogo prowadzić.
            ->filter(fn (Order $order): bool => $order->shop !== null)
            ->map(fn (Order $order): array => [
                'title' => $order->number,
                'subtitle' => $order->shop->name,
                'note' => $note($order),
                'url' => route('administrator.orders.show', $order),
            ])->values()->all();
    }
}
