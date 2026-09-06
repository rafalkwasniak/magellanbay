<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\OrderItemComponent;
use App\Models\Shop;
use App\Support\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rozliczenie z partnerami licencyjnymi za wybrany okres.
 *
 * Odpowiada na pytanie, które postawił klient: „ile sztuk sprzedano z logo
 * Biegu Gdańskiego w marcu i ile się im należy".
 *
 * ---------------------------------------------------------------------------
 * ZAŁOŻENIA, KTÓRE TU PRZYJĘLIŚMY — bo to pieniądze i nie mogą być domysłem
 *
 * 1. WCHODZI WSZYSTKO POZA ANULOWANYM. Tak liczy analityka sklepu i tak samo
 *    liczymy tutaj; dwie różne definicje sprzedaży w jednym panelu to pewny
 *    spór o to, która jest prawdziwa.
 *
 * 2. NIEZAPŁACONE JEST WYKAZANE OSOBNO. Zamówienie czeka na przelew, więc
 *    pieniędzy jeszcze nie ma — ale sprzedaż jest. Nie decydujemy za
 *    właściciela, czy płacić partnerowi z góry: pokazujemy kwotę i mówimy,
 *    ile z niej jeszcze nie wpłynęło.
 *
 * 3. ZWROT ODEJMUJE. Klient oddał magnes, umowa się cofnęła, licencja się nie
 *    należy. Liczymy ilość PO zwrotach (`effectiveQuantity`), a nie zamówioną.
 *
 * 4. OKRES BIERZEMY Z DATY ZAMÓWIENIA. Ta sama data, po której sprzedawca
 *    ogląda sprzedaż w analityce i po której partner rozpozna swój bieg.
 *
 * 5. KWOTY SĄ BRUTTO — bo takie wpisuje sprzedawca przy grafice i przy
 *    produkcie, i takie widzi kupujący w rozbiciu ceny. Przeliczanie na netto
 *    wymagałoby stawki VAT dla samej licencji, której nikt nigdzie nie podaje.
 *
 * ---------------------------------------------------------------------------
 * DLACZEGO PO MIGAWCE NAZWY, A NIE PO KARTOTECE
 *
 * Wiersz rozbicia niesie `licensor_name` — nazwę z chwili sprzedaży. Partner
 * mógł od tego czasu zmienić nazwę albo wypaść z kartoteki; rozliczenie za
 * marzec ma pokazywać, komu należały się pieniądze W MARCU. Grupujemy po
 * identyfikatorze (gdy jest), ale wyświetlamy migawkę.
 */
class LicensorSettlement
{
    /**
     * Podsumowanie: jeden wiersz na partnera.
     *
     * @return Collection<int, object{licensor_id: ?int, name: string, quantity: float, amount: float, unpaid: float, orders: int}>
     */
    public function summary(Shop $shop, Carbon $from, Carbon $to): Collection
    {
        return $this->rows($shop, $from, $to)
            ->groupBy(fn (object $row): string => (string) ($row->licensor_id ?? 'x'.$row->name))
            ->map(function (Collection $group): object {
                $first = $group->first();

                return (object) [
                    'licensor_id' => $first->licensor_id,
                    'name' => $first->name,
                    'quantity' => round($group->sum('quantity'), 2),
                    'amount' => round($group->sum('amount'), 2),
                    'unpaid' => round($group->where('paid', false)->sum('amount'), 2),
                    'orders' => $group->pluck('order_id')->unique()->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * Pozycje szczegółowe — podstawa arkusza i ekranu „co się na to złożyło".
     *
     * @return Collection<int, object>
     */
    public function rows(Shop $shop, Carbon $from, Carbon $to, ?int $licensorId = null): Collection
    {
        $components = OrderItemComponent::query()
            ->licences()
            ->whereHas('item.order', fn (Builder $query) => $query
                ->where('shop_id', $shop->id)
                ->where('status', '!=', OrderStatus::Cancelled->value)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to))
            ->when($licensorId !== null, fn (Builder $query) => $query->where('licensor_id', $licensorId))
            ->with(['item.order'])
            ->get();

        return $components
            ->map(function (OrderItemComponent $component): ?object {
                $item = $component->item;
                $order = $item?->order;

                if ($item === null || $order === null) {
                    return null;
                }

                // Ilość PO zwrotach: oddany magnes nie generuje opłaty.
                $quantity = $item->effectiveQuantity();

                if ($quantity <= 0) {
                    return null;
                }

                $unit = round((float) $component->unit_amount_gross, 2);

                return (object) [
                    'licensor_id' => $component->licensor_id,
                    // Migawka nazwy z chwili sprzedaży — patrz nagłówek klasy.
                    'name' => $component->licensor_name ?: 'Bez wskazanego partnera',
                    'order_id' => $order->id,
                    'order_number' => $order->number ?? (string) $order->id,
                    'date' => $order->created_at,
                    'status' => $order->status,
                    'paid' => $this->isPaid($order->status),
                    'product' => $item->name,
                    'label' => $component->label,
                    'quantity' => $quantity,
                    'unit_amount' => $unit,
                    'amount' => round($unit * $quantity, 2),
                ];
            })
            ->filter()
            ->sortBy([['date', 'asc'], ['order_id', 'asc']])
            ->values();
    }

    /**
     * Czy pieniądze za to zamówienie już są.
     *
     * „Nowe" i „oczekuje na płatność" to sprzedaż jeszcze nieopłacona — reszta
     * statusów niesie zapłatę albo ją zakłada (sklep nie wysyła paczki za
     * darmo). Anulowane w ogóle tu nie dociera.
     */
    private function isPaid(OrderStatus $status): bool
    {
        return ! in_array($status, [OrderStatus::New, OrderStatus::AwaitingPayment], true);
    }

    /**
     * Arkusz .xlsx: podsumowanie i pozycje, po jednym arkuszu na każde.
     */
    public function workbook(Shop $shop, Carbon $from, Carbon $to): string
    {
        $summary = [[
            'Partner', 'Zamówień', 'Sztuk', 'Należne brutto (zł)', 'W tym niezapłacone (zł)',
        ]];

        foreach ($this->summary($shop, $from, $to) as $row) {
            $summary[] = [$row->name, $row->orders, $row->quantity, $row->amount, $row->unpaid];
        }

        $details = [[
            'Data', 'Zamówienie', 'Status', 'Zapłacone', 'Partner', 'Produkt',
            'Składnik', 'Sztuk', 'Stawka brutto (zł)', 'Razem brutto (zł)',
        ]];

        foreach ($this->rows($shop, $from, $to) as $row) {
            $details[] = [
                $row->date->format('Y-m-d'),
                $row->order_number,
                $row->status->label(),
                $row->paid ? 'tak' : 'nie',
                $row->name,
                $row->product,
                $row->label,
                $row->quantity,
                $row->unit_amount,
                $row->amount,
            ];
        }

        return (new Xlsx)
            ->sheet('Podsumowanie', $summary)
            ->sheet('Pozycje', $details)
            ->contents();
    }
}
