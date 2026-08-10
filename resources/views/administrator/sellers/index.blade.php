<x-layouts.panel title="Sprzedawcy">
    <x-slot:actions>
        <span class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur">
            {{ $sellers->total() }} {{ trans_choice('konto|konta|kont', $sellers->total()) }}
        </span>
    </x-slot:actions>

    {{-- Kafelki liczone po filtrach. Przy pustych filtrach to obraz całej
         platformy — w tym „Zgoda na oferty”, czyli jedyna liczba mówiąca,
         do ilu sprzedawców wolno legalnie napisać. --}}
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Sprzedawcy', $summary['sellers'], $filtered ? 'Wynik bieżących filtrów' : 'Wszystkie konta na platformie', '👥'],
            ['Aktywowani', $summary['activated'], 'Ustawili hasło i potwierdzili adres', '✅'],
            ['Zgoda na oferty', $summary['consented'], 'Tylu wolno wysłać treści handlowe', '📣'],
        ] as [$label, $value, $hint, $icon])
            <div class="rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-stone-500">{{ $label }}</p>
                    <span class="text-lg">{{ $icon }}</span>
                </div>
                <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums text-stone-900">{{ $value }}</p>
                <p class="mt-1 text-xs text-stone-400">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            @if ($sellers->isEmpty())
                <div class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-stone-300 bg-white/40 px-6 py-16 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-100 text-2xl">👥</span>
                    @if ($filtered)
                        <p class="mt-4 font-medium text-stone-700">Nikt nie pasuje do tych filtrów</p>
                        <p class="mt-1 max-w-sm text-sm text-stone-500">Zmień kryteria albo <a href="{{ route('administrator.sellers.index') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">wyczyść filtry</a>.</p>
                    @else
                        <p class="mt-4 font-medium text-stone-700">Nie ma jeszcze żadnych sprzedawców</p>
                        <p class="mt-1 max-w-sm text-sm text-stone-500">Konta pojawią się tutaj zaraz po rejestracji — jeszcze przed aktywacją, żeby było widać, kto utknął na ustawianiu hasła.</p>
                    @endif
                </div>
            @else
                <div class="overflow-hidden rounded-3xl border border-white/60 bg-white/70 backdrop-blur">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm" style="min-width: 62rem">
                            <thead class="border-b border-stone-200/70 text-xs uppercase tracking-wide text-stone-400">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Sprzedawca</th>
                                    <th class="px-5 py-3 font-medium">Sklep</th>
                                    <th class="px-5 py-3 font-medium">Pakiet</th>
                                    <th class="px-5 py-3 font-medium">Rejestracja</th>
                                    <th class="px-5 py-3 font-medium">Ostatnie logowanie</th>
                                    <th class="px-5 py-3 font-medium">Oferty</th>
                                    <th class="px-5 py-3"><span class="sr-only">Akcje</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($sellers as $seller)
                                    <tr class="transition hover:bg-white/60">
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-2">
                                                <p class="font-medium text-stone-900">{{ trim($seller->name.' '.$seller->surname) }}</p>
                                                @unless ($seller->isActivated())
                                                    {{-- Konto po rejestracji, przed ustawieniem hasła. Nie umie się
                                                         zalogować, więc każda inna kolumna będzie przy nim pusta. --}}
                                                    <span class="inline-flex items-center whitespace-nowrap rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700"
                                                        title="Konto zarejestrowane, ale hasło nieustawione">czeka na aktywację</span>
                                                @endunless
                                            </div>
                                            <p class="text-xs text-stone-400">{{ $seller->email }}</p>
                                        </td>
                                        <td class="px-5 py-3">
                                            @if ($seller->shop)
                                                <p class="text-stone-700">{{ $seller->shop->name }}</p>
                                                <p class="text-xs text-stone-400">{{ $seller->shop->slug }}</p>
                                            @else
                                                {{-- Rejestracja zawsze zakłada sklep, więc puste pole to nie „jeszcze
                                                     nie zdążył", tylko anomalia warta obejrzenia. --}}
                                                <span class="text-xs text-rose-700">brak sklepu</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            @if ($seller->shop)
                                                <span class="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700">{{ $seller->shop->packageName() }}</span>
                                            @else
                                                <span class="text-stone-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap tabular-nums text-stone-600">
                                            {{ $seller->created_at?->format('d.m.Y') ?? '—' }}
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap tabular-nums text-stone-600">
                                            @if ($seller->last_login_at)
                                                {{ $seller->last_login_at->format('d.m.Y H:i') }}
                                            @else
                                                <span class="text-stone-400" title="Nie logował się, odkąd zaczęliśmy to zapisywać">—</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            @if ($seller->hasMarketingConsent())
                                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">zgoda</span>
                                            @else
                                                <span class="text-stone-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <a href="{{ route('administrator.sellers.show', $seller) }}"
                                                class="inline-flex items-center rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 shadow-sm transition hover:bg-stone-100">
                                                Karta
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($sellers->hasPages())
                    <div class="mt-6">{{ $sellers->links() }}</div>
                @endif
            @endif
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <form method="GET" action="{{ route('administrator.sellers.index') }}" class="space-y-4 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Filtry</h2>

                <div>
                    <label for="szukaj" class="block text-sm font-medium text-stone-700">Szukaj</label>
                    <input type="search" id="szukaj" name="szukaj" value="{{ $search }}" placeholder="Nazwisko, e-mail, telefon, sklep"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>

                <div>
                    <label for="aktywacja" class="block text-sm font-medium text-stone-700">Aktywacja</label>
                    <select id="aktywacja" name="aktywacja"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <option value="">Wszyscy</option>
                        <option value="1" @selected($filters['aktywacja'] === true)>Aktywowani</option>
                        <option value="0" @selected($filters['aktywacja'] === false)>Czekają na aktywację</option>
                    </select>
                </div>

                <div>
                    <label for="zgoda" class="block text-sm font-medium text-stone-700">Zgoda na oferty</label>
                    <select id="zgoda" name="zgoda"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <option value="">Wszyscy</option>
                        <option value="1" @selected($filters['zgoda'] === true)>Ze zgodą</option>
                        <option value="0" @selected($filters['zgoda'] === false)>Bez zgody</option>
                    </select>
                </div>

                <div>
                    <label for="sortuj" class="block text-sm font-medium text-stone-700">Sortuj</label>
                    <select id="sortuj" name="sortuj"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        @foreach ($sorts as $key => $label)
                            <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">Filtruj</button>
                    @if ($filtered)
                        <a href="{{ route('administrator.sellers.index') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">Wyczyść</a>
                    @endif
                </div>
            </form>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to czytać</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">📣</span>
                        <span><span class="text-stone-700">Zgoda na oferty</span> dotyczy WYŁĄCZNIE treści handlowych. Faktura, wygaśnięcie pakietu czy awaria idą do wszystkich i nigdy nie wolno ich nią blokować.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">⏳</span>
                        <span><span class="text-stone-700">Czeka na aktywację</span> = konto założone, ale hasło nieustawione. Na karcie takiego konta wyślesz link ponownie.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🕐</span>
                        <span><span class="text-stone-700">Ostatnie logowanie</span> zapisujemy od wdrożenia tego ekranu — pusto u starego konta znaczy „nie wchodził od tamtej pory”, nie „nigdy”.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
