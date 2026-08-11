<x-layouts.panel title="Pakiety">
    {{-- Sama plakietka, jak na pozostałych listach (Sklepy, Sprzedawcy). Wejście
         do rejestru opłat prowadzi z karty w kolumnie bocznej, nie z nagłówka. --}}
    <x-slot:actions>
        <span class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur">
            {{ $subscriptions['paying'] }} {{ trans_choice('płacący sklep|płacące sklepy|płacących sklepów', $subscriptions['paying']) }}
        </span>
    </x-slot:actions>

    {{-- Układ 8/4 jak u Sprzedawców: po lewej dane, po prawej to, co się robi i
         czym się to czyta. Kafelki siedzą W LEWEJ kolumnie, nie nad całością —
         dzięki temu kolumna boczna zaczyna się przy górnej krawędzi ekranu i
         cała strona czyta się jako dwa pasy, a nie trzy piętra. --}}
    <div class="grid gap-6 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-8">
            {{-- Trzy okna na te same pieniądze, bo odpowiadają na trzy różne pytania:
                 rok KALENDARZOWY (jak zamknął się rok obrotowy), ostatnie 12 miesięcy
                 (ruchome okno — w styczniu rok kalendarzowy jest prawie pusty i sam
                 zmyliłby obraz) oraz suma od początku.

                 Siatka 2×2, nie 1×4: w kolumnie o szerokości 8/12 cztery kafelki
                 obok siebie ścisnęłyby kwotę i podpis do nieczytelnych słupków. --}}
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Przychód — w tym roku', \App\Support\Money::pln($revenue['year']), 'Opłaty od 1 stycznia '.now()->year, '🗓️'],
                    ['Przychód — 12 miesięcy', \App\Support\Money::pln($revenue['last12m']), 'Ostatnie 12 miesięcy, licząc od dziś', '💰'],
                    ['Przychód — łącznie', \App\Support\Money::pln($revenue['total']), $revenue['count'].' '.trans_choice('opłata|opłaty|opłat', $revenue['count']).' od początku platformy', '📈'],
                    ['Sklepy płacące', $subscriptions['paying'].' / '.$subscriptions['shops'], $subscriptions['comped'] > 0 ? $subscriptions['comped'].' '.trans_choice('sklep na gratisie|sklepy na gratisie|sklepów na gratisie', $subscriptions['comped']) : 'Nikt nie ma dostępu gratisowego', '🛍️'],
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

            <div class="overflow-hidden rounded-3xl border border-white/60 bg-white/70 backdrop-blur">
                <div class="border-b border-stone-200/70 px-5 py-4">
                    <h2 class="font-medium text-stone-900">Rozkład po pakietach</h2>
                    <p class="mt-0.5 text-sm text-stone-500">
                        „Sklepy" to wszyscy z danym pakietem, „płacące" — ci, którzy realnie za niego płacą:
                        bez dostępu gratisowego, bez pakietów darmowych i bez sklepów po wygaśnięciu.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    {{-- Węższe minimum niż wcześniej: tabela mieszka teraz w kolumnie
                         8/12, a nie na pełnej szerokości — przy 40rem wyskakiwał
                         poziomy pasek na mniejszych laptopach. --}}
                    <table class="w-full text-left text-sm" style="min-width: 34rem">
                        <thead class="border-b border-stone-200/70 text-xs uppercase tracking-wide text-stone-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Pakiet</th>
                                <th class="px-5 py-3 text-right font-medium">Cena / rok</th>
                                <th class="px-5 py-3 text-right font-medium">Sklepy</th>
                                <th class="px-5 py-3 text-right font-medium">Płacące</th>
                                <th class="px-5 py-3 text-right font-medium">Wartość roczna</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($subscriptions['packages'] as $package)
                                <tr class="transition hover:bg-white/60">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('administrator.shops.index', ['package' => $package['slug']]) }}"
                                            class="font-medium text-stone-900 underline decoration-amber-300 underline-offset-2">{{ $package['name'] }}</a>
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums text-stone-500">
                                        {{-- Cennik, nie średnia z realnych umów: sklep z ceną indywidualną
                                             ma własną stawkę i widać ją dopiero w wartości rocznej. --}}
                                        @if ((float) config("shop.packages.{$package['slug']}.price_yearly") > 0)
                                            {{ number_format((float) config("shop.packages.{$package['slug']}.price_yearly"), 0, ',', ' ') }} zł
                                        @else
                                            <span class="text-stone-400">darmowy</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums text-stone-700">{{ $package['shops'] }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-stone-700">{{ $package['paying'] }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums font-medium text-stone-900">
                                        @if ($package['annualValue'] > 0)
                                            {{ \App\Support\Money::pln($package['annualValue']) }}
                                        @else
                                            <span class="font-normal text-stone-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-stone-200/70 bg-white/40">
                            <tr>
                                <td class="px-5 py-3 font-medium text-stone-900" colspan="2">Razem</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium text-stone-900">{{ $subscriptions['shops'] }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium text-stone-900">{{ $subscriptions['paying'] }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium text-stone-900">{{ \App\Support\Money::pln($subscriptions['annualValue']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <aside class="lg:col-span-4 space-y-6">
            {{-- Sedno ekranu przy sprzedaży z ręki: terminy nie pilnują się same,
                 dopóki nie ma automatycznego billingu. Grupy puste w ogóle się nie
                 renderują — lista ma być robotą do zrobienia, nie tablicą zer. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-900">Wymaga uwagi</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Sprawy, o których nikt inny nie przypomni.</p>
                    </div>
                    @if ($attention !== [])
                        <span class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">{{ $attentionCount }}</span>
                    @endif
                </div>

                @if ($attention === [])
                    <div class="mt-5 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 bg-white/40 px-4 py-10 text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-2xl">✅</span>
                        <p class="mt-3 font-medium text-stone-700">Nic nie wymaga uwagi</p>
                        <p class="mt-1 text-sm text-stone-500">
                            Żaden abonament nie kończy się w ciągu {{ config('shop.subscription.notice_days') }} dni,
                            żaden nie wygasł ani nie czeka na zaległą wpłatę, a każda opłata ma fakturę.
                        </p>
                    </div>
                @else
                    <div class="mt-5 space-y-5">
                        @foreach ($attention as $group)
                            <div>
                                <h3 @class([
                                    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                                    'bg-rose-50 text-rose-700' => $group['tone'] === 'rose',
                                    'bg-amber-50 text-amber-700' => $group['tone'] === 'amber',
                                    'bg-stone-100 text-stone-600' => $group['tone'] === 'stone',
                                ])>{{ $group['label'] }} · {{ count($group['items']) }}</h3>
                                <p class="mt-1.5 text-xs text-stone-400">{{ $group['hint'] }}</p>

                                {{-- W wąskiej kolumnie nazwa i termin idą JEDNO POD DRUGIM.
                                     Rozstrzelone w poprzek (jak było na pełnej szerokości)
                                     zawijałyby się w schodki przy dłuższej nazwie sklepu. --}}
                                <ul class="mt-2 space-y-1">
                                    @foreach ($group['items'] as $item)
                                        <li>
                                            <a href="{{ $item['url'] }}"
                                                class="block rounded-2xl px-3 py-2 transition hover:bg-white">
                                                <span class="block truncate text-sm font-medium text-stone-900">{{ $item['title'] }}</span>
                                                <span class="mt-0.5 block text-xs text-stone-500">{{ $item['note'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-semibold text-stone-900">Rejestr opłat</h2>
                    {{-- Link BEZWARUNKOWY: to jedyne wejście do rejestru z tego ekranu,
                         a pusty rejestr też trzeba móc otworzyć (choćby po to, żeby
                         zobaczyć wiszące płatności, które nie liczą się do przychodu). --}}
                    <a href="{{ route('administrator.packages.payments') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">Wszystkie →</a>
                </div>

                @if ($recentPayments->isEmpty())
                    {{-- Zero na kafelku przychodu wygląda jak zepsuty ekran, a jest
                         prawdą o pustym rejestrze. Ta kartka mówi wprost, skąd liczby
                         się biorą — i że pakiet nadany z konsoli pieniędzy nie oznacza. --}}
                    <div class="mt-4 rounded-2xl border border-dashed border-stone-300 bg-white/40 px-4 py-10 text-center">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-100 text-2xl">💰</span>
                        <p class="mt-3 font-medium text-stone-700">Rejestr opłat jest jeszcze pusty</p>
                        <p class="mt-1 text-sm text-stone-500">
                            Pakiet nadany w konsoli sklepów nie jest dowodem wpłaty i nie podbija przychodu.
                            Przelew albo gotówkę wpisz ręcznie.
                        </p>
                    </div>
                @else
                    <ul class="mt-4 divide-y divide-stone-100">
                        @foreach ($recentPayments as $payment)
                            <li class="flex items-baseline justify-between gap-3 py-2.5">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-stone-900">{{ $payment->shop?->name ?? 'sklep usunięty' }}</span>
                                    <span class="mt-0.5 block text-xs text-stone-400">
                                        {{ $payment->paid_at?->format('d.m.Y') ?? '—' }} · {{ $payment->methodLabel() }}
                                    </span>
                                </span>
                                <span class="shrink-0 text-sm tabular-nums font-medium text-stone-900">{{ \App\Support\Money::pln($payment->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <a href="{{ route('administrator.packages.payments.create') }}"
                    class="mt-4 block rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-center text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                    Zarejestruj wpłatę
                </a>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to czytać</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">💰</span>
                        <span><span class="text-stone-700">Przychód</span> patrzy WSTECZ — liczymy go wyłącznie z zarejestrowanych opłat. Pakiet ustawiony ręcznie w <a href="{{ route('administrator.shops.index') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">konsoli sklepów</a> nie jest dowodem wpłaty.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🗓️</span>
                        <span><span class="text-stone-700">Wartość roczna</span> patrzy W PRZÓD — ile biegnące abonamenty są warte przez rok, jeśli nikt nie odejdzie. To jedyna liczba na tym ekranie, która nie jest przychodem.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🛍️</span>
                        <span><span class="text-stone-700">Sklepy płacące</span> pomijają dostęp gratisowy, pakiety darmowe i sklepy po wygaśnięciu — to liczba realnych klientów, nie kont.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
