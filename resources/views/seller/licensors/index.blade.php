<x-layouts.panel title="Partnerzy licencyjni">
    <x-slot:actions>
        <a href="{{ route('seller.licensors.create') }}"
            class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
            Dodaj partnera
        </a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            @if ($licensors->isEmpty())
                <div class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-stone-300 bg-white/40 px-6 py-16 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🤝</span>
                    <p class="mt-4 font-medium text-stone-700">Kartoteka jest pusta</p>
                    <p class="mt-1 max-w-sm text-sm text-stone-500">
                        Dodaj firmę, która udziela Ci prawa do swojego logotypu — organizatora biegu, klub, wydawcę.
                        Dopiero wtedy przypiszesz jej opłatę przy produkcie albo przy grafice graweru.
                    </p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($licensors as $licensor)
                        <div class="rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur">
                            <div class="flex flex-wrap items-center gap-3">
                                <a href="{{ route('seller.licensors.edit', $licensor) }}"
                                    class="font-medium text-stone-800 hover:underline">{{ $licensor->name }}</a>
                                @unless ($licensor->is_active)
                                    <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-500">Wygaszony</span>
                                @endunless
                            </div>

                            <p class="mt-1 text-xs text-stone-400">
                                @if ($licensor->agreement_reference)
                                    Umowa {{ $licensor->agreement_reference }} ·
                                @endif
                                {{ $licensor->contact_email ?: 'bez kontaktu' }}
                            </p>

                            {{-- Liczby mówią, czy partnera wolno skasować. Bez nich
                                 sprzedawca klika „Usuń", dostaje odmowę i nie wie
                                 dlaczego. --}}
                            <p class="mt-2 text-xs text-stone-500">
                                Grafik w bibliotece: <span class="font-medium tabular-nums text-stone-700">{{ $licensor->choices_count }}</span>
                                · Sprzedanych pozycji z jego znakiem: <span class="font-medium tabular-nums text-stone-700">{{ $licensor->components_count }}</span>
                            </p>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('seller.licensors.edit', $licensor) }}"
                                    class="rounded-full border border-stone-200 bg-white px-4 py-1.5 text-sm font-medium text-stone-600 transition hover:bg-stone-50">Edytuj</a>

                                <form method="POST" action="{{ route('seller.licensors.toggle', $licensor) }}">
                                    @csrf
                                    <button type="submit"
                                        class="rounded-full border border-stone-200 bg-white px-4 py-1.5 text-sm font-medium text-stone-600 transition hover:bg-stone-50">
                                        {{ $licensor->is_active ? 'Wygaś' : 'Przywróć' }}
                                    </button>
                                </form>

                                @if ($licensor->choices_count === 0 && $licensor->components_count === 0)
                                    <form method="POST" action="{{ route('seller.licensors.destroy', $licensor) }}"
                                        onsubmit="return confirm('Usunąć {{ $licensor->name }} z kartoteki?')">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-full border border-stone-200 bg-white px-4 py-1.5 text-sm font-medium text-rose-600 transition hover:bg-rose-50">Usuń</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Po co jest ta kartoteka</h2>
                <p class="mt-2 text-sm leading-relaxed text-stone-500">
                    Partner to firma, która pozwala Ci umieścić swój znak na produkcie i bierze za to opłatę.
                    Ta opłata doliczana jest do ceny i jest <span class="text-stone-700">widoczna dla kupującego</span>
                    jako osobna pozycja.
                </p>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">➕</span>
                        <span>Opłatę przypisujesz w <span class="text-stone-700">dwóch miejscach</span>: przy produkcie (logotyp na awersie) i przy grafice graweru.</span>
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
