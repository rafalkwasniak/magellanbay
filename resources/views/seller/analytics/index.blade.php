<x-layouts.panel title="Analityka">
    <x-slot:heading>Analityka</x-slot:heading>

    @php
        // Kafelki KPI składane z policzonych agregatów (serwis ShopAnalytics). Kolor
        // = akcent sparkline'u (hex, niezależny od Tailwinda). Ikony tylko dekoracyjne.
        $k = $analytics['kpis'];
        $tiles = [
            ['label' => 'Obrót', 'icon' => '💰', 'color' => '#f59e0b', 'value' => \App\Support\Money::pln($k['revenue']['value']), 'delta' => $k['revenue']['delta'], 'spark' => $k['revenue']['spark']],
            ['label' => 'Zamówienia', 'icon' => '📦', 'color' => '#10b981', 'value' => (string) (int) $k['orders']['value'], 'delta' => $k['orders']['delta'], 'spark' => $k['orders']['spark']],
            ['label' => 'Średni koszyk', 'icon' => '🧺', 'color' => '#6366f1', 'value' => \App\Support\Money::pln($k['aov']['value']), 'delta' => $k['aov']['delta'], 'spark' => $k['aov']['spark']],
            ['label' => 'Klienci', 'icon' => '👤', 'color' => '#e11d48', 'value' => (string) (int) $k['customers']['value'], 'delta' => $k['customers']['delta'], 'spark' => $k['customers']['spark']],
        ];
    @endphp

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: dane (KPI + wykresy). Kolejne wykresy dojdą tu niżej. --}}
        <div class="lg:col-span-8 space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($tiles as $tile)
                    <div class="rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-stone-500">{{ $tile['label'] }}</p>
                            <span class="text-lg">{{ $tile['icon'] }}</span>
                        </div>

                        <div class="mt-2 flex items-baseline gap-2">
                            <p class="text-3xl font-semibold tracking-tight text-stone-900">{{ $tile['value'] }}</p>

                            {{-- Δ vs poprzedni okres: zielony wzrost / różowy spadek / szare „—"
                                 gdy brak bazy odniesienia (poprzedni okres = 0). --}}
                            @php $delta = $tile['delta']; @endphp
                            @if ($delta === null)
                                <span class="inline-flex items-center rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-400" title="Brak danych z poprzedniego okresu">—</span>
                            @elseif ($delta >= 0)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">▲ {{ number_format($delta, 1, ',', ' ') }}%</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">▼ {{ number_format(abs($delta), 1, ',', ' ') }}%</span>
                            @endif
                        </div>

                        <div class="mt-3">
                            <x-analytics.sparkline :points="$tile['spark']" :color="$tile['color']" />
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Sprzedaż w czasie: jeden słupek na kubełek okna (dzień/miesiąc). --}}
            @php $byMonth = $period === \App\Enums\AnalyticsPeriod::Last12Months; @endphp
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Sprzedaż w czasie</h2>
                <p class="mt-1 text-sm text-stone-500">Obrót w kolejnych {{ $byMonth ? 'miesiącach' : 'dniach' }} — najedź na słupek po szczegóły.</p>
                <div class="mt-5">
                    <x-analytics.bar-chart :series="$analytics['series']" />
                </div>
            </div>

            {{-- Bestsellery: top produkty wg obrotu (poziome paski, długość ∝ obrót). --}}
            @php
                $maxRev = collect($analytics['bestsellers'])->max('revenue') ?: 1;
                $bestsellerRows = array_map(fn ($b) => [
                    'label' => $b['name'],
                    'value' => \App\Support\Money::pln($b['revenue']),
                    'ratio' => $b['revenue'] / $maxRev,
                ], $analytics['bestsellers']);
            @endphp
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Bestsellery</h2>
                <p class="mt-1 text-sm text-stone-500">Najlepiej sprzedające się produkty wg obrotu.</p>
                <div class="mt-5">
                    <x-analytics.bars :rows="$bestsellerRows" color="#f59e0b" empty="Brak sprzedaży w tym okresie." />
                </div>
            </div>

            {{-- Podział zamówień: metoda płatności i metoda dostawy (udział w liczbie). --}}
            @php
                $paymentRows = array_map(fn ($s) => [
                    'label' => $s['label'],
                    'value' => $s['count'].' ('.round($s['share'] * 100).'%)',
                    'ratio' => $s['share'],
                ], $analytics['payment_split']);
                $deliveryRows = array_map(fn ($s) => [
                    'label' => $s['label'],
                    'value' => $s['count'].' ('.round($s['share'] * 100).'%)',
                    'ratio' => $s['share'],
                ], $analytics['delivery_split']);
            @endphp
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Metody płatności</h2>
                    <p class="mt-1 text-sm text-stone-500">Udział w liczbie zamówień.</p>
                    <div class="mt-5">
                        <x-analytics.bars :rows="$paymentRows" color="#10b981" empty="Brak zamówień w tym okresie." />
                    </div>
                </div>
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Metody dostawy</h2>
                    <p class="mt-1 text-sm text-stone-500">Udział w liczbie zamówień.</p>
                    <div class="mt-5">
                        <x-analytics.bars :rows="$deliveryRows" color="#6366f1" empty="Brak zamówień w tym okresie." />
                    </div>
                </div>
            </div>

            {{-- Klienci: nowi vs powracający (wg historii sprzed okna) + najlepsi klienci. --}}
            @php
                $cb = $analytics['customers_breakdown'];
                $cbTotal = $cb['new'] + $cb['returning'];
                $customerTypeRows = $cbTotal === 0 ? [] : [
                    ['label' => 'Nowi klienci', 'value' => $cb['new'].' ('.round($cb['new'] / $cbTotal * 100).'%)', 'ratio' => $cb['new'] / $cbTotal],
                    ['label' => 'Powracający klienci', 'value' => $cb['returning'].' ('.round($cb['returning'] / $cbTotal * 100).'%)', 'ratio' => $cb['returning'] / $cbTotal],
                ];
                $maxCust = collect($analytics['top_customers'])->max('revenue') ?: 1;
                $topCustomerRows = array_map(fn ($c) => [
                    'label' => $c['label'],
                    'value' => \App\Support\Money::pln($c['revenue']),
                    'ratio' => $c['revenue'] / $maxCust,
                ], $analytics['top_customers']);
            @endphp
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Nowi vs powracający</h2>
                    <p class="mt-1 text-sm text-stone-500">Klienci, którzy kupili w tym okresie.</p>
                    <div class="mt-5">
                        <x-analytics.bars :rows="$customerTypeRows" color="#e11d48" empty="Brak klientów w tym okresie." />
                    </div>
                </div>
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Najlepsi klienci</h2>
                    <p class="mt-1 text-sm text-stone-500">Wg wartości zakupów w tym okresie.</p>
                    <div class="mt-5">
                        <x-analytics.bars :rows="$topCustomerRows" color="#f59e0b" empty="Brak klientów w tym okresie." />
                    </div>
                </div>
            </div>
        </div>

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
