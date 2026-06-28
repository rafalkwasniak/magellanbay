<x-layouts.panel title="Mój sklep">
    <x-slot:heading>Mój sklep</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: formularz --}}
        <div class="lg:col-span-8">
            <form method="POST" action="{{ route('seller.shop.update') }}" class="space-y-6" enctype="multipart/form-data" novalidate data-validate>
                @csrf

                {{-- Dane podstawowe --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Dane podstawowe</h2>
                    <p class="mt-1 text-sm text-stone-500">Nazwa i opis prezentowane klientom w Twoim sklepie.</p>

                    <div class="mt-6 space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="name" class="block text-sm font-medium text-stone-700">Nazwa sklepu</label>
                                <input id="name" name="name" type="text" required
                                    value="{{ old('name', $shop->name) }}"
                                    data-msg-required="Podaj nazwę sklepu."
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                <p class="mt-1.5 text-xs text-stone-400">Zmiana nazwy nie zmienia adresu sklepu.</p>
                                @error('name')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-stone-700">Adres sklepu</label>
                                <input type="text" value="{{ $shop->host() }}" disabled
                                    class="mt-1.5 block w-full cursor-not-allowed rounded-2xl border border-stone-200 bg-stone-100 px-4 py-3 text-sm text-stone-500">
                                <p class="mt-1.5 text-xs text-stone-400">Stały adres — nadany przy rejestracji.</p>
                            </div>
                        </div>

                        <div>
                            <label for="logo" class="block text-sm font-medium text-stone-700">Logo sklepu <span class="text-stone-400">(opcjonalnie)</span></label>
                            <div class="mt-1.5 flex items-center gap-4">
                                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-stone-200 bg-stone-100">
                                    <img id="logo-preview"
                                        src="{{ $shop->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($shop->logo_path) : '' }}"
                                        alt="Logo sklepu"
                                        class="h-full w-full object-cover {{ $shop->logo_path ? '' : 'hidden' }}">
                                    <span id="logo-placeholder" class="text-2xl {{ $shop->logo_path ? 'hidden' : '' }}">🛍️</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                                        class="block w-full text-sm text-stone-500 file:mr-4 file:rounded-xl file:border-0 file:bg-amber-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-amber-800 file:transition hover:file:bg-amber-200">
                                    <p class="mt-1.5 text-xs text-stone-400">PNG, JPG lub WebP, do 2 MB. Najlepiej kwadratowe.</p>
                                    @if ($shop->logo_path)
                                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-stone-600">
                                            <input type="checkbox" name="remove_logo" value="1" class="shrink-0">
                                            <span>Usuń obecne logo</span>
                                        </label>
                                    @endif
                                </div>
                            </div>
                            @error('logo')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-stone-700">Opis sklepu <span class="text-stone-400">(opcjonalnie)</span></label>
                            <textarea id="description" name="description" rows="4"
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">{{ old('description', $shop->description) }}</textarea>
                            <div class="mt-1 flex items-start justify-between gap-3">
                                <p class="text-xs text-stone-400">Krótko o tym, co sprzedajesz — pojawi się na stronie głównej sklepu.</p>
                                <x-ai-improve-button field="shop_description" target="description" class="mt-0 shrink-0" />
                            </div>
                            @error('description')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Dane firmowe (NIP pierwszy + pobranie z rejestru; wypełnia nazwę i adres poniżej) --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Dane firmowe <span class="text-sm font-normal text-stone-400">(opcjonalnie)</span></h2>
                    <p class="mt-1 text-sm text-stone-500">Wpisz NIP i pobierz dane z rejestru — uzupełnimy nazwę firmy oraz adres poniżej. Wynik możesz poprawić.</p>

                    <div class="mt-6 grid grid-cols-12 gap-5">
                        <div class="col-span-12 sm:col-span-4">
                            <label for="nip" class="block text-sm font-medium text-stone-700">NIP</label>
                            <input id="nip" name="nip" type="text" inputmode="numeric" placeholder="0000000000"
                                value="{{ old('nip', $shop->nip) }}"
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <button type="button" data-nip-lookup data-url="{{ route('seller.company.lookup') }}" disabled
                                class="mt-2 inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                                </svg>
                                <span data-nip-text>Pobierz dane z NIP</span>
                            </button>
                            <p class="mt-1.5 text-xs text-stone-400">Pobiera nazwę i adres z rejestru REGON (GUS), a gdy niedostępny — z Białej listy podatników VAT (MF).</p>
                            @error('nip')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-12 sm:col-span-8">
                            <label for="company_name" class="block text-sm font-medium text-stone-700">Nazwa firmy</label>
                            <input id="company_name" name="company_name" type="text"
                                value="{{ old('company_name', $shop->company_name) }}"
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('company_name')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Adres sklepu --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Adres sklepu</h2>
                    <p class="mt-1 text-sm text-stone-500">Adres prowadzenia działalności lub kontaktowy — używany w regulaminie i dokumentach.</p>

                    <div class="mt-6 grid grid-cols-12 gap-5">
                        <div class="col-span-12 sm:col-span-6">
                            <label for="street" class="block text-sm font-medium text-stone-700">Ulica</label>
                            <input id="street" name="street" type="text" required
                                value="{{ old('street', $shop->street) }}"
                                data-msg-required="Podaj ulicę."
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('street')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="building_number" class="block text-sm font-medium text-stone-700">Nr budynku</label>
                            <input id="building_number" name="building_number" type="text" required
                                value="{{ old('building_number', $shop->building_number) }}"
                                data-msg-required="Podaj numer budynku."
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('building_number')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="apartment_number" class="block text-sm font-medium text-stone-700">Nr lokalu <span class="text-stone-400">(opc.)</span></label>
                            <input id="apartment_number" name="apartment_number" type="text"
                                value="{{ old('apartment_number', $shop->apartment_number) }}"
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('apartment_number')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="postal_code" class="block text-sm font-medium text-stone-700">Kod pocztowy</label>
                            <input id="postal_code" name="postal_code" type="text" inputmode="numeric" placeholder="00-000" required
                                value="{{ old('postal_code', $shop->postal_code) }}"
                                data-msg-required="Podaj kod pocztowy."
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('postal_code')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-9">
                            <label for="city" class="block text-sm font-medium text-stone-700">Miejscowość</label>
                            <input id="city" name="city" type="text" required
                                value="{{ old('city', $shop->city) }}"
                                data-msg-required="Podaj miejscowość."
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('city')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-12 sm:col-span-6">
                            <label for="province" class="block text-sm font-medium text-stone-700">Województwo</label>
                            @php($selectedProvince = old('province', $shop->province))
                            <select id="province" name="province" required
                                data-msg-required="Wybierz województwo."
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                <option value="" @selected($selectedProvince === null || $selectedProvince === '')>— wybierz —</option>
                                @foreach (config('shop.provinces') as $province)
                                    <option value="{{ $province }}" @selected($selectedProvince === $province)>{{ $province }}</option>
                                @endforeach
                            </select>
                            @error('province')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-12 sm:col-span-6">
                            <label for="country" class="block text-sm font-medium text-stone-700">Kraj</label>
                            <input id="country" name="country" type="text" required
                                value="{{ old('country', $shop->country ?? 'Polska') }}"
                                data-msg-required="Podaj kraj."
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('country')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        Zapisz dane sklepu
                    </button>
                </div>
            </form>
        </div>

        {{-- Kolumna pomocnicza: treści opisowe --}}
        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Dlaczego prosimy o te dane</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        <span>Nazwa i opis budują rozpoznawalność sklepu i wspierają pozycjonowanie w wyszukiwarkach.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        <span>Adres jest widoczny dla klientów i buduje zaufanie do sprzedawcy.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        <span>Dane adresowe i firmowe trafiają automatycznie do regulaminu i dokumentów sprzedażowych.</span>
                    </li>
                </ul>
            </div>

            <div class="rounded-3xl border border-amber-200/70 bg-amber-50/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Adres jest stały</h2>
                <p class="mt-2 text-sm text-stone-600">
                    Adres Twojego sklepu (<span class="font-medium text-amber-800">{{ $shop->host() }}</span>) został nadany przy rejestracji i nie zmienia się przy edycji nazwy.
                </p>
            </div>
        </aside>
    </div>

    {{-- Podgląd logo na żywo (zero zależności): pokazuje wybrany plik przed zapisem. --}}
    <script>
        (function () {
            const input = document.getElementById('logo');
            const preview = document.getElementById('logo-preview');
            const placeholder = document.getElementById('logo-placeholder');
            if (!input || !preview) return;

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                if (!file) return;
                preview.src = URL.createObjectURL(file);
                preview.classList.remove('hidden');
                if (placeholder) placeholder.classList.add('hidden');
            });
        })();
    </script>
</x-layouts.panel>
