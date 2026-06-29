<x-layouts.panel title="Ustawienia">
    <x-slot:heading>Ustawienia sklepu</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: formularz --}}
        <div class="lg:col-span-8">
            <form method="POST" action="{{ route('seller.settings.update') }}" class="space-y-6" novalidate data-validate>
                @csrf

                {{-- Sprzedaż --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Sprzedaż</h2>
                    <p class="mt-1 text-sm text-stone-500">Domyślne wartości podpowiadane przy dodawaniu produktów.</p>

                    <div class="mt-6 grid grid-cols-12 gap-5">
                        <div class="col-span-6 sm:col-span-3">
                            <label for="default_vat_rate" class="block text-sm font-medium text-stone-700">Domyślna stawka VAT</label>
                            @php($selectedVat = old('default_vat_rate', $shop->default_vat_rate?->value ?? '23'))
                            <select id="default_vat_rate" name="default_vat_rate" required
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @foreach ($vatRates as $rate)
                                    <option value="{{ $rate->value }}" @selected($selectedVat === $rate->value)>{{ $rate->label() }}</option>
                                @endforeach
                            </select>
                            @error('default_vat_rate')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1.5 text-xs text-stone-400">Wstępnie ustawiana przy każdym nowym produkcie — przy produkcie nadal możesz ją zmienić.</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        Zapisz ustawienia
                    </button>
                </div>
            </form>
        </div>

        {{-- Kolumna pomocnicza: wskazówki --}}
        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Wskazówki</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🧮</span>
                        <span>Ustaw stawkę, której używasz najczęściej — zaoszczędzisz klik przy każdym produkcie.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔧</span>
                        <span>Kolejne ustawienia (płatności, integracje) dojdą tutaj wkrótce.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
