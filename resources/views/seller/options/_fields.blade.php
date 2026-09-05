{{-- POLA FORMATKI — lista wierszy zapisywana jednym żądaniem.

     Ostatni wiersz jest zawsze PUSTY: to nim dodaje się kolejne pole, bez
     osobnego przycisku „dodaj" i bez JavaScriptu. Wiersz zostawiony pusty jest
     po prostu pomijany przy zapisie, więc nic nie trzeba kasować. --}}
<form method="POST" action="{{ route('seller.options.fields', $group) }}"
    class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur" novalidate>
    @csrf

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-semibold text-stone-900">Pola do wypełnienia</h2>
            <p class="mt-1 text-sm text-stone-500">Klient wpisze w nie własny tekst. Kolejność wierszy = kolejność na karcie produktu.</p>
        </div>
        <button type="submit"
            class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
            Zapisz pola
        </button>
    </div>

    <div class="mt-6 space-y-3">
        @foreach ($group->fields as $field)
            <div class="rounded-2xl border border-stone-200 bg-white/80 p-4 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-12">
                    <div class="sm:col-span-5">
                        <label class="block text-xs font-medium text-stone-500">Etykieta</label>
                        <input type="text" name="items[{{ $field->id }}][label]" value="{{ old('items.'.$field->id.'.label', $field->label) }}"
                            class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        @error('items.'.$field->id.'.label')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-xs font-medium text-stone-500">Limit znaków</label>
                        <input type="number" min="1" max="500" name="items[{{ $field->id }}][max_length]"
                            value="{{ old('items.'.$field->id.'.max_length', $field->max_length) }}"
                            class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        @error('items.'.$field->id.'.max_length')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-4">
                        <label class="block text-xs font-medium text-stone-500">Podpowiedź w polu</label>
                        <input type="text" name="items[{{ $field->id }}][placeholder]"
                            value="{{ old('items.'.$field->id.'.placeholder', $field->placeholder) }}"
                            class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-4 border-t border-stone-100 pt-3 text-sm">
                    <label class="flex items-center gap-2 text-stone-700">
                        <input type="checkbox" name="items[{{ $field->id }}][required]" value="1" @checked($field->required)>
                        Wymagane
                    </label>
                    {{-- Kasowanie POLA jest bezpieczne: zamówienie niesie migawkę
                         „etykieta → wartość", a do wykonania nadruku potrzebny jest
                         sam tekst, nie identyfikator pola. --}}
                    <label class="flex items-center gap-2 text-rose-600">
                        <input type="checkbox" name="items[{{ $field->id }}][_delete]" value="1">
                        Usuń to pole
                    </label>
                </div>
            </div>
        @endforeach

        {{-- Pusty wiersz na kolejne pole. --}}
        <div class="rounded-2xl border border-dashed border-stone-300 bg-white/50 p-4">
            <p class="text-xs font-medium text-stone-500">Nowe pole</p>
            <div class="mt-2 grid gap-3 sm:grid-cols-12">
                <div class="sm:col-span-5">
                    <input type="text" name="items[nowe][label]" value="{{ old('items.nowe.label') }}"
                        placeholder="np. Imię"
                        class="block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>
                <div class="sm:col-span-3">
                    <input type="number" min="1" max="500" name="items[nowe][max_length]" value="{{ old('items.nowe.max_length', 30) }}"
                        class="block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>
                <div class="sm:col-span-4">
                    <input type="text" name="items[nowe][placeholder]" value="{{ old('items.nowe.placeholder') }}"
                        placeholder="podpowiedź (nieobowiązkowa)"
                        class="block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>
            </div>
            <label class="mt-3 flex items-center gap-2 text-sm text-stone-700">
                <input type="checkbox" name="items[nowe][required]" value="1" checked>
                Wymagane
            </label>
            @error('items.nowe.max_length')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <p class="mt-4 text-xs leading-relaxed text-stone-500">
        <span class="font-medium text-stone-700">Limit znaków wynika z fizyki produktu</span> — na magnes wchodzi tyle liter,
        ile wchodzi. Dłuższy tekst nie zostanie przycięty, tylko odrzucony przy dodawaniu do koszyka: magnes z uciętym
        imieniem to rzecz, której klient nie może zwrócić.
    </p>
</form>
