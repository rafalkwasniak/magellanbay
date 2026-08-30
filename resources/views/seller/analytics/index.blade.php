<x-layouts.panel title="Analityka">
    <x-slot:heading>Analityka</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna (KPI + wykresy) mieszka we wspólnym komponencie —
             ten sam ekran ogląda administrator w przekroju platformy. --}}
        <x-analytics.dashboard :analytics="$analytics" :period="$period" />

        {{-- Kolumna pomocnicza: filtry (okres) + opis danych — wzorzec jak w Zamówieniach. --}}
        <aside class="lg:col-span-4 space-y-6">
            {{-- Okres: proste linki GET (bez JS). Kroczące okna — porównanie „vs poprzedni
                 okres" bierze poprzednie okno tej samej długości. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Okres</h2>
                <div class="mt-4 space-y-1">
                    @foreach ($periods as $option)
                        <a href="{{ route('seller.analytics.index', ['okres' => $option->value]) }}"
                           @class([
                               'flex items-center justify-between rounded-2xl px-4 py-2.5 text-sm transition',
                               'bg-white font-medium text-stone-900 shadow-sm' => $option === $period,
                               'text-stone-500 hover:bg-white/60' => $option !== $period,
                           ])>
                            <span>{{ $option->label() }}</span>
                            @if ($option === $period)
                                <span class="text-amber-500" aria-hidden="true">✓</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Opis: skąd te liczby (buduje zaufanie + tłumaczy „vs poprzedni okres"). --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Skąd te liczby</h2>
                <p class="mt-2 text-sm text-stone-500">
                    Liczone na bieżąco z Twoich zamówień (bez anulowanych) — nie śledzimy ruchu ani nie zapisujemy niczego przy wejściu klienta.
                </p>
                <p class="mt-3 text-sm text-stone-500">
                    Strzałki pokazują zmianę <span class="font-medium text-stone-700">vs poprzedni okres</span> tej samej długości. „—" oznacza brak danych do porównania.
                </p>
            </div>
        </aside>
    </div>
</x-layouts.panel>
