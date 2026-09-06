<x-layouts.panel title="Partnerzy licencyjni">
    <x-slot:heading>Partnerzy licencyjni</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: kartoteka. Układ jak przy kodach rabatowych —
             jedna karta, nagłówek z opisem i przyciskiem w jednym wierszu,
             lista pod spodem. To standard tego panelu. --}}
        <div class="lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-900">Twoi partnerzy</h2>
                        <p class="mt-1 text-sm text-stone-500">Firmy, których znak umieszczasz na produktach — i które biorą za to opłatę.</p>
                    </div>
                    <a href="{{ route('seller.licensors.create') }}"
                        class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                        Dodaj partnera
                    </a>
                </div>

                @if ($licensors->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🤝</span>
                        <p class="mt-4 font-medium text-stone-700">Kartoteka jest pusta</p>
                        <p class="mt-1 text-sm text-stone-500">
                            Dodaj firmę, która udziela Ci prawa do swojego logotypu — organizatora biegu, klub, wydawcę.
                            Dopiero wtedy przypiszesz jej opłatę przy produkcie albo przy grafice graweru.
                        </p>
                    </div>
                @else
                    <div class="mt-6 space-y-2">
                        @foreach ($licensors as $licensor)
                            <div class="rounded-2xl border border-stone-200 bg-white/80 px-4 py-3.5 shadow-sm transition hover:border-amber-300">
                                <div class="flex items-start justify-between gap-4">
                                    {{-- Lewa: kto to jest i na jakiej podstawie --}}
                                    <div class="min-w-0">
                                        <a href="{{ route('seller.licensors.edit', $licensor) }}"
                                            class="font-semibold text-stone-900 transition hover:text-amber-700">{{ $licensor->name }}</a>
                                        <p class="mt-0.5 truncate text-sm text-stone-600">
                                            {{ $licensor->contact_person ?: 'bez osoby kontaktowej' }}
                                            @if ($licensor->contact_email)
                                                · {{ $licensor->contact_email }}
                                            @endif
                                        </p>
                                        <p class="text-xs text-stone-400">
                                            @if ($licensor->agreement_reference)
                                                Umowa {{ $licensor->agreement_reference }}
                                            @else
                                                Bez numeru umowy
                                            @endif
                                        </p>
                                    </div>

                                    {{-- Prawa: stan i ile na nim wisi. Te liczby mówią, czy
                                         partnera wolno skasować — bez nich sprzedawca klika
                                         „Usuń", dostaje odmowę i nie wie dlaczego. --}}
                                    <div class="flex shrink-0 flex-col items-end gap-1.5 text-right">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $licensor->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                            {{ $licensor->is_active ? 'Aktywny' : 'Wygaszony' }}
                                        </span>
                                        <span class="text-xs text-stone-400">Grafik: {{ $licensor->choices_count }}</span>
                                        <span class="text-xs text-stone-400">Sprzedano: {{ $licensor->components_count }}</span>
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-stone-100 pt-3">
                                    <a href="{{ route('seller.licensors.edit', $licensor) }}"
                                        class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100">Edytuj</a>

                                    {{-- Strona partnera jest PUBLICZNA — sprzedawca musi
                                         o tym wiedzieć, zanim dopisze do kartoteki firmę,
                                         z którą dopiero negocjuje. Wygaszony partner strony
                                         nie ma, więc i odnośnika nie pokazujemy. --}}
                                    @if ($licensor->is_active)
                                        <a href="{{ 'https://'.$shop->host().$licensor->storefrontPath() }}" target="_blank" rel="noopener"
                                            class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100">Strona partnera ↗</a>
                                    @endif

                                    <form method="POST" action="{{ route('seller.licensors.toggle', $licensor) }}">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100">
                                            {{ $licensor->is_active ? 'Wygaś' : 'Przywróć' }}
                                        </button>
                                    </form>

                                    {{-- Przycisk pokazuje się TYLKO tam, gdzie zadziała.
                                         Partner, na którego poszła sprzedaż, jest gaszony,
                                         nie kasowany — inaczej rozliczenie sprzed roku
                                         zostaje bez adresata. --}}
                                    @if ($licensor->choices_count === 0 && $licensor->components_count === 0)
                                        <form method="POST" action="{{ route('seller.licensors.destroy', $licensor) }}"
                                            onsubmit="return confirm('Usunąć {{ $licensor->name }} z kartoteki?')">
                                            @csrf
                                            <button type="submit"
                                                class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-rose-600 transition hover:bg-rose-50">Usuń</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Po co jest ta kartoteka</h2>
                <p class="mt-2 text-sm leading-relaxed text-stone-500">
                    Partner to firma, która pozwala Ci umieścić swój znak na produkcie i bierze za to opłatę.
                    Opłata doliczana jest do ceny i jest <span class="text-stone-700">widoczna dla kupującego</span>
                    jako osobna pozycja.
                </p>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">➕</span>
                        <span>Kwotę ustawiasz w <span class="text-stone-700">dwóch miejscach</span>: przy produkcie (logotyp na awersie) i przy grafice graweru. Tutaj definiujesz tylko, kto ją dostaje.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🧮</span>
                        <span>Dwie opłaty <span class="text-stone-700">tej samej firmy</span> na jednym produkcie nie sumują się — liczy się wyższa. Opłaty różnych firm sumują się normalnie.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-rose-500">🔒</span>
                        <span>Partnera, na którego poszła sprzedaż, <span class="text-stone-700">wygasza się, a nie kasuje</span> — inaczej rozliczenie sprzed roku zostaje bez adresata.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
