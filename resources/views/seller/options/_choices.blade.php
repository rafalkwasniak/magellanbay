{{-- BIBLIOTEKA GRAFIK — lista wierszy zapisywana jednym żądaniem.

     Ostatni wiersz jest zawsze PUSTY: to nim dodaje się kolejną pozycję, bez
     osobnego przycisku i bez JavaScriptu.

     POZYCJI NIE DA SIĘ TU SKASOWAĆ, tylko wygasić — i to nie jest niedoróbka.
     Zamówienie wskazuje wybraną grafikę po identyfikatorze, więc skasowany
     wiersz zabiera ze sobą PLIK do wygrawerowania: zamówienie nadal mówiłoby
     „Trasa Biegu", ale nie dałoby się jej wykonać. --}}
<form method="POST" action="{{ route('seller.options.choices', $group) }}" enctype="multipart/form-data"
    class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur" novalidate>
    @csrf

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-semibold text-stone-900">Biblioteka</h2>
            <p class="mt-1 text-sm text-stone-500">Pozycje, z których klient wybiera jedną. Kolejność wierszy = kolejność w siatce.</p>
        </div>
        <button type="submit"
            class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
            Zapisz bibliotekę
        </button>
    </div>

    <div class="mt-6 space-y-3">
        @foreach ($group->choices as $choice)
            <div class="rounded-2xl border border-stone-200 bg-white/80 p-4 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-stone-200 bg-white">
                        @if ($choice->imageUrl())
                            <img src="{{ $choice->imageUrl() }}" alt="{{ $choice->label }}" class="h-full w-full object-contain">
                        @else
                            <span class="flex h-full w-full items-center justify-center text-xs text-stone-400">bez grafiki</span>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1 grid gap-3 sm:grid-cols-12">
                        <div class="sm:col-span-12">
                            <label class="block text-xs font-medium text-stone-500">Nazwa</label>
                            <input type="text" name="items[{{ $choice->id }}][label]" value="{{ old('items.'.$choice->id.'.label', $choice->label) }}"
                                class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('items.'.$choice->id.'.label')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-4">
                            <label class="block text-xs font-medium text-stone-500">Dopłata (zł)</label>
                            <input type="text" inputmode="decimal" name="items[{{ $choice->id }}][surcharge_gross]"
                                value="{{ old('items.'.$choice->id.'.surcharge_gross', number_format((float) $choice->surcharge_gross, 2, ',', '')) }}"
                                class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        </div>

                        <div class="sm:col-span-4">
                            <label class="block text-xs font-medium text-stone-500">Opłata licencyjna (zł)</label>
                            <input type="text" inputmode="decimal" name="items[{{ $choice->id }}][licence_fee_gross]"
                                value="{{ old('items.'.$choice->id.'.licence_fee_gross', number_format((float) $choice->licence_fee_gross, 2, ',', '')) }}"
                                class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        </div>

                        <div class="sm:col-span-4">
                            <label class="block text-xs font-medium text-stone-500">Partner</label>
                            <select name="items[{{ $choice->id }}][licensor_id]"
                                class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                <option value="">— bez partnera —</option>
                                @foreach ($licensors as $licensor)
                                    <option value="{{ $licensor->id }}" @selected((int) old('items.'.$choice->id.'.licensor_id', $choice->licensor_id) === $licensor->id)>{{ $licensor->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-12">
                            <label class="block text-xs font-medium text-stone-500">Podmień grafikę</label>
                            <input type="file" name="items[{{ $choice->id }}][image]" accept="image/jpeg,image/png,image/webp"
                                class="mt-1 block w-full text-sm text-stone-600 file:mr-3 file:rounded-xl file:border-0 file:bg-stone-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-stone-700">
                            @error('items.'.$choice->id.'.image')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-4 border-t border-stone-100 pt-3 text-sm">
                    <label class="flex items-center gap-2 text-stone-700">
                        <input type="checkbox" name="items[{{ $choice->id }}][is_active]" value="1" @checked($choice->is_active)>
                        Dostępna w sklepie
                    </label>
                    @unless ($choice->is_active)
                        <span class="text-xs text-stone-400">Wygaszona — znika z wyboru, zostaje w historii zamówień.</span>
                    @endunless
                </div>
            </div>
        @endforeach

        <div class="rounded-2xl border border-dashed border-stone-300 bg-white/50 p-4">
            <p class="text-xs font-medium text-stone-500">Nowa pozycja</p>
            <div class="mt-2 grid gap-3 sm:grid-cols-12">
                <div class="sm:col-span-12">
                    <input type="text" name="items[nowa][label]" value="{{ old('items.nowa.label') }}"
                        placeholder="np. Trasa Biegu Gdańskiego"
                        class="block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>
                <div class="sm:col-span-4">
                    <input type="text" inputmode="decimal" name="items[nowa][surcharge_gross]" value="{{ old('items.nowa.surcharge_gross', '0,00') }}"
                        placeholder="dopłata"
                        class="block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>
                <div class="sm:col-span-4">
                    <input type="text" inputmode="decimal" name="items[nowa][licence_fee_gross]" value="{{ old('items.nowa.licence_fee_gross', '0,00') }}"
                        placeholder="licencja"
                        class="block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>
                <div class="sm:col-span-4">
                    <select name="items[nowa][licensor_id]"
                        class="block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <option value="">— bez partnera —</option>
                        @foreach ($licensors as $licensor)
                            <option value="{{ $licensor->id }}">{{ $licensor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-12">
                    <input type="file" name="items[nowa][image]" accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-stone-600 file:mr-3 file:rounded-xl file:border-0 file:bg-stone-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-stone-700">
                    @error('items.nowa.image')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <label class="mt-3 flex items-center gap-2 text-sm text-stone-700">
                <input type="checkbox" name="items[nowa][is_active]" value="1" checked>
                Dostępna w sklepie
            </label>
        </div>
    </div>

    <p class="mt-4 text-xs leading-relaxed text-stone-500">
        <span class="font-medium text-stone-700">Opłata licencyjna to co innego niż dopłata.</span> Dopłata jest Twoim kosztem
        i sumuje się zwyczajnie; opłata licencyjna należy się partnerowi i podlega regule „dwie opłaty tej samej firmy na
        jednym produkcie nie sumują się — liczy się wyższa".
    </p>
    <p class="mt-2 text-xs leading-relaxed text-stone-500">
        Grafiki są przeskalowywane i zapisywane jako WebP, razem z usunięciem danych EXIF.
        Pozycji nie da się skasować — <span class="font-medium text-stone-700">wygaś ją</span>, żeby zniknęła z wyboru,
        ale została w zamówieniach, które już ją niosą.
    </p>
</form>
