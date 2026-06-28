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
                            <label class="block text-sm font-medium text-stone-700">Opis sklepu <span class="text-stone-400">(opcjonalnie)</span></label>
                            {{-- h1 → h2: stare opisy (edytor wcześniej zapisywał h1) pokazujemy jako nagłówek h2. --}}
                            <input id="description" type="hidden" name="description" value="{{ str_replace(['<h1>', '</h1>'], ['<h2>', '</h2>'], (string) old('description', $shop->description)) }}">

                            {{-- Własny pasek narzędzi: tylko potrzebne przyciski, ikony SVG, podpowiedzi PL.
                                 Bez cytatu, kodu i załączników (świadomie usunięte). --}}
                            <trix-toolbar id="description-toolbar">
                                <div class="trix-button-row">
                                    <span class="trix-button-group trix-button-group--text-tools" data-trix-button-group="text-tools">
                                        <button type="button" class="trix-button" data-trix-attribute="bold" data-trix-key="b" title="Pogrubienie" tabindex="-1">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 12a4 4 0 0 0 0-8H6v8"/><path d="M15 20a4 4 0 0 0 0-8H6v8Z"/></svg>
                                        </button>
                                        <button type="button" class="trix-button" data-trix-attribute="italic" data-trix-key="i" title="Kursywa" tabindex="-1">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
                                        </button>
                                        <button type="button" class="trix-button" data-trix-attribute="strike" title="Przekreślenie" tabindex="-1">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4H9a3 3 0 0 0-2.83 4"/><path d="M14 12a4 4 0 0 1 0 8H6"/><line x1="4" y1="12" x2="20" y2="12"/></svg>
                                        </button>
                                        <button type="button" class="trix-button" data-trix-attribute="href" data-trix-action="link" data-trix-key="k" title="Odnośnik" tabindex="-1">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                        </button>
                                    </span>
                                    <span class="trix-button-group trix-button-group--block-tools" data-trix-button-group="block-tools">
                                        <button type="button" class="trix-button" data-trix-attribute="heading1" title="Nagłówek" tabindex="-1">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6v12"/><path d="M16 6v12"/><path d="M6 12h10"/></svg>
                                        </button>
                                        <button type="button" class="trix-button" data-trix-attribute="bullet" title="Lista punktowana" tabindex="-1">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                                        </button>
                                        <button type="button" class="trix-button" data-trix-attribute="number" title="Lista numerowana" tabindex="-1">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
                                        </button>
                                    </span>
                                    <span class="trix-button-group-spacer"></span>
                                    <span class="trix-button-group trix-button-group--history-tools" data-trix-button-group="history-tools">
                                        <button type="button" class="trix-button" data-trix-action="undo" data-trix-key="z" title="Cofnij" tabindex="-1">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>
                                        </button>
                                        <button type="button" class="trix-button" data-trix-action="redo" data-trix-key="shift+z" title="Ponów" tabindex="-1">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/></svg>
                                        </button>
                                    </span>
                                </div>
                                <div class="trix-dialogs" data-trix-dialogs>
                                    <div class="trix-dialog trix-dialog--link" data-trix-dialog="href" data-trix-dialog-attribute="href">
                                        <div class="trix-dialog__link-fields">
                                            <input type="url" name="href" class="trix-input trix-input--dialog" placeholder="Wklej lub wpisz adres" aria-label="Adres URL" required data-trix-input>
                                            <div class="trix-button-group">
                                                <input type="button" class="trix-button trix-button--dialog" value="Wstaw" data-trix-method="setAttribute">
                                                <input type="button" class="trix-button trix-button--dialog" value="Usuń" data-trix-method="removeAttribute">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </trix-toolbar>
                            <trix-editor input="description" toolbar="description-toolbar" class="trix-content"></trix-editor>
                            <div class="mt-1 flex items-start justify-between gap-3">
                                <p class="text-xs text-stone-400">Krótko o tym, co sprzedajesz — pojawi się na stronie głównej sklepu. <span class="whitespace-nowrap text-stone-500"><span data-desc-count>0</span> / {{ config('shop.description_max') }} znaków (z formatowaniem)</span></p>
                                <x-ai-improve-button field="shop_description" target="description" class="mt-0 shrink-0" />
                            </div>
                            @error('description')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Identyfikacja sklepu (grafika i elementy wizualne; docelowo też kolory) --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Identyfikacja sklepu</h2>
                    <p class="mt-1 text-sm text-stone-500">Logo i elementy wizualne Twojego sklepu (więcej wkrótce — m.in. kolory).</p>

                    <div class="mt-6">
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
                        <span>Logo wzmacnia wizerunek marki — pojawi się w sklepie i przy jego prezentacji.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        <span>Adres jest widoczny dla klientów i buduje zaufanie do sprzedawcy.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        <span>Dane firmowe i adresowe trafiają automatycznie do regulaminu i dokumentów sprzedażowych.</span>
                    </li>
                </ul>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Wskazówki</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✨</span>
                        <span>Pod opisem kliknij <span class="font-medium text-stone-700">„Popraw przez AI"</span> — poprawi ortografię, interpunkcję i styl jednym kliknięciem.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🧾</span>
                        <span>W <span class="font-medium text-stone-700">Danych firmowych</span> wpisz NIP i kliknij <span class="font-medium text-stone-700">„Pobierz dane z NIP"</span> — uzupełnimy nazwę i adres.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🖼️</span>
                        <span>Najlepsze logo to kwadrat (np. 512×512 px) na jednolitym tle.</span>
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

    {{-- Podgląd logo na żywo + licznik znaków opisu (zero zależności). --}}
    <script>
        (function () {
            // Podgląd logo przed zapisem.
            const input = document.getElementById('logo');
            const preview = document.getElementById('logo-preview');
            const placeholder = document.getElementById('logo-placeholder');
            if (input && preview) {
                input.addEventListener('change', function () {
                    const file = input.files && input.files[0];
                    if (!file) return;
                    preview.src = URL.createObjectURL(file);
                    preview.classList.remove('hidden');
                    if (placeholder) placeholder.classList.add('hidden');
                });
            }

            // Licznik znaków opisu — liczy długość HTML (to ona podlega limitowi).
            const descInput = document.getElementById('description');
            const descCount = document.querySelector('[data-desc-count]');
            const updateDescCount = () => {
                if (descInput && descCount) descCount.textContent = descInput.value.length;
            };
            document.addEventListener('trix-change', updateDescCount);
            document.addEventListener('trix-initialize', updateDescCount);
            updateDescCount();
        })();
    </script>
</x-layouts.panel>
