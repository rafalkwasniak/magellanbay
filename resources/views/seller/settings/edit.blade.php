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

                {{-- Dostawa --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Dostawa</h2>
                    <p class="mt-1 text-sm text-stone-500">Sposoby dostawy widoczne dla klientów w kasie.</p>

                    <div class="mt-6 space-y-4">
                        @php($hasAddress = $shop->addressComplete())
                        <div class="flex items-start gap-4 rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6 {{ $hasAddress ? '' : 'opacity-60' }}">
                            {{-- hidden = wartość bazowa; bez adresu checkbox jest disabled i nic nie wysyła --}}
                            <input type="hidden" name="pickup_enabled" value="{{ $hasAddress ? '0' : ($shop->pickup_enabled ? '1' : '0') }}">
                            <input type="checkbox" id="pickup_enabled" name="pickup_enabled" value="1"
                                @checked(old('pickup_enabled', $shop->pickup_enabled)) @disabled(! $hasAddress)
                                class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                            <label for="pickup_enabled" class="flex-1 {{ $hasAddress ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                <span class="block text-sm font-medium text-stone-800">Odbiór osobisty</span>
                                <span class="mt-0.5 block text-sm text-stone-500">Klient odbiera zamówienie pod adresem Twojego sklepu. Bez kosztów dostawy.</span>
                                @unless($hasAddress)
                                    @if($shop->pickup_enabled)
                                        <span class="mt-1.5 block text-xs text-amber-700">Odbiór jest włączony, ale zacznie działać w kasie, gdy uzupełnisz adres sklepu w <a href="{{ route('seller.shop.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Mój sklep</a>.</span>
                                    @else
                                        <span class="mt-1.5 block text-xs text-amber-700">Aby móc włączyć, najpierw uzupełnij adres sklepu w <a href="{{ route('seller.shop.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Mój sklep</a>.</span>
                                    @endif
                                @endunless
                            </label>
                        </div>

                        <p class="text-xs text-stone-400">Kolejne metody (kurier, dostawa własna) dojdą tutaj wkrótce.</p>
                    </div>
                </div>

                {{-- Płatności --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Płatności</h2>
                    <p class="mt-1 text-sm text-stone-500">Metody płatności widoczne dla klientów w kasie.</p>

                    <div class="mt-6 space-y-4">
                        @php($hasAccount = filled($shop->bank_account_number))
                        <div class="flex items-start gap-4 rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6 {{ $hasAccount ? '' : 'opacity-60' }}">
                            {{-- hidden = wartość bazowa; przy braku numeru zachowujemy stan (checkbox jest disabled i nic nie wysyła) --}}
                            <input type="hidden" name="bank_transfer_enabled" value="{{ $hasAccount ? '0' : ($shop->bank_transfer_enabled ? '1' : '0') }}">
                            <input type="checkbox" id="bank_transfer_enabled" name="bank_transfer_enabled" value="1"
                                @checked(old('bank_transfer_enabled', $shop->bank_transfer_enabled)) @disabled(! $hasAccount)
                                class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                            <label for="bank_transfer_enabled" class="flex-1 {{ $hasAccount ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                <span class="block text-sm font-medium text-stone-800">Przelew na konto</span>
                                <span class="mt-0.5 block text-sm text-stone-500">Klient otrzymuje Twój numer konta i tytuł przelewu. Bez operatora i prowizji.</span>
                                @unless($hasAccount)
                                    @if($shop->bank_transfer_enabled)
                                        <span class="mt-1.5 block text-xs text-amber-700">Ta metoda jest włączona, ale zacznie działać w kasie dopiero, gdy podasz numer konta w <a href="{{ route('seller.shop.edit') }}#dane-do-przelewu" class="font-medium underline decoration-amber-300 underline-offset-2">Mój sklep</a>.</span>
                                    @else
                                        <span class="mt-1.5 block text-xs text-amber-700">Aby móc włączyć tę metodę, najpierw podaj numer konta w <a href="{{ route('seller.shop.edit') }}#dane-do-przelewu" class="font-medium underline decoration-amber-300 underline-offset-2">Mój sklep</a>.</span>
                                    @endif
                                @endunless
                            </label>
                        </div>

                        {{-- Płatność przy odbiorze — zależna od włączonego odbioru osobistego. --}}
                        @php($pickupReady = $shop->pickupAvailable())
                        <div class="flex items-start gap-4 rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6 {{ $pickupReady ? '' : 'opacity-60' }}">
                            <input type="hidden" name="pay_on_pickup_enabled" value="{{ $pickupReady ? '0' : ($shop->pay_on_pickup_enabled ? '1' : '0') }}">
                            <input type="checkbox" id="pay_on_pickup_enabled" name="pay_on_pickup_enabled" value="1"
                                @checked(old('pay_on_pickup_enabled', $shop->pay_on_pickup_enabled)) @disabled(! $pickupReady)
                                class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                            <label for="pay_on_pickup_enabled" class="flex-1 {{ $pickupReady ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                <span class="block text-sm font-medium text-stone-800">Płatność przy odbiorze</span>
                                <span class="mt-0.5 block text-sm text-stone-500">Klient płaci na miejscu przy odbiorze zamówienia. Bez operatora i prowizji.</span>
                                @unless($pickupReady)
                                    <span class="mt-1.5 block text-xs text-amber-700">Ta metoda wymaga włączonego <a href="#" onclick="document.getElementById('pickup_enabled').scrollIntoView({behavior:'smooth',block:'center'});return false;" class="font-medium underline decoration-amber-300 underline-offset-2">odbioru osobistego</a> (z uzupełnionym adresem sklepu).</span>
                                @endunless
                            </label>
                        </div>

                        <p class="text-xs text-stone-400">Płatności online (szybkie przelewy, BLIK, karty) dojdą później jako integracja w wyższych pakietach.</p>
                    </div>
                </div>

                {{-- Integracje (włączniki; konfiguracja w zakładce Integracje) --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Integracje</h2>
                    <p class="mt-1 text-sm text-stone-500">Włączasz i wyłączasz usługi skonfigurowane w zakładce <a href="{{ route('seller.integrations.edit') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">Integracje</a>.</p>

                    <div class="mt-6 space-y-4">
                        @php($gaConfigured = filled($googleAnalyticsId))
                        <div class="flex items-start gap-4 rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6 {{ $gaConfigured ? '' : 'opacity-60' }}">
                            {{-- hidden = wartość bazowa; bez ID checkbox jest disabled i nic nie wysyła --}}
                            <input type="hidden" name="google_analytics_enabled" value="{{ $gaConfigured ? '0' : ($googleAnalyticsEnabled ? '1' : '0') }}">
                            <input type="checkbox" id="google_analytics_enabled" name="google_analytics_enabled" value="1"
                                @checked(old('google_analytics_enabled', $googleAnalyticsEnabled)) @disabled(! $gaConfigured)
                                class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                            <label for="google_analytics_enabled" class="flex-1 {{ $gaConfigured ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                <span class="block text-sm font-medium text-stone-800">Google Analytics</span>
                                <span class="mt-0.5 block text-sm text-stone-500">Zbiera statystyki ruchu w Twoim sklepie i wysyła je na Twoje konto Google.</span>
                                @unless($gaConfigured)
                                    <span class="mt-1.5 block text-xs text-amber-700">Aby móc włączyć, najpierw wpisz identyfikator w <a href="{{ route('seller.integrations.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Integracjach</a>.</span>
                                @endunless
                            </label>
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
                        <span class="mt-0.5 shrink-0 text-amber-500">🏦</span>
                        <span>Numer konta do przelewu podajesz w <a href="{{ route('seller.shop.edit') }}#dane-do-przelewu" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">Mój sklep</a>; tutaj tylko włączasz metodę.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔧</span>
                        <span>Płatności online i integracje dojdą tutaj wkrótce.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
