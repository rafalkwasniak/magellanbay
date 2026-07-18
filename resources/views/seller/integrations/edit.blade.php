<x-layouts.panel title="Integracje">
    <x-slot:heading>Integracje</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: formularz --}}
        <div class="lg:col-span-8">
            <form method="POST" action="{{ route('seller.integrations.update') }}" class="space-y-6" novalidate data-validate>
                @csrf

                {{-- Płatności online (Paynow / mBank) — najważniejsza integracja, na
                     samej górze. Na tym etapie BEZ bramy pakietu: dostępna dla każdego
                     sklepu (test na własnym), egzekwowanie entitlement('online_payments')
                     dojdzie na końcu wdrożenia. Klucze sprzedawcy trzymamy zaszyfrowane
                     w bazie — pieniądze płyną klient → Paynow → sprzedawca, wprost. --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex items-start gap-4">
                        <span class="mt-0.5 shrink-0 text-2xl">💳</span>
                        <div class="flex-1">
                            <h2 class="font-semibold text-stone-900">Płatności online (Paynow)</h2>
                            <p class="mt-1 text-sm text-stone-500">
                                Przyjmuj płatności BLIK, kartą i szybkim przelewem wprost w kasie sklepu. Potrzebujesz konta w
                                <a href="https://www.paynow.pl" target="_blank" rel="noopener" class="font-medium text-stone-600 underline decoration-amber-300 underline-offset-2">Paynow</a>
                                (bramka mBanku) oraz dwóch kluczy z jego panelu. Pieniądze trafiają prosto na Twoje konto.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="paynow_api_key" class="block text-sm font-medium text-stone-700">Klucz dostępu do API</label>
                                <input type="text" id="paynow_api_key" name="paynow_api_key"
                                    value="{{ old('paynow_api_key', $paynowApiKey) }}"
                                    placeholder="np. 14d59738-4b18-4c83-…"
                                    autocomplete="off" spellcheck="false"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('paynow_api_key')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">Z panelu Paynow → <span class="text-stone-500">Ustawienia → Klucze API</span>. Wyczyść to pole, aby odłączyć płatności.</p>
                            </div>

                            <div>
                                <label for="paynow_signature_key" class="block text-sm font-medium text-stone-700">Klucz obliczania podpisu</label>
                                <input type="password" id="paynow_signature_key" name="paynow_signature_key"
                                    value=""
                                    placeholder="{{ $paynowConfigured ? '•••••••• (zapisany)' : 'Wklej klucz podpisu z Paynow' }}"
                                    autocomplete="off" spellcheck="false"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('paynow_signature_key')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">
                                    Sekret — podpisuje płatności i weryfikuje powiadomienia.
                                    @if ($paynowConfigured)
                                        Klucz jest zapisany — zostaw pole puste, aby go nie zmieniać.
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div>
                            <label for="paynow_environment" class="block text-sm font-medium text-stone-700">Środowisko</label>
                            <select id="paynow_environment" name="paynow_environment"
                                class="mt-1.5 block w-full max-w-xs rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                <option value="sandbox" @selected(old('paynow_environment', $paynowEnvironment) === 'sandbox')>Sandbox (testowe)</option>
                                <option value="production" @selected(old('paynow_environment', $paynowEnvironment) === 'production')>Produkcja (prawdziwe płatności)</option>
                            </select>
                            @error('paynow_environment')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1.5 text-xs text-stone-400">Zacznij od Sandboksa, aby przetestować. Na produkcję przełącz dopiero z kluczami produkcyjnymi.</p>
                        </div>

                        @if ($paynowConfigured)
                            <div class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4 text-sm">
                                <span class="mt-0.5 shrink-0 font-semibold {{ $paynowEnabled ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $paynowEnabled ? '✓' : '✕' }}
                                </span>
                                <p class="text-stone-600">
                                    @if ($paynowEnabled)
                                        Płatności online są połączone i <span class="font-medium text-stone-800">aktywne</span> ({{ $paynowEnvironment === 'production' ? 'produkcja' : 'sandbox' }}) — klient może zapłacić w kasie.
                                    @else
                                        Klucze są zapisane, ale integracja jest <span class="font-medium text-stone-800">wyłączona</span>. Włącz ją w
                                        <a href="{{ route('seller.settings.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Ustawieniach</a>, aby przyjmować płatności.
                                    @endif
                                </p>
                            </div>
                        @endif

                        {{-- Adres powiadomień: to sprzedawca musi wkleić w panelu Paynow.
                             Nie da się tego ustawić z naszej strony — dlatego pokazujemy
                             gotowy link do skopiowania, a nie „załatwiamy to za Ciebie". --}}
                        <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
                            <p class="text-sm font-medium text-stone-800">Adres powiadomień (webhook)</p>
                            <p class="mt-1 text-xs text-stone-500">
                                Wklej ten adres w panelu Paynow → <span class="text-stone-600">Ustawienia → Powiadomienia (URL)</span>. To przez niego Paynow informuje sklep o opłaceniu zamówienia — bez tego zamówienia nie oznaczą się jako opłacone. Musisz ustawić go samodzielnie po stronie Paynow.
                            </p>
                            <div class="mt-3 flex items-stretch gap-2">
                                <input type="text" id="paynow_webhook_url" value="{{ $paynowWebhookUrl }}" readonly
                                    onclick="this.select()"
                                    class="min-w-0 flex-1 rounded-xl border border-amber-200 bg-white px-3 py-2 font-mono text-xs text-stone-700 shadow-sm">
                                <button type="button"
                                    onclick="navigator.clipboard.writeText(document.getElementById('paynow_webhook_url').value); this.textContent='Skopiowano ✓'; setTimeout(() => this.textContent='Kopiuj', 1500);"
                                    class="shrink-0 rounded-xl border border-amber-300 bg-white px-4 py-2 text-xs font-medium text-amber-800 transition hover:bg-amber-100">Kopiuj</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Fakturownia (faktury VAT) — tylko gdy pakiet daje to uprawnienie.
                     Ważniejsze integracje idą na górę; Google Analytics zostaje na dole. --}}
                @if ($shop->entitlement('invoices'))
                    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                        <div class="flex items-start gap-4">
                            <span class="mt-0.5 shrink-0 text-2xl">🧾</span>
                            <div class="flex-1">
                                <h2 class="font-semibold text-stone-900">Fakturownia</h2>
                                <p class="mt-1 text-sm text-stone-500">
                                    Wystawiaj klientom faktury VAT wprost z karty zamówienia. Potrzebujesz konta w
                                    <a href="https://fakturownia.pl" target="_blank" rel="noopener" class="font-medium text-stone-600 underline decoration-amber-300 underline-offset-2">Fakturowni</a>
                                    oraz tokenu API z tego konta.
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-5">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="fakturownia_url" class="block text-sm font-medium text-stone-700">Adres konta</label>
                                    <input type="text" id="fakturownia_url" name="fakturownia_url"
                                        value="{{ old('fakturownia_url', $fakturowniaUrl) }}"
                                        placeholder="https://twojadomena.fakturownia.pl"
                                        autocomplete="off" spellcheck="false"
                                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                    @error('fakturownia_url')
                                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1.5 text-xs text-stone-400">Adres Twojego konta w Fakturowni. Wyczyść to pole, aby odłączyć integrację.</p>
                                </div>

                                <div>
                                    <label for="fakturownia_token" class="block text-sm font-medium text-stone-700">Token API</label>
                                    <input type="password" id="fakturownia_token" name="fakturownia_token"
                                        value=""
                                        placeholder="{{ $fakturowniaConfigured ? '•••••••• (zapisany)' : 'Wklej token z Fakturowni' }}"
                                        autocomplete="off" spellcheck="false"
                                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                    @error('fakturownia_token')
                                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1.5 text-xs text-stone-400">
                                        Znajdziesz go w Fakturowni → <span class="text-stone-500">Ustawienia → Ustawienia konta → Integracja / API</span>.
                                        @if ($fakturowniaConfigured)
                                            Token jest zapisany — zostaw pole puste, aby go nie zmieniać.
                                        @endif
                                    </p>
                                </div>
                            </div>

                            @if ($fakturowniaConfigured)
                                <div class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4 text-sm">
                                    <span class="mt-0.5 shrink-0 font-semibold {{ $fakturowniaEnabled ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $fakturowniaEnabled ? '✓' : '✕' }}
                                    </span>
                                    <p class="text-stone-600">
                                        @if ($fakturowniaEnabled)
                                            Fakturownia jest połączona i <span class="font-medium text-stone-800">aktywna</span> — na karcie zamówienia możesz wystawić fakturę VAT.
                                        @else
                                            Dane są zapisane, ale integracja jest <span class="font-medium text-stone-800">wyłączona</span>. Włącz ją w
                                            <a href="{{ route('seller.settings.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Ustawieniach</a>, aby wystawiać faktury.
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Google Analytics — najmniej istotna integracja, zawsze na dole. --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex items-start gap-4">
                        <span class="mt-0.5 shrink-0 text-2xl">📈</span>
                        <div class="flex-1">
                            <h2 class="font-semibold text-stone-900">Google Analytics</h2>
                            <p class="mt-1 text-sm text-stone-500">Podłącz statystyki ruchu w swoim sklepie. Obsługujemy Google Analytics&nbsp;4 oraz Google Tag Managera.</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label for="google_analytics_id" class="block text-sm font-medium text-stone-700">Identyfikator śledzenia</label>
                        <input type="text" id="google_analytics_id" name="google_analytics_id"
                            value="{{ old('google_analytics_id', $googleAnalyticsId) }}"
                            placeholder="G-XXXXXXXXXX"
                            autocomplete="off" spellcheck="false"
                            class="mt-1.5 block w-full max-w-sm rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm uppercase tracking-wide shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        @error('google_analytics_id')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-xs text-stone-400">
                            GA4: <span class="font-mono">G-XXXXXXXXXX</span> · Tag Manager: <span class="font-mono">GTM-XXXXXXX</span>.
                            Znajdziesz go w panelu Google Analytics → <span class="text-stone-500">Administracja → Strumienie danych</span>. Zostaw puste, aby odłączyć.
                        </p>

                        @if(filled($googleAnalyticsId))
                            <div class="mt-4 flex items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4 text-sm">
                                <span class="mt-0.5 shrink-0 font-semibold {{ $shop->tracksWithGoogleAnalytics() ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $shop->tracksWithGoogleAnalytics() ? '✓' : '✕' }}
                                </span>
                                <p class="text-stone-600">
                                    @if($shop->tracksWithGoogleAnalytics())
                                        Integracja jest skonfigurowana i <span class="font-medium text-stone-800">aktywna</span> — kod zbiera dane w Twoim sklepie.
                                    @else
                                        Identyfikator zapisany, ale integracja jest <span class="font-medium text-stone-800">wyłączona</span>. Włącz ją w
                                        <a href="{{ route('seller.settings.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Ustawieniach</a>, aby zaczęła zbierać dane.
                                    @endif
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        Zapisz integracje
                    </button>
                </div>
            </form>
        </div>

        {{-- Kolumna pomocnicza: wskazówki --}}
        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔧</span>
                        <span>Tutaj <span class="text-stone-700">konfigurujesz</span> usługi (wpisujesz identyfikatory), a <span class="text-stone-700">włączasz i wyłączasz</span> je w <a href="{{ route('seller.settings.edit') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">Ustawieniach</a>.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">📈</span>
                        <span>Po podaniu identyfikatora integracja włącza się od razu — statystyki zaczynają spływać do Twojego konta Google.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔒</span>
                        <span>Kod śledzenia działa tylko na Twoim sklepie. Kolejne integracje (płatności, wysyłki) dojdą tutaj wkrótce.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
