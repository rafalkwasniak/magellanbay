<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Arkusz produkcyjny {{ $order->number }}</title>

    {{-- PROSTY HTML, WŁASNY STYL, ZERO ZALEŻNOŚCI.

         To dokument do wydrukowania i położenia obok magnesu, a nie ekran
         panelu. Karty, cienie i siatki Tailwinda nie drukują się przewidywalnie
         i tylko zjadają toner.

         `break-inside: avoid` stoi WYŁĄCZNIE na pojedynczej pozycji — blok
         wyższy niż strona z tą regułą rozjeżdża tekst zamiast go przenieść. --}}
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            font: 13px/1.5 -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            color: #111;
            background: #fff;
        }
        h1 { margin: 0; font-size: 19px; }
        .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #111; padding-bottom: 10px; }
        .meta { text-align: right; font-size: 12px; }
        .meta strong { font-size: 15px; }
        .shop { font-size: 12px; color: #555; margin-top: 2px; }

        .alert { margin: 14px 0 0; border: 2px solid #111; padding: 8px 10px; font-weight: 700; }

        .item { border-bottom: 1px solid #bbb; padding: 12px 0; break-inside: avoid; page-break-inside: avoid; }
        .item-head { display: flex; justify-content: space-between; gap: 16px; }
        .name { font-weight: 700; font-size: 15px; }
        .qty { font-size: 17px; font-weight: 700; white-space: nowrap; }
        .note { font-size: 12px; color: #555; margin-top: 2px; }

        table.pers { border-collapse: collapse; margin-top: 8px; width: 100%; }
        table.pers th, table.pers td { border: 1px solid #999; padding: 5px 8px; text-align: left; vertical-align: top; }
        table.pers th { width: 34%; font-weight: 600; background: #f2f2f2; font-size: 12px; }
        /* Tekst do wykonania — większy i monospace, żeby nie pomylić „l" z „1"
           ani „O" z „0". Na magnesie taka pomyłka jest nie do poprawienia. */
        table.pers td { font-family: ui-monospace, "SF Mono", Consolas, monospace; font-size: 14px; }

        .plain { margin-top: 6px; font-size: 12px; color: #555; font-style: italic; }
        .done { margin-top: 8px; font-size: 12px; color: #555; }

        .foot { margin-top: 20px; border-top: 1px solid #bbb; padding-top: 8px; font-size: 11px; color: #666; }

        .actions { margin-bottom: 18px; }
        .actions button, .actions a {
            font: inherit; padding: 8px 14px; border: 1px solid #999; background: #fff;
            border-radius: 6px; cursor: pointer; text-decoration: none; color: #111; margin-right: 8px;
        }
        @media print {
            body { padding: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">Drukuj</button>
        <a href="{{ route('seller.orders.show', $order) }}">← Wróć do zamówienia</a>
    </div>

    <div class="top">
        <div>
            <h1>Arkusz produkcyjny</h1>
            <p class="shop">{{ $order->shop->name }}</p>
        </div>
        <div class="meta">
            <strong>{{ $order->number }}</strong><br>
            Złożone: {{ $order->created_at->format('d.m.Y H:i') }}<br>
            Status: {{ $order->status->label() }}
        </div>
    </div>

    {{-- Anulowanego nie wykonujemy. Arkusz mógł zostać wydrukowany wcześniej,
         ale ten, który powstaje TERAZ, ma o tym mówić na górze strony. --}}
    @if ($order->status === \App\Enums\OrderStatus::Cancelled)
        <p class="alert">ZAMÓWIENIE ANULOWANE — NIE WYKONYWAĆ</p>
    @endif

    @foreach ($order->items as $item)
        <div class="item">
            <div class="item-head">
                <div>
                    <div class="name">{{ $item->name }}</div>
                    @if ($item->returned_quantity > 0)
                        {{-- Zwrot pokazujemy, ale ilości NIE zmniejszamy: arkusz
                             jest zleceniem wykonania, a zwrot zwykle przychodzi
                             po nim. Decyzję, co z tym zrobić, podejmuje człowiek. --}}
                        <div class="note">Uwaga: zwrócono {{ $item->sale_unit->formatQuantity((float) $item->returned_quantity) }}</div>
                    @endif
                </div>
                <div class="qty">{{ $item->sale_unit->formatQuantity((float) $item->quantity) }}</div>
            </div>

            @if ($item->isPersonalised())
                <table class="pers">
                    @foreach ($item->personalisationLines() as $line)
                        <tr>
                            <th>{{ $line['label'] }}</th>
                            <td>{{ $line['value'] }}</td>
                        </tr>
                    @endforeach
                </table>
            @else
                <p class="plain">Bez personalizacji.</p>
            @endif

            <p class="done">Wykonano: ☐&nbsp;&nbsp;&nbsp; Sprawdzono: ☐</p>
        </div>
    @endforeach

    {{-- CEN TU NIE MA I TO NIE JEST PRZEOCZENIE. Arkusz idzie do pracowni albo
         do podwykonawcy; do wykonania magnesu potrzebny jest tekst i grafika,
         nie kwota, którą zapłacił klient. --}}
    <p class="foot">
        Arkusz do wykonania zamówienia — bez cen i danych płatniczych.
        Wydrukowano {{ now()->format('d.m.Y H:i') }}.
    </p>
</body>
</html>
