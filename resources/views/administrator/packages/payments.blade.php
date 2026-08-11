<x-layouts.panel title="Rejestr opłat">
    {{-- W nagłówku TYLKO powrót — tak samo jak na karcie sprzedawcy i w edycji
         sklepu. Przycisk akcji przeniesiony do kolumny bocznej: gradientowy CTA
         w pasku górnym nie występuje nigdzie indziej w panelu. --}}
    <x-slot:actions>
        <a href="{{ route('administrator.packages.index') }}"
            class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur transition hover:bg-white">
            ← Pakiety
        </a>
    </x-slot:actions>

    @php($hasFilters = $filters['q'] !== '' || $filters['status'] !== '' || $filters['package'] !== '')

    {{-- Ten sam podział 8/4 co w Pakietach i u Sprzedawców: po lewej dane,
         po prawej filtry i objaśnienia. --}}
    <div class="grid gap-6 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-8">
            <div class="grid gap-4 sm:grid-cols-2">
                {{-- Suma liczona z TYCH SAMYCH filtrów co lista i tylko z opłaconych:
                     dorzucenie wiszących kazałoby czytać ją jako pieniądze, którymi
                     nie są. Przy pustych filtrach równa się przychodowi z Pakietów. --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-stone-500">Opłacone</p>
                        <span class="text-lg">💰</span>
                    </div>
                    <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-stone-900">{{ \App\Support\Money::pln($sum) }}</p>
                    <p class="mt-1 text-xs text-stone-400">{{ $hasFilters ? 'Wynik bieżących filtrów' : 'Wszystko, co wpłynęło' }}</p>
                </div>

                <div class="rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-stone-500">Opłat na liście</p>
                        <span class="text-lg">🧾</span>
                    </div>
                    <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-stone-900">{{ $payments->total() }}</p>
                    <p class="mt-1 text-xs text-stone-400">Razem z wiszącymi i nieudanymi</p>
                </div>
            </div>

            @if ($payments->isEmpty())
                <div class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-stone-300 bg-white/40 px-6 py-16 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-100 text-2xl">💰</span>
                    @if ($hasFilters)
                        <p class="mt-4 font-medium text-stone-700">Żadna opłata nie pasuje do tych filtrów</p>
                        <p class="mt-1 max-w-sm text-sm text-stone-500">Zmień kryteria albo <a href="{{ route('administrator.packages.payments') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">wyczyść filtry</a>.</p>
                    @else
                        <p class="mt-4 font-medium text-stone-700">Rejestr opłat jest pusty</p>
                        <p class="mt-1 max-w-md text-sm text-stone-500">
                            Trafi tu każda opłata za pakiet: kupiona przez bramkę i wpisana ręcznie.
                            Sprzedaż z ręki nie zapisze się sama — użyj „Zarejestruj wpłatę".
                        </p>
                    @endif
                </div>
            @else
                <div class="overflow-hidden rounded-3xl border border-white/60 bg-white/70 backdrop-blur">
                    <div class="overflow-x-auto">
                        {{-- Sześć kolumn, nie osiem: w kolumnie 8/12 termin zmieścił się
                             pod pakietem, a sposób wpłaty pod datą. Osiem osobnych kolumn
                             znaczyło minimum 64rem, czyli poziomy pasek na każdym ekranie. --}}
                        <table class="w-full text-left text-sm" style="min-width: 42rem">
                            <thead class="border-b border-stone-200/70 text-xs uppercase tracking-wide text-stone-400">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Sklep</th>
                                    <th class="px-5 py-3 font-medium">Pakiet</th>
                                    <th class="px-5 py-3 text-right font-medium">Kwota</th>
                                    <th class="px-5 py-3 font-medium">Wpłata</th>
                                    <th class="px-5 py-3 font-medium">Faktura</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($payments as $payment)
                                    <tr class="transition hover:bg-white/60">
                                        <td class="px-5 py-3">
                                            @if ($payment->shop)
                                                <a href="{{ route('administrator.shops.edit', $payment->shop) }}"
                                                    class="font-medium text-stone-900 underline decoration-amber-300 underline-offset-2">{{ $payment->shop->name }}</a>
                                                <p class="text-xs text-stone-400">{{ $payment->shop->slug }}</p>
                                            @else
                                                {{-- Sklep skasowany, opłata zostaje: dokument księgowy nie
                                                     znika razem z klientem. --}}
                                                <span class="text-xs text-stone-400">sklep usunięty</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-stone-700">
                                            {{ config("shop.packages.{$payment->target_package}.name", $payment->target_package) }}
                                            <p class="text-xs tabular-nums text-stone-400">do {{ $payment->new_ends_at->format('d.m.Y') }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-right tabular-nums font-medium text-stone-900">
                                            {{ \App\Support\Money::pln($payment->amount) }}
                                            @if ((float) $payment->credit > 0)
                                                <p class="text-xs font-normal text-stone-400">ze zniżką</p>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="block tabular-nums text-stone-600">{{ $payment->paid_at?->format('d.m.Y') ?? '—' }}</span>
                                            <span @class([
                                                'mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-stone-100 text-stone-600' => ! $payment->isManual(),
                                                'bg-amber-50 text-amber-700' => $payment->isManual(),
                                            ])>{{ $payment->methodLabel() }}</span>
                                            @if ($payment->recordedBy)
                                                <p class="mt-0.5 text-xs text-stone-400">wpisał: {{ $payment->recordedBy->name }}</p>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            @if ($payment->invoicePdfUrl())
                                                <a href="{{ $payment->invoicePdfUrl() }}" target="_blank" rel="noopener"
                                                    class="text-stone-700 underline decoration-amber-300 underline-offset-2">
                                                    {{ $payment->invoice_number ?: 'PDF' }}
                                                </a>
                                            @elseif (filled($payment->invoice_number))
                                                {{-- Numer bez tokenu = dokument wystawiony poza systemem. --}}
                                                <span class="text-stone-600">{{ $payment->invoice_number }}</span>
                                            @elseif ($payment->status === \App\Models\PackagePayment::STATUS_PAID)
                                                <span class="text-xs text-amber-700">brak</span>
                                            @else
                                                <span class="text-stone-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <span @class([
                                                'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                                                'bg-emerald-100 text-emerald-700' => $payment->status === \App\Models\PackagePayment::STATUS_PAID,
                                                'bg-amber-50 text-amber-700' => $payment->status === \App\Models\PackagePayment::STATUS_PENDING,
                                                'bg-rose-50 text-rose-700' => ! in_array($payment->status, [\App\Models\PackagePayment::STATUS_PAID, \App\Models\PackagePayment::STATUS_PENDING], true),
                                            ])>
                                                @switch($payment->status)
                                                    @case(\App\Models\PackagePayment::STATUS_PAID) opłacone @break
                                                    @case(\App\Models\PackagePayment::STATUS_PENDING) w toku @break
                                                    @default nieudane
                                                @endswitch
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>{{ $payments->links() }}</div>
            @endif
        </div>

        <aside class="space-y-6 lg:col-span-4">
            <a href="{{ route('administrator.packages.payments.create') }}"
                class="block rounded-3xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-3 text-center text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                Zarejestruj wpłatę
            </a>

            <form method="GET" action="{{ route('administrator.packages.payments') }}" class="space-y-4 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Filtry</h2>

                <div>
                    <label for="szukaj" class="block text-sm font-medium text-stone-700">Szukaj</label>
                    <input type="search" name="szukaj" id="szukaj" value="{{ $filters['q'] }}" placeholder="Nazwa lub adres sklepu"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-stone-700">Status</label>
                    <select name="status" id="status"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <option value="">Wszystkie</option>
                        <option value="paid" @selected($filters['status'] === 'paid')>Opłacone</option>
                        <option value="pending" @selected($filters['status'] === 'pending')>W toku</option>
                        <option value="failed" @selected($filters['status'] === 'failed')>Nieudane</option>
                    </select>
                </div>

                <div>
                    <label for="pakiet" class="block text-sm font-medium text-stone-700">Pakiet</label>
                    <select name="pakiet" id="pakiet"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <option value="">Wszystkie</option>
                        @foreach ($packages as $slug => $package)
                            <option value="{{ $slug }}" @selected($filters['package'] === $slug)>{{ $package['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">Filtruj</button>
                    @if ($hasFilters)
                        <a href="{{ route('administrator.packages.payments') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">Wyczyść</a>
                    @endif
                </div>
            </form>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to czytać</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">💰</span>
                        <span>Kafelek <span class="text-stone-700">„Opłacone"</span> sumuje wyłącznie wpłaty ze statusem opłacone i zawsze po bieżących filtrach — więc „pokaż Pawilon" od razu mówi, ile Pawilon przyniósł.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🧾</span>
                        <span><span class="text-stone-700">Przelew</span> i <span class="text-stone-700">Gotówka</span> to wpłaty wpisane ręcznie; <span class="text-stone-700">Paynow</span> przyszedł z bramki. Liczą się dokładnie tak samo.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">📄</span>
                        <span><span class="text-stone-700">„brak"</span> w kolumnie Faktura znaczy, że pieniądze wpłynęły, a dokumentu nie ma. Te same wpłaty zbiera „Wymaga uwagi" w <a href="{{ route('administrator.packages.index') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">Pakietach</a>.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
