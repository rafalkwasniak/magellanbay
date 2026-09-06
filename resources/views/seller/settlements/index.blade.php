{{-- ROZLICZENIA Z PARTNERAMI — komu i ile należy się za wybrany miesiąc.

     Ekran jest po to, żeby wysłać partnerowi zestawienie i przelew. Dlatego
     kwota „należne" stoi obok kwoty „w tym niezapłacone": to druga liczba
     decyduje, czy właściciel płaci teraz, czy czeka na przelewy klientów. --}}
<x-layouts.panel title="Rozliczenia">
    <x-slot:heading>Rozliczenia z partnerami</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8 space-y-6">

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-semibold text-stone-900">{{ $from->translatedFormat('LLLL Y') }}</h2>
                        <p class="mt-1 text-sm text-stone-500">
                            Zamówienia z tego miesiąca poza anulowanymi, po odjęciu zwrotów. Kwoty brutto.
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <form method="GET" action="{{ route('seller.settlements.index') }}">
                            <select name="miesiac" onchange="this.form.submit()"
                                class="rounded-2xl border border-stone-200 bg-white px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @foreach ($months as $option)
                                    <option value="{{ $option['value'] }}" @selected($option['value'] === $month)>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </form>

                        <a href="{{ route('seller.settlements.download', ['miesiac' => $month]) }}"
                            class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            Pobierz arkusz
                        </a>
                    </div>
                </div>

                @if ($summary->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🧾</span>
                        <p class="mt-4 font-medium text-stone-700">W tym miesiącu nic się nie należy</p>
                        <p class="mt-1 text-sm text-stone-500">
                            Żadne sprzedane produkty nie miały opłaty licencyjnej — albo miesiąc jest jeszcze pusty.
                        </p>
                    </div>
                @else
                    <div class="mt-6 space-y-2">
                        @foreach ($summary as $row)
                            <div class="rounded-2xl border border-stone-200 bg-white/80 px-4 py-3.5 shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-stone-900">{{ $row->name }}</p>
                                        <p class="mt-0.5 text-sm text-stone-500">
                                            {{ $row->orders }} {{ $row->orders === 1 ? 'zamówienie' : 'zamówień' }}
                                            · {{ rtrim(rtrim(number_format($row->quantity, 2, ',', ' '), '0'), ',') }} szt.
                                        </p>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <p class="text-lg font-bold text-stone-900">{{ \App\Support\Money::pln($row->amount) }}</p>
                                        @if ($row->unpaid > 0)
                                            {{-- Sprzedaż jest, pieniędzy jeszcze nie ma. Nie decydujemy
                                                 za właściciela, czy płacić partnerowi z góry — mówimy,
                                                 ile z tej kwoty jeszcze nie wpłynęło. --}}
                                            <p class="mt-0.5 text-xs text-amber-700">
                                                w tym {{ \App\Support\Money::pln($row->unpaid) }} z zamówień nieopłaconych
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @php($razem = $summary->sum('amount'))
                    @php($nieoplacone = $summary->sum('unpaid'))
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-stone-200 pt-4">
                        <span class="text-sm font-medium text-stone-700">Razem do wypłaty</span>
                        <span class="text-right">
                            <span class="text-xl font-bold text-stone-900">{{ \App\Support\Money::pln($razem) }}</span>
                            @if ($nieoplacone > 0)
                                <span class="mt-0.5 block text-xs text-amber-700">w tym {{ \App\Support\Money::pln($nieoplacone) }} jeszcze nieopłacone</span>
                            @endif
                        </span>
                    </div>
                @endif
            </div>

            {{-- Co się na to złożyło. Bez tej listy sprzedawca ma kwotę, której
                 nie umie obronić, gdy partner o nią zapyta. --}}
            @if ($rows->isNotEmpty())
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Pozycje</h2>
                    <p class="mt-1 text-sm text-stone-500">Każda opłata licencyjna osobno — to samo, co w arkuszu.</p>

                    <div class="mt-5 overflow-x-auto">
                        {{-- Szerokość minimalna inline: klasy dowolnej `min-w-[46rem]` nie ma
                                 w zbudowanym arkuszu, a Tailwind nie dogeneruje jej bez builda. --}}
                        <table class="w-full text-left text-sm" style="min-width:46rem">
                            <thead class="text-xs uppercase tracking-wide text-stone-400">
                                <tr>
                                    <th class="pb-2 pr-3 font-medium">Data</th>
                                    <th class="pb-2 pr-3 font-medium">Zamówienie</th>
                                    <th class="pb-2 pr-3 font-medium">Partner</th>
                                    <th class="pb-2 pr-3 font-medium">Za co</th>
                                    <th class="pb-2 pr-3 text-right font-medium">Szt.</th>
                                    <th class="pb-2 pr-3 text-right font-medium">Stawka</th>
                                    <th class="pb-2 text-right font-medium">Razem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($rows as $row)
                                    <tr class="text-stone-700">
                                        <td class="py-2 pr-3 whitespace-nowrap">{{ $row->date->format('d.m.Y') }}</td>
                                        <td class="py-2 pr-3 whitespace-nowrap">
                                            {{ $row->order_number }}
                                            @unless ($row->paid)
                                                <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">nieopłacone</span>
                                            @endunless
                                        </td>
                                        <td class="py-2 pr-3">{{ $row->name }}</td>
                                        <td class="py-2 pr-3">
                                            <span class="block">{{ $row->label }}</span>
                                            <span class="block text-xs text-stone-400">{{ $row->product }}</span>
                                        </td>
                                        <td class="py-2 pr-3 text-right whitespace-nowrap">{{ rtrim(rtrim(number_format($row->quantity, 2, ',', ' '), '0'), ',') }}</td>
                                        <td class="py-2 pr-3 text-right whitespace-nowrap">{{ \App\Support\Money::pln($row->unit_amount) }}</td>
                                        <td class="py-2 text-right font-medium whitespace-nowrap">{{ \App\Support\Money::pln($row->amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to liczymy</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">📅</span>
                        <span>Po dacie <span class="font-medium text-stone-700">złożenia zamówienia</span> — tej samej, po której oglądasz sprzedaż w Analityce.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🚫</span>
                        <span>Anulowane zamówienia <span class="font-medium text-stone-700">nie wchodzą</span> wcale.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">↩️</span>
                        <span>Zwrot <span class="font-medium text-stone-700">odejmuje</span> — oddany magnes nie generuje opłaty.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">⏳</span>
                        <span>Zamówienia bez zapłaty <span class="font-medium text-stone-700">są w kwocie</span>, ale wykazane osobno — decyzja, czy płacić z góry, należy do Ciebie.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🏷️</span>
                        <span>Nazwa partnera jest <span class="font-medium text-stone-700">z chwili sprzedaży</span>. Rozliczenie za marzec pokazuje, komu należało się w marcu.</span>
                    </li>
                </ul>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Arkusz</h2>
                <p class="mt-3 text-sm text-stone-500">
                    Plik <span class="font-medium text-stone-700">.xlsx</span> z dwoma arkuszami: „Podsumowanie" do przelewu
                    i „Pozycje" do wysłania partnerowi, gdy zapyta, skąd ta kwota.
                </p>
            </div>
        </aside>
    </div>
</x-layouts.panel>
