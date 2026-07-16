<x-layouts.panel title="Zamówienia">
    <x-slot:heading>Zamówienia</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: lista zamówień --}}
        <div class="lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold text-stone-900">Twoje zamówienia</h2>
                    {{-- Sortowanie: GET bez `page` → zmiana zeruje paginację; hidden pola
                         niosą aktywne filtry, żeby sort ich nie gubił. --}}
                    @if ($total > 0)
                        <form method="GET" action="{{ route('seller.orders.index') }}" class="flex items-center gap-2">
                            @foreach (['status', 'data_od', 'data_do', 'kwota_od', 'kwota_do', 'produkt'] as $k)
                                @if (filled($filters[$k]))<input type="hidden" name="{{ $k }}" value="{{ $filters[$k] }}">@endif
                            @endforeach
                            <label for="sortowanie" class="text-sm text-stone-500">Sortuj</label>
                            <select id="sortowanie" name="sortowanie" onchange="this.form.submit()"
                                class="rounded-2xl border border-stone-200 bg-white/80 px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @foreach ($sortOptions as $opt)
                                    <option value="{{ $opt['key'] }}" @selected($opt['active'])>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>

                @if ($total === 0)
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">📦</span>
                        <p class="mt-4 font-medium text-stone-700">Nie masz jeszcze zamówień</p>
                        <p class="mt-1 text-sm text-stone-500">Gdy klient złoży zamówienie, zobaczysz je tutaj — z danymi i statusem.</p>
                    </div>
                @elseif ($orders->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🔍</span>
                        <p class="mt-4 font-medium text-stone-700">Brak zamówień pasujących do filtrów</p>
                        <p class="mt-1 text-sm text-stone-500">Zmień kryteria lub wyczyść filtry, aby zobaczyć więcej.</p>
                        <a href="{{ route('seller.orders.index', $sortKey !== 'domyslne' ? ['sortowanie' => $sortKey] : []) }}"
                            class="mt-5 inline-flex rounded-2xl border border-stone-200 bg-white/70 px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-white">
                            Wyczyść filtry
                        </a>
                    </div>
                @else
                    <div class="mt-6 space-y-2">
                        @foreach ($orders as $order)
                            <a href="{{ route('seller.orders.show', ['order' => $order] + $listQuery) }}"
                                class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white/80 px-4 py-3.5 shadow-sm transition hover:border-amber-300 hover:shadow-md">
                                {{-- Lewa: numer, kupujący, pozycje · data --}}
                                <div class="min-w-0">
                                    <p class="font-semibold text-stone-900">Zamówienie #{{ $order->number }}</p>
                                    <p class="mt-0.5 truncate text-sm font-medium text-stone-700">
                                        {{ $order->is_company && filled($order->company_name) ? $order->company_name : trim($order->buyer_name.' '.$order->buyer_surname) }}
                                    </p>
                                    <p class="text-xs text-stone-400">
                                        {{ $order->items_count }} {{ trans_choice('pozycja|pozycje|pozycji', $order->items_count) }} · {{ $order->created_at->format('d.m.Y, H:i') }}
                                    </p>
                                </div>
                                {{-- Prawa: wartość + aktualny status --}}
                                <div class="flex shrink-0 flex-col items-end gap-1.5 text-right">
                                    <span class="font-bold tabular-nums text-stone-900">{{ \App\Support\Money::pln($order->total_gross) }}</span>
                                    <div class="flex items-center gap-1.5">
                                        @if ($order->hasInvoice())
                                            {{-- Plakietka „FV" (tylko marker) — pokazuje, że do zamówienia jest już faktura. --}}
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800" title="Faktura VAT wystawiona">FV</span>
                                        @endif
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $order->status->badgeClasses() }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    @if ($orders->hasPages())
                        <div class="mt-6">
                            {{ $orders->onEachSide(1)->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Kolumna pomocnicza: filtry + statystyki z wyświetlonych --}}
        <aside class="lg:col-span-4 space-y-6">
            @if ($total > 0 || $hasFilters)
                {{-- Filtry: GET bez `page` → włączenie/zmiana filtra zeruje paginację do
                     pierwszej strony. Aktywne sortowanie niesiemy hidden polem. --}}
                <form method="GET" action="{{ route('seller.orders.index') }}" class="space-y-4 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-stone-900">Filtry</h2>
                        @if ($hasFilters)
                            <a href="{{ route('seller.orders.index', $sortKey !== 'domyslne' ? ['sortowanie' => $sortKey] : []) }}"
                                class="text-xs font-medium text-stone-500 underline decoration-stone-300 underline-offset-2 transition hover:text-stone-700">Wyczyść</a>
                        @endif
                    </div>

                    @if ($sortKey !== 'domyslne')
                        <input type="hidden" name="sortowanie" value="{{ $sortKey }}">
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-stone-700">Data zamówienia</label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <input type="date" name="data_od" aria-label="Data od" value="{{ $filters['data_od'] }}"
                                class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <span class="text-stone-400">–</span>
                            <input type="date" name="data_do" aria-label="Data do" value="{{ $filters['data_do'] }}"
                                class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-stone-700">Kwota (zł)</label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <input type="text" inputmode="decimal" name="kwota_od" placeholder="od" aria-label="Kwota od"
                                value="{{ $filters['kwota_od'] !== null ? number_format($filters['kwota_od'], 2, ',', '') : '' }}"
                                class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <span class="text-stone-400">–</span>
                            <input type="text" inputmode="decimal" name="kwota_do" placeholder="do" aria-label="Kwota do"
                                value="{{ $filters['kwota_do'] !== null ? number_format($filters['kwota_do'], 2, ',', '') : '' }}"
                                class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        </div>
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-stone-700">Status</label>
                        <select id="status" name="status"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <option value="">— dowolny —</option>
                            @foreach (\App\Enums\OrderStatus::cases() as $case)
                                <option value="{{ $case->value }}" @selected($filters['status'] === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($productOptions !== [])
                        <div>
                            <label for="produkt" class="block text-sm font-medium text-stone-700">Produkt</label>
                            <select id="produkt" name="produkt"
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                <option value="">— dowolny —</option>
                                @foreach ($productOptions as $id => $name)
                                    <option value="{{ $id }}" @selected($filters['produkt'] === (int) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <button type="submit"
                        class="w-full rounded-2xl border border-amber-200 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-100">
                        Filtruj
                    </button>
                </form>

                {{-- Twoja sprzedaż — dynamicznie z wyświetlonego (po filtrach) zbioru,
                     liczone ze wszystkich stron, nie tylko z bieżącej. --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="text-sm font-medium text-stone-500">Twoja sprzedaż</h2>
                    {{-- „bez anulowanych" mówimy wprost: lista niżej je pokazuje, więc
                         inaczej kafelki wyglądałyby na niezgodne z tym, co widać. --}}
                    <p class="mt-1 text-xs text-stone-400">{{ $hasFilters ? 'Z wyświetlonych zamówień' : 'Ze wszystkich zamówień' }}, bez anulowanych</p>
                    @php($qty = $stats['products'])
                    <dl class="mt-4 space-y-3">
                        @foreach ([
                            ['Zamówienia', (string) $stats['orders'], '📦'],
                            ['Produkty', $qty == (int) $qty ? (string) (int) $qty : number_format($qty, 2, ',', ' '), '🏷️'],
                            ['Przychód', \App\Support\Money::pln($stats['revenue']), '💰'],
                        ] as [$label, $value, $icon])
                            <div class="flex items-center justify-between gap-3">
                                <dt class="flex items-center gap-2 text-sm text-stone-500"><span>{{ $icon }}</span>{{ $label }}</dt>
                                <dd class="font-semibold tabular-nums text-stone-900">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.panel>
