<x-layouts.panel title="Personalizacja">
    <x-slot:heading>Personalizacja</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-900">Grupy opcji</h2>
                        <p class="mt-1 text-sm text-stone-500">Bloki pytań, które klient wypełnia przy zakupie. Definiujesz raz, przypinasz do wielu produktów.</p>
                    </div>
                    <a href="{{ route('seller.options.create') }}"
                        class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                        Dodaj grupę
                    </a>
                </div>

                @if ($groups->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">✍️</span>
                        <p class="mt-4 font-medium text-stone-700">Nie masz jeszcze żadnej grupy</p>
                        <p class="mt-1 max-w-md text-sm text-stone-500">
                            Grupa to jedno pytanie do kupującego: „wpisz imię" albo „wybierz grafikę do wygrawerowania".
                            Bez niej produkt sprzedaje się taki, jaki jest.
                        </p>
                    </div>
                @else
                    <div class="mt-6 space-y-2">
                        @foreach ($groups as $group)
                            <div class="rounded-2xl border border-stone-200 bg-white/80 px-4 py-3.5 shadow-sm transition hover:border-amber-300">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <a href="{{ route('seller.options.edit', $group) }}"
                                            class="font-semibold text-stone-900 transition hover:text-amber-700">{{ $group->name }}</a>
                                        <p class="mt-0.5 truncate text-sm text-stone-600">
                                            {{ $group->kind->label() }}
                                            @if ($group->isText())
                                                · {{ trans_choice('{0}brak pól|{1}:count pole|[2,4]:count pola|[5,*]:count pól', $group->fields_count, ['count' => $group->fields_count]) }}
                                            @else
                                                · {{ trans_choice('{0}pusta biblioteka|{1}:count pozycja|[2,4]:count pozycje|[5,*]:count pozycji', $group->choices_count, ['count' => $group->choices_count]) }}
                                            @endif
                                        </p>
                                        <p class="text-xs text-stone-400">
                                            @if ($group->excludes)
                                                Wyklucza się z „{{ $group->excludes->name }}"
                                            @else
                                                Bez wykluczeń
                                            @endif
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 flex-col items-end gap-1.5 text-right">
                                        @if ((float) $group->surcharge_gross > 0)
                                            <span class="font-semibold text-stone-900">+{{ \App\Support\Money::pln($group->surcharge_gross) }}</span>
                                        @else
                                            <span class="text-sm text-stone-400">bez dopłaty</span>
                                        @endif
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $group->required ? 'bg-amber-50 text-amber-700' : 'bg-stone-100 text-stone-500' }}">
                                            {{ $group->required ? 'Obowiązkowa' : 'Nieobowiązkowa' }}
                                        </span>
                                        <span class="text-xs text-stone-400">Produktów: {{ $group->products_count }}</span>
                                    </div>
                                </div>

                                {{-- Grupa bez zawartości to PUSTE PYTANIE: w kasie wygląda
                                     jak usterka, a przy „obowiązkowa" blokuje zakup zupełnie. --}}
                                @if (($group->isText() && $group->fields_count === 0) || (! $group->isText() && $group->choices_count === 0))
                                    <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                        Ta grupa jest pusta — dopóki nie dodasz {{ $group->isText() ? 'pól' : 'pozycji' }}, nie ma o co pytać klienta.
                                    </p>
                                @endif

                                <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-stone-100 pt-3">
                                    <a href="{{ route('seller.options.edit', $group) }}"
                                        class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100">Edytuj</a>

                                    {{-- „Usuń" tylko tam, gdzie zadziała: grupa przypięta do
                                         produktów zabrałaby im personalizację bez ostrzeżenia. --}}
                                    @if ($group->products_count === 0)
                                        <form method="POST" action="{{ route('seller.options.destroy', $group) }}"
                                            onsubmit="return confirm('Usunąć grupę „{{ $group->name }}" razem z jej zawartością?')">
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
                <h2 class="font-semibold text-stone-900">Dwa rodzaje grup</h2>
                <ul class="mt-4 space-y-4 text-sm text-stone-500">
                    <li>
                        <span class="font-medium text-stone-700">Pola do wpisania</span>
                        <p class="mt-0.5">Klient wpisuje własny tekst w przygotowane pola, każde z limitem znaków — imię na kubku, data na magnesie.</p>
                    </li>
                    <li>
                        <span class="font-medium text-stone-700">Wybór z biblioteki</span>
                        <p class="mt-0.5">Klient wskazuje jedną pozycję z Twojej listy — z podglądem, dopłatą i ewentualną opłatą licencyjną.</p>
                    </li>
                </ul>
                <p class="mt-4 text-xs leading-relaxed text-stone-500">
                    Rodzaju <span class="text-stone-700">nie da się zmienić po utworzeniu</span> — zmiana osierociłaby pola
                    albo pozycje biblioteki, a produkty z tą grupą przestałyby dać się kupić.
                </p>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Dopłata</h2>
                <p class="mt-2 text-sm leading-relaxed text-stone-500">
                    To koszt <span class="text-stone-700">wykonania</span> — np. graweru. Doliczany raz, niezależnie od tego,
                    co klient wpisze lub wybierze.
                </p>
                <p class="mt-3 text-sm leading-relaxed text-stone-500">
                    Zostaw <span class="font-medium text-stone-700">zero</span>, jeśli koszt personalizacji jest już wliczony
                    w cenę produktu — wtedy nie pokaże się w rozbiciu ceny.
                </p>
            </div>
        </aside>
    </div>
</x-layouts.panel>
