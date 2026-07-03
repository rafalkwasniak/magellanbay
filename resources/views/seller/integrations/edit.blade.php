<x-layouts.panel title="Integracje">
    <x-slot:heading>Integracje</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: formularz --}}
        <div class="lg:col-span-8">
            <form method="POST" action="{{ route('seller.integrations.update') }}" class="space-y-6" novalidate data-validate>
                @csrf

                {{-- Google Analytics --}}
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
