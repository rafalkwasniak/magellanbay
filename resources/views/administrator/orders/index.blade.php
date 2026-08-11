<x-layouts.panel title="Zamówienia">
    <x-slot:actions>
        <span class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur">
            {{ $orders->total() }} {{ trans_choice('zamówienie|zamówienia|zamówień', $orders->total()) }}
        </span>
    </x-slot:actions>

    {{-- Ten sam podział 8/4 co w Pakietach i u Sprzedawców. --}}
    <div class="grid gap-6 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-8">
            {{-- Kafelki liczą się z CAŁEGO przefiltrowanego zbioru i pomijają
                 anulowane — lista je pokazuje, bo trzeba je móc znaleźć, ale
                 sprzedażą nie są. --}}
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Zamówienia', (string) $stats['orders'], 'Bez anulowanych', '📦'],
                    ['Sprzedaż', \App\Support\Money::pln($stats['revenue']), 'Suma brutto', '💰'],
                    ['Średni koszyk', \App\Support\Money::pln($stats['basket']), 'Sprzedaż ÷ liczba zamówień', '🧺'],
                    ['Sklepy ze sprzedażą', (string) $stats['shops'], 'Ile sklepów cokolwiek sprzedało', '🛍️'],
                ] as [$label, $value, $hint, $icon])
                    <div class="rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-stone-500">{{ $label }}</p>
                            <span class="text-lg">{{ $icon }}</span>
                        </div>
                        <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-stone-900">{{ $value }}</p>
                        <p class="mt-1 text-xs text-stone-400">{{ $hint }}</p>
                    </div>
                @endforeach
            </div>

            @if ($orders->isEmpty())
                <div class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-stone-300 bg-white/40 px-6 py-16 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-100 text-2xl">📦</span>
                    @if ($hasFilters)
                        <p class="mt-4 font-medium text-stone-700">Żadne zamówienie nie pasuje do tych filtrów</p>
                        <p class="mt-1 max-w-sm text-sm text-stone-500">
                            Zmień kryteria albo <a href="{{ route('administrator.orders.index') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">wyczyść filtry</a>.
                            Pamiętaj, że domyślnie pokazujemy tylko ostatnie 30 dni.
                        </p>
                    @else
                        <p class="mt-4 font-medium text-stone-700">Brak zamówień z ostatnich 30 dni</p>
                        <p class="mt-1 max-w-md text-sm text-stone-500">
                            Trafią tu zamówienia ze wszystkich sklepów na platformie — do szukania po numerze,
                            mailu klienta albo nazwie sklepu.
                        </p>
                    @endif
                </div>
            @else
                <div class="overflow-hidden rounded-3xl border border-white/60 bg-white/70 backdrop-blur">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm" style="min-width: 44rem">
                            <thead class="border-b border-stone-200/70 text-xs uppercase tracking-wide text-stone-400">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Zamówienie</th>
                                    <th class="px-5 py-3 font-medium">Sklep</th>
                                    <th class="px-5 py-3 font-medium">Kupujący</th>
                                    <th class="px-5 py-3 text-right font-medium">Kwota</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($orders as $order)
                                    <tr class="transition hover:bg-white/60">
                                        <td class="px-5 py-3">
                                            <a href="{{ route('administrator.orders.show', $order) }}"
                                                class="font-medium text-stone-900 underline decoration-amber-300 underline-offset-2">{{ $order->number }}</a>
                                            <p class="text-xs tabular-nums text-stone-400">{{ $order->created_at->format('d.m.Y H:i') }}</p>
                                        </td>
                                        <td class="px-5 py-3">
                                            @if ($order->shop)
                                                <a href="{{ route('administrator.shops.edit', $order->shop) }}"
                                                    class="text-stone-700 underline decoration-amber-300 underline-offset-2">{{ $order->shop->name }}</a>
                                            @else
                                                <span class="text-xs text-stone-400">sklep usunięty</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="block truncate text-stone-700">{{ trim($order->buyer_name.' '.$order->buyer_surname) }}</span>
                                            <span class="block truncate text-xs text-stone-400">{{ $order->buyer_email }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-right tabular-nums font-medium text-stone-900">
                                            {{ \App\Support\Money::pln($order->total_gross) }}
                                            <p class="text-xs font-normal text-stone-400">{{ $order->delivery_method?->label() ?? 'bez dostawy' }}</p>
                                        </td>
                                        <td class="px-5 py-3">
                                            {{-- Kolory z enuma (`badgeClasses`) — jedno źródło dla panelu
                                                 sprzedawcy i konsoli, żeby ten sam status nie miał dwóch barw. --}}
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $order->status->badgeClasses() }}">
                                                {{ $order->status->label() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>{{ $orders->links() }}</div>
            @endif
        </div>

        <aside class="space-y-6 lg:col-span-4">
            {{-- Nad filtrami, bo to jedyna rzecz na ekranie, która woła o działanie.
                 Świadomie NIE reaguje na filtry: „co się pali" ma być tą samą
                 odpowiedzią niezależnie od tego, czego akurat szukasz. --}}
            @if ($attention !== [])
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-stone-900">Wymaga uwagi</h2>
                            <p class="mt-0.5 text-sm text-stone-500">Niezależnie od filtrów obok.</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">
                            {{ collect($attention)->sum(fn ($group) => count($group['items'])) }}
                        </span>
                    </div>

                    <div class="mt-5">
                        <x-attention-list :groups="$attention" />
                    </div>

                    <p class="mt-4 text-xs text-stone-400">
                        Konsola tylko pokazuje — naprawia sprzedawca. Te pozycje to lista telefonów do wykonania.
                    </p>
                </div>
            @endif

            <form method="GET" action="{{ route('administrator.orders.index') }}" class="space-y-4 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Filtry</h2>

                <div>
                    <label for="szukaj" class="block text-sm font-medium text-stone-700">Szukaj</label>
                    <input type="search" name="szukaj" id="szukaj" value="{{ $filters['q'] }}" placeholder="Numer, e-mail, nazwisko, sklep"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    <p class="mt-1.5 text-xs text-stone-400">Jedno pole na wszystko, co masz przez telefon.</p>
                </div>

                <div>
                    <label for="okres" class="block text-sm font-medium text-stone-700">Okres</label>
                    <select name="okres" id="okres"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        @foreach ($periods as $value => $period)
                            <option value="{{ $value }}" @selected($filters['period'] === (string) $value)>{{ $period['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-stone-700">Status</label>
                    <select name="status" id="status"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <option value="">Wszystkie</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="sklep" class="block text-sm font-medium text-stone-700">Sklep</label>
                    <select name="sklep" id="sklep"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <option value="">Wszystkie</option>
                        @foreach ($shops as $shop)
                            <option value="{{ $shop->id }}" @selected($filters['shop'] === (string) $shop->id)>{{ $shop->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">Filtruj</button>
                    @if ($hasFilters)
                        <a href="{{ route('administrator.orders.index') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">Wyczyść</a>
                    @endif
                </div>
            </form>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to czytać</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">👀</span>
                        <span>Ten ekran jest <span class="text-stone-700">wyłącznie do odczytu</span>. Zamówieniem steruje sprzedawca — zmiana statusu stąd wysłałaby klientowi maila, o którym sprzedawca by nie wiedział.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🗓️</span>
                        <span>Domyślnie widzisz <span class="text-stone-700">ostatnie 30 dni</span>. Szukając starszego zamówienia, przestaw okres na „Cała historia".</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🧺</span>
                        <span>Kafelki pomijają <span class="text-stone-700">anulowane</span>, żeby średni koszyk nie spadał przez zakupy, które się nie odbyły. Lista pokazuje je normalnie — trzeba je móc znaleźć.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
