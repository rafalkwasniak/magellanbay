{{-- KATALOG — jedna oś podziału na ekranie, przełączana zakładkami.

     Cała oś zapisuje się JEDNYM żądaniem. Geografia to kilkaset pozycji;
     ekran „edytuj → zapisz" przeklikany dwieście razy byłby karą, a nie
     narzędziem. Ostatni wiersz jest zawsze pusty — to nim dodaje się kolejną
     pozycję, bez osobnego przycisku i bez JavaScriptu. --}}
<x-layouts.panel title="Katalog">
    <x-slot:heading>Katalog</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8 space-y-6">

            {{-- Zakładki osi. Osie są niezależne: ten sam magnes stoi
                 jednocześnie w rodzaju, w tematyce i w geografii. --}}
            <div class="flex flex-wrap gap-2">
                @foreach ($axes as $tab)
                    <a href="{{ route('seller.categories.index', $tab->segment()) }}"
                        class="rounded-2xl border px-4 py-2 text-sm font-medium transition {{ $tab->key() === $axis->key()
                            ? 'border-amber-300 bg-amber-50 text-amber-800'
                            : 'border-stone-200 bg-white/70 text-stone-600 hover:border-amber-300 hover:text-amber-700' }}">
                        {{ $tab->labelPlural() }}
                    </a>
                @endforeach
            </div>

            <form method="POST" action="{{ route('seller.categories.save', $axis->segment()) }}"
                class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur" novalidate>
                @csrf

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-semibold text-stone-900">{{ $axis->labelPlural() }}</h2>
                        <p class="mt-1 text-sm text-stone-500">{{ $axis->hint() }}</p>
                    </div>
                    <button type="submit"
                        class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                        Zapisz podział
                    </button>
                </div>

                <div class="mt-6 space-y-2">
                    @forelse ($rows as $row)
                        @php($node = $row['category'])
                        <div class="rounded-2xl border border-stone-200 bg-white/80 p-4 shadow-sm"
                            style="margin-left: {{ $row['depth'] * 28 }}px">
                            <div class="grid gap-3 sm:grid-cols-12">
                                <div class="{{ $axis->hierarchical() ? 'sm:col-span-6' : 'sm:col-span-9' }}">
                                    <label class="block text-xs font-medium text-stone-500">Nazwa</label>
                                    <input type="text" name="items[{{ $node->id }}][name]"
                                        value="{{ old('items.'.$node->id.'.name', $node->name) }}"
                                        class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                    @error('items.'.$node->id.'.name')
                                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                @if ($axis->hierarchical())
                                    <div class="sm:col-span-3">
                                        <label class="block text-xs font-medium text-stone-500">Wewnątrz</label>
                                        <select name="items[{{ $node->id }}][parent_id]"
                                            class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                            <option value="">— najwyższy poziom —</option>
                                            @foreach ($rows as $option)
                                                @php($candidate = $option['category'])
                                                @if ($node->canHaveParent($candidate))
                                                    <option value="{{ $candidate->id }}" @selected((int) old('items.'.$node->id.'.parent_id', $node->parent_id) === $candidate->id)>
                                                        {{ str_repeat('— ', $option['depth']) }}{{ $candidate->name }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div class="sm:col-span-3">
                                    <label class="block text-xs font-medium text-stone-500">Kolejność</label>
                                    <input type="number" min="0" max="9999" name="items[{{ $node->id }}][position]"
                                        value="{{ old('items.'.$node->id.'.position', $node->position) }}"
                                        class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                </div>
                            </div>

                            {{-- Opis pokazujemy na stronie kategorii w sklepie.
                                 Zwinięty, bo przy dwustu miastach nikt go nie pisze,
                                 a rozwinięty rozpychałby listę nie do przejścia. --}}
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-medium text-stone-500 transition hover:text-amber-700">
                                    Opis w sklepie{{ filled($node->description) ? ' — wypełniony' : '' }}
                                </summary>
                                <textarea name="items[{{ $node->id }}][description]" rows="3"
                                    placeholder="Kilka zdań na stronie tej kategorii — dla kupującego i dla wyszukiwarki."
                                    class="mt-2 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">{{ old('items.'.$node->id.'.description', $node->description) }}</textarea>
                            </details>

                            {{-- WSTRZYMANIE SPRZEDAŻY CAŁEJ SERII — tylko na osi
                                 jednokrotnej, gdzie „ta seria" znaczy jedno.
                                 Przycisk wysyła ten sam formularz na inny adres
                                 (`formaction`), więc jedno kliknięcie zapisuje
                                 datę i komunikat, i od razu wstrzymuje. --}}
                            @if ($axis->suspendable())
                                <details class="mt-3" @if ($node->salesSuspended()) open @endif>
                                    <summary class="cursor-pointer text-xs font-medium {{ $node->salesSuspended() ? 'text-rose-600' : 'text-stone-500 hover:text-amber-700' }}">
                                        {{ $node->salesSuspended() ? 'SPRZEDAŻ WSTRZYMANA' : 'Sprzedaż serii' }}
                                    </summary>

                                    <div class="mt-2 grid gap-3 sm:grid-cols-12">
                                        <div class="sm:col-span-4">
                                            <label class="block text-xs font-medium text-stone-500">Wznowienie</label>
                                            <input type="date" name="suspension[{{ $node->id }}][sales_resume_on]"
                                                value="{{ old('suspension.'.$node->id.'.sales_resume_on', $node->sales_resume_on?->format('Y-m-d')) }}"
                                                class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                        </div>
                                        <div class="sm:col-span-8">
                                            <label class="block text-xs font-medium text-stone-500">Komunikat dla kupujących</label>
                                            <input type="text" name="suspension[{{ $node->id }}][suspension_note]" maxlength="300"
                                                value="{{ old('suspension.'.$node->id.'.suspension_note', $node->suspension_note) }}"
                                                placeholder="Zostawione puste — napiszemy sami, z datą wznowienia."
                                                class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                        </div>
                                    </div>

                                    <button type="submit" formaction="{{ route('seller.categories.suspension', [$axis->segment(), $node]) }}"
                                        class="mt-3 rounded-2xl px-4 py-2 text-sm font-semibold transition {{ $node->salesSuspended()
                                            ? 'bg-emerald-600 text-white hover:brightness-105'
                                            : 'border border-rose-300 text-rose-700 hover:bg-rose-50' }}">
                                        {{ $node->salesSuspended() ? 'Wznów sprzedaż' : 'Wstrzymaj sprzedaż tej serii' }}
                                    </button>

                                    <p class="mt-2 text-xs leading-relaxed text-stone-500">
                                        Produkty zostają widoczne — opis, zdjęcia i adres bez zmian, żeby nie stracić ich
                                        pozycji w wyszukiwarce. Znika tylko możliwość kupienia, także dla tych, którzy mają
                                        je już w koszyku. Po dacie wznowienia sprzedaż wraca sama.
                                    </p>

                                    @error('suspension.'.$node->id.'.sales_resume_on')
                                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                    @enderror
                                </details>
                            @endif

                            <div class="mt-3 flex flex-wrap items-center gap-4 border-t border-stone-100 pt-3 text-sm">
                                <span class="text-xs text-stone-400">
                                    /{{ $axis->segment() }}/{{ $node->slug }}
                                    · {{ $node->products_count }} {{ $node->products_count === 1 ? 'produkt' : 'produktów' }}
                                </span>
                                <label class="flex items-center gap-2 text-rose-600">
                                    <input type="checkbox" name="items[{{ $node->id }}][_delete]" value="1">
                                    Usuń
                                </label>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-10 text-center">
                            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🗂️</span>
                            <p class="mt-4 font-medium text-stone-700">Ten podział jest jeszcze pusty</p>
                            <p class="mt-1 text-sm text-stone-500">Wpisz pierwszą pozycję w polu niżej i zapisz.</p>
                        </div>
                    @endforelse

                    {{-- Pusty wiersz na kolejną pozycję. --}}
                    <div class="rounded-2xl border border-dashed border-stone-300 bg-white/60 p-4">
                        <p class="text-xs font-medium text-stone-500">Nowa pozycja</p>
                        <div class="mt-2 grid gap-3 sm:grid-cols-12">
                            <div class="{{ $axis->hierarchical() ? 'sm:col-span-6' : 'sm:col-span-9' }}">
                                <input type="text" name="items[nowa][name]" value="{{ old('items.nowa.name') }}"
                                    placeholder="np. {{ $axis->hierarchical() ? 'Rzym' : 'Kamień' }}"
                                    class="block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('items.nowa.name')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            @if ($axis->hierarchical())
                                <div class="sm:col-span-3">
                                    <select name="items[nowa][parent_id]"
                                        class="block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                        <option value="">— najwyższy poziom —</option>
                                        @foreach ($rows as $option)
                                            @if ($option['depth'] + 1 < $axis->maxDepth())
                                                <option value="{{ $option['category']->id }}" @selected((int) old('items.nowa.parent_id') === $option['category']->id)>
                                                    {{ str_repeat('— ', $option['depth']) }}{{ $option['category']->name }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="sm:col-span-3">
                                <input type="number" min="0" max="9999" name="items[nowa][position]" value="{{ old('items.nowa.position', 0) }}"
                                    placeholder="kolejność"
                                    class="block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            </div>
                        </div>
                    </div>
                </div>

                <p class="mt-4 text-xs leading-relaxed text-stone-500">
                    <span class="font-medium text-stone-700">Adres pozycji nie zmienia się razem z nazwą.</span>
                    Poprawka literówki nie przenosi kategorii pod inny adres — inaczej padłyby linki, które już ktoś rozesłał,
                    i pozycja w wyszukiwarce.
                    @if ($axis->hierarchical())
                        Pozycja wyżej obejmuje wszystko, co pod nią: produkt przypięty do Rzymu widać też we Włoszech.
                    @endif
                </p>
            </form>
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Trzy niezależne podziały</h2>
                <p class="mt-3 text-sm text-stone-500">
                    Ten sam produkt stoi jednocześnie w każdym z nich — to nie są szufladki, z których trzeba wybrać jedną,
                    tylko trzy różne drogi, którymi kupujący do niego trafia.
                </p>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    @foreach ($axes as $tab)
                        <li class="flex gap-3">
                            <span class="mt-0.5 shrink-0 text-amber-500">{{ $tab->multiple() ? '▦' : '▪' }}</span>
                            <span>
                                <span class="font-medium text-stone-700">{{ $tab->label() }}</span> —
                                {{ $tab->multiple() ? 'produkt może być w kilku naraz' : 'produkt należy do jednej' }}{{ $tab->hierarchical() ? ', z zagłębieniem do '.$tab->maxDepth().' poziomów' : '' }}.
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Kategorie a tagi</h2>
                <p class="mt-3 text-sm text-stone-500">
                    Kategoria to miejsce w katalogu — ma własny adres i własną stronę. Tag jest luźną etykietą, którą
                    dopisujesz przy produkcie w locie. Jedno nie zastępuje drugiego: kategorie układasz raz, tagi rosną same.
                </p>
            </div>
        </aside>
    </div>
</x-layouts.panel>
