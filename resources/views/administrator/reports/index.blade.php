<x-layouts.panel title="Zgłoszenia">
    <x-slot:actions>
        <span class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur">
            {{ $counts['pending'] }} do rozpatrzenia
        </span>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            @if ($reports->isEmpty())
                <div class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-stone-300 bg-white/40 px-6 py-16 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🚩</span>
                    @if ($filters['stan'] !== '' || $filters['szukaj'] !== '')
                        <p class="mt-4 font-medium text-stone-700">Nic nie pasuje do tych filtrów</p>
                        <p class="mt-1 max-w-sm text-sm text-stone-500">
                            Zmień kryteria albo <a href="{{ route('administrator.reports.index') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">wyczyść filtry</a>.
                        </p>
                    @else
                        <p class="mt-4 font-medium text-stone-700">Nie ma żadnych zgłoszeń</p>
                        <p class="mt-1 max-w-sm text-sm text-stone-500">
                            To dobra wiadomość. Zgłoszenia trafiają tutaj z formularza w stopce każdego sklepu.
                        </p>
                    @endif
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($reports as $report)
                        <a href="{{ route('administrator.reports.show', $report) }}"
                            class="block rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur transition hover:border-stone-300">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="font-mono text-xs font-semibold text-stone-500">{{ $report->reference() }}</span>
                                @php([$badgeBg, $badgeText] = $report->status->badgeClasses())
                                <span class="rounded-full {{ $badgeBg }} px-3 py-1 text-xs font-semibold {{ $badgeText }}">{{ $report->status->label() }}</span>
                                <span class="text-sm font-medium text-stone-800">{{ $report->category->label() }}</span>
                                <span class="text-xs text-stone-400">{{ $report->created_at->format('d.m.Y H:i') }}</span>
                            </div>

                            <p class="mt-2 truncate text-sm text-stone-600">{{ $report->url }}</p>

                            <p class="mt-1 text-xs text-stone-400">
                                @if ($report->shop)
                                    Sklep: <span class="font-medium text-stone-600">{{ $report->shop->name }}</span>
                                @else
                                    {{-- Adres spoza Kramio albo sklep już usunięty — zgłoszenie zostaje w rejestrze. --}}
                                    Adres nie wskazuje na żaden sklep w Kramio
                                @endif
                                · zgłasza {{ $report->reporter_email }}
                            </p>
                        </a>
                    @endforeach
                </div>

                <div class="mt-6">{{ $reports->links() }}</div>
            @endif
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <form method="GET" action="{{ route('administrator.reports.index') }}" class="space-y-4 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Filtry</h2>

                <div>
                    <label for="szukaj" class="block text-sm font-medium text-stone-700">Szukaj</label>
                    <input type="search" id="szukaj" name="szukaj" value="{{ $filters['szukaj'] }}" placeholder="Numer sprawy, adres, sklep, zgłaszający"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>

                <div>
                    <label for="stan" class="block text-sm font-medium text-stone-700">Stan</label>
                    <select id="stan" name="stan"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <option value="">Wszystkie</option>
                        @foreach ($statuses as $case)
                            <option value="{{ $case->value }}" @selected($filters['stan'] === $case->value)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">Filtruj</button>
                    @if ($filters['stan'] !== '' || $filters['szukaj'] !== '')
                        <a href="{{ route('administrator.reports.index') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">Wyczyść</a>
                    @endif
                </div>
            </form>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Zgłoszenia treści</h2>
                <p class="mt-2 text-sm text-stone-500">
                    Publiczny mechanizm z <span class="font-medium text-stone-700">art. 16 DSA</span>. Każdy może zgłosić treść w cudzym sklepie
                    z formularza w stopce — bez logowania. Zgłaszający dostaje potwierdzenie od razu, a rozstrzygnięcie osobnym pismem.
                </p>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">⏳</span>
                        <span>Nierozpatrzone są zawsze <span class="text-stone-700">na górze listy</span> — od chwili zgłoszenia mamy wiedzę o treści, więc zwłoka ma cenę.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-stone-400">✍️</span>
                        <span>Uzasadnienie jest <span class="text-stone-700">obowiązkowe także przy odrzuceniu</span> i idzie do zgłaszającego jako pismo.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-rose-500">🔒</span>
                        <span>Rozstrzygnięcia <span class="text-stone-700">nie da się cofnąć</span> — po nim wychodzą maile na zewnątrz.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-stone-400">🛍️</span>
                        <span>Akcje na sklepie (ukrycie, zawieszenie) robisz w dziale <span class="text-stone-700">„Sklepy"</span> — tutaj zapada sama decyzja.</span>
                    </li>
                </ul>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">W rejestrze</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-stone-500">Wszystkie zgłoszenia</dt>
                        <dd class="font-semibold tabular-nums text-stone-900">{{ $counts['all'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-stone-500">Czekają na decyzję</dt>
                        <dd class="font-semibold tabular-nums text-stone-900">{{ $counts['pending'] }}</dd>
                    </div>
                </dl>
            </div>
        </aside>
    </div>
</x-layouts.panel>
