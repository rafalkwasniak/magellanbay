@props([
    'rows' => [],        // list<array{label:string, value:string, ratio:float}>
    'color' => '#f59e0b', // jeden hue — tożsamość niesie etykieta, nie kolor (bez legendy)
    'empty' => 'Brak danych w tym okresie.',
])

{{-- Poziome paski rankingowe/udziałowe. Szerokość paska = `ratio` (0..1) inline
     stylem, żeby nie zależeć od dynamicznych klas szerokości Tailwinda. Wartość
     (obrót / udział) jako etykieta bezpośrednia po prawej — w kolorze tekstu, nie
     serii. `truncate` z `min-w-0` skraca długą nazwę bez rozpychania wiersza. --}}
@if (count($rows) === 0)
    <div class="flex h-24 items-center justify-center rounded-2xl border border-dashed border-stone-300 text-sm text-stone-400">
        {{ $empty }}
    </div>
@else
    <div class="space-y-3">
        @foreach ($rows as $row)
            @php($pct = ($row['ratio'] ?? 0) <= 0 ? 0 : max(2, round($row['ratio'] * 100, 1)))
            <div>
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="min-w-0 truncate text-stone-700">{{ $row['label'] }}</span>
                    <span class="shrink-0 font-medium tabular-nums text-stone-900">{{ $row['value'] }}</span>
                </div>
                <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-stone-100">
                    <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $color }}"></div>
                </div>
            </div>
        @endforeach
    </div>
@endif
