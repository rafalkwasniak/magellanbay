<x-layouts.panel title="Mój sklep">
    <x-slot:heading>Mój sklep</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: formularz --}}
        <div class="lg:col-span-8">
            <form method="POST" action="{{ route('seller.shop.update') }}" class="space-y-6" novalidate data-validate>
                @csrf

                {{-- Dane podstawowe --}}
                <div id="dane-podstawowe" class="scroll-mt-24 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
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
                            <x-rich-editor name="description" :value="old('description', $shop->description)" ai-field="shop_description" :max="config('shop.description_max')">Krótko o tym, co sprzedajesz — pojawi się na stronie głównej sklepu.</x-rich-editor>
                            @error('description')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Dane kontaktowe — osobny box: e-mail i telefon nie wypełniają się z NIP
                     (jak adres), a budują wiarygodność sklepu i zasilają kontakt/maile. --}}
                <div id="dane-kontaktowe" class="scroll-mt-24 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Dane kontaktowe</h2>
                    <p class="mt-1 text-sm text-stone-500">Widoczne dla klientów — pod tym adresem i numerem odpisujesz na pytania o zamówienia. E-mail trafia też w odpowiedź na maile ze sklepu.</p>

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="contact_email" class="block text-sm font-medium text-stone-700">E-mail kontaktowy</label>
                            <input id="contact_email" name="contact_email" type="email" required
                                value="{{ old('contact_email', $shop->contact_email) }}"
                                data-msg-required="Podaj e-mail kontaktowy."
                                data-msg-email="Podaj prawidłowy adres e-mail."
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <p class="mt-1.5 text-xs text-stone-400">Na ten adres klient odpowie, pisząc na maila o zamówieniu.</p>
                            @error('contact_email')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="contact_phone" class="block text-sm font-medium text-stone-700">Telefon kontaktowy</label>
                            <input id="contact_phone" name="contact_phone" type="tel" inputmode="tel" required
                                value="{{ old('contact_phone', $shop->formattedContactPhone()) }}"
                                placeholder="+48 600 700 800"
                                data-msg-required="Podaj telefon kontaktowy."
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <p class="mt-1.5 text-xs text-stone-400">9 cyfr. Możesz wpisać ze spacjami lub z „+48" — poprawimy zapis.</p>
                            @error('contact_phone')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Dane firmowe (NIP pierwszy + pobranie z rejestru; wypełnia nazwę i adres poniżej) --}}
                <div id="dane-firmowe" class="scroll-mt-24 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
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
                <div id="adres" class="scroll-mt-24 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
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

                {{-- Dane do przelewu — na końcu, bo to samodzielna dana; blok NIP→firma→adres
                     zostaje w jednym ciągu (wypełnia się razem z pobrania danych z NIP). --}}
                <div id="dane-do-przelewu" class="scroll-mt-24 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Dane do przelewu <span class="text-sm font-normal text-stone-400">(opcjonalnie)</span></h2>
                    <p class="mt-1 text-sm text-stone-500">Konto do płatności przelewem tradycyjnym. Podanie numeru pozwala włączyć tę metodę w <a href="{{ route('seller.settings.edit') }}" class="font-medium text-amber-700 underline decoration-amber-300 underline-offset-2 hover:text-amber-800">Ustawieniach</a>.</p>

                    <div class="mt-6 grid grid-cols-12 gap-5">
                        <div class="col-span-12">
                            <label for="bank_account_number" class="block text-sm font-medium text-stone-700">Numer konta</label>
                            <input type="text" id="bank_account_number" name="bank_account_number" inputmode="numeric" autocomplete="off"
                                value="{{ old('bank_account_number', $shop->formattedBankAccountNumber()) }}"
                                placeholder="00 0000 0000 0000 0000 0000 0000"
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('bank_account_number')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1.5 text-xs text-stone-400">26 cyfr polskiego numeru konta. Możesz wkleić ze spacjami lub z „PL" — poprawimy zapis.</p>
                        </div>

                        <div class="col-span-12 sm:col-span-6">
                            <label for="bank_account_holder" class="block text-sm font-medium text-stone-700">Odbiorca</label>
                            <input type="text" id="bank_account_holder" name="bank_account_holder" autocomplete="off"
                                value="{{ old('bank_account_holder', $shop->bank_account_holder) }}"
                                placeholder="{{ $shop->company_name ?: 'Nazwa odbiorcy przelewu' }}"
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('bank_account_holder')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1.5 text-xs text-stone-400">Puste = użyjemy nazwy firmy{{ $shop->company_name ? ' („'.$shop->company_name.'")' : '' }}.</p>
                        </div>

                        <div class="col-span-12 sm:col-span-6">
                            <label for="bank_name" class="block text-sm font-medium text-stone-700">Nazwa banku <span class="text-stone-400">(opcjonalnie)</span></label>
                            <input type="text" id="bank_name" name="bank_name" autocomplete="off"
                                value="{{ old('bank_name', $shop->bank_name) }}"
                                placeholder="np. mBank"
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('bank_name')
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
                        <span>Dane kontaktowe (e-mail i telefon) są widoczne dla klientów i trafiają do maili o zamówieniu — pod nie klient odpisze z pytaniem.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        <span>Adres jest widoczny dla klientów i buduje zaufanie do sprzedawcy.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        <span>Dane firmowe i adresowe trafiają automatycznie do regulaminu i dokumentów sprzedażowych.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        <span>Numer konta do przelewu pozwala włączyć płatność przelewem w Ustawieniach.</span>
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
</x-layouts.panel>
