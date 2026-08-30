<x-layouts.panel title="Analityka">
    <x-slot:heading>Analityka{{ $shop ? ' — '.$shop->name : '' }}</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Ta sama główna kolumna co u sprzedawcy — różni się tylko zakres danych
             (bez wybranego sklepu: suma wszystkich). --}}
        <x-analytics.dashboard :analytics="$analytics" :period="$period" />

        <aside class="lg:col-span-4 space-y-6">
            {{-- Jeden formularz na oba filtry — wzorzec z działu „Zamówienia".
                 Linki jak u sprzedawcy musiałyby przenosić drugi parametr w obie
                 strony; formularz trzyma stan filtrów w jednym miejscu. --}}
            <form method="GET" action="{{ route('administrator.analytics.index') }}" class="space-y-4 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Filtry</h2>

                <div>
                    <label for="sklep" class="block text-sm font-medium text-stone-700">Sklep</label>
                    <select name="sklep" id="sklep"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <option value="">Wszystkie sklepy (sumarycznie)</option>
                        @foreach ($shops as $option)
                            <option value="{{ $option->id }}" @selected($shop?->id === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="okres" class="block text-sm font-medium text-stone-700">Okres</label>
                    <select name="okres" id="okres"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        @foreach ($periods as $option)
                            <option value="{{ $option->value }}" @selected($option === $period)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">Filtruj</button>
                    @if ($shop)
                        <a href="{{ route('administrator.analytics.index', ['okres' => $period->value]) }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">Wszystkie sklepy</a>
                    @endif
                </div>
            </form>

            {{-- Opis: co dokładnie znaczą liczby w przekroju platformy. Dwie rzeczy
                 potrafią zaskoczyć przy sumie — zakres sklepów i sposób liczenia
                 klientów — więc mówimy je wprost, zamiast liczyć na domysł. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Skąd te liczby</h2>
                <p class="mt-2 text-sm text-stone-500">
                    Liczone na bieżąco z zamówień (bez anulowanych) — dokładnie tym samym rachunkiem, który widzi sprzedawca u siebie.
                </p>
                <p class="mt-3 text-sm text-stone-500">
                    Suma obejmuje <span class="font-medium text-stone-700">wszystkie sklepy</span>, także wyłączone i te w karencji na usunięcie — ich sprzedaż się wydarzyła.
                </p>
                <p class="mt-3 text-sm text-stone-500">
                    Klient liczony jest <span class="font-medium text-stone-700">osobno w każdym sklepie</span> (konta klientów są per sklep), więc suma platformy zgadza się z sumą pojedynczych sklepów.
                </p>
                <p class="mt-3 text-sm text-stone-500">
                    Strzałki pokazują zmianę <span class="font-medium text-stone-700">vs poprzedni okres</span> tej samej długości. „—" oznacza brak danych do porównania.
                </p>
            </div>
        </aside>
    </div>
</x-layouts.panel>
