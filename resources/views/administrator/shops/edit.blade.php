<x-layouts.panel :title="'Sklep: '.$shop->name">
    <x-slot:actions>
        <a href="{{ route('administrator.shops.index') }}"
            class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur transition hover:bg-white">
            ← Wróć do listy
        </a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8 space-y-6">
            <livewire:administrator.shop-manager :shop="$shop" />

            @if ($shop->deletion_scheduled_at)
                {{-- Sklep w karencji: sprzedawca zlecił usunięcie i ma czas do tej daty.
                     Storefront już nie działa, więc admin musi widzieć powód. --}}
                <div class="rounded-3xl border border-rose-300 bg-rose-50 p-6">
                    <h2 class="font-semibold text-rose-900">Sprzedawca zlecił usunięcie tego sklepu</h2>
                    <p class="mt-2 text-sm text-rose-700">
                        Sklep jest niewidoczny dla klientów, a {{ $shop->deletion_scheduled_at->format('d.m.Y') }}
                        zniknie razem z kontem właściciela. Do tego dnia wszystko da się przywrócić.
                    </p>
                    <form method="POST" action="{{ route('administrator.shops.restore', $shop) }}" class="mt-4">
                        @csrf
                        <button type="submit"
                            class="rounded-2xl border border-rose-400 bg-white px-5 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                            Zatrzymaj usunięcie
                        </button>
                    </form>
                </div>
            @endif

            {{-- Usunięcie sklepu. Świadomie na dole, w osobnej karcie i z polem
                 na nazwę — kliknięcie „OK" w oknie przeglądarki jest odruchem,
                 przepisanie nazwy wymaga sprawdzenia, KTÓRY sklep się kasuje. --}}
            <div class="rounded-3xl border border-rose-200 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-rose-900">Usuń sklep</h2>
                <p class="mt-2 text-sm text-stone-600">
                    Trwale usuwa sklep <span class="font-medium text-stone-800">{{ $shop->name }}</span>
                    razem z kontem właściciela ({{ $shop->owner?->email ?? 'brak właściciela' }}).
                    Bez karencji — kasujemy od razu.
                </p>

                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-stone-600">
                    <li>{{ $shop->products_count }} {{ trans_choice('produkt|produkty|produktów', $shop->products_count) }} ze zdjęciami</li>
                    <li>{{ $shop->orders_count }} {{ trans_choice('zamówienie|zamówienia|zamówień', $shop->orders_count) }} wraz z historią</li>
                    <li>{{ $shop->customers_count }} {{ trans_choice('klient|klienci|klientów', $shop->customers_count) }} i ich konta</li>
                    <li>{{ $shop->pages_count }} {{ trans_choice('strona informacyjna|strony informacyjne|stron informacyjnych', $shop->pages_count) }}</li>
                </ul>

                <p class="mt-3 text-sm text-stone-500">
                    Adres <span class="font-medium text-stone-700">{{ $shop->slug }}.{{ config('tenancy.central_domain') }}</span>
                    pozostanie zajęty przez {{ config('shop.deletion.slug_quarantine_days') }} dni, żeby nikt nie przejął
                    subdomeny, do której prowadzą stare linki.
                </p>

                <form method="POST" action="{{ route('administrator.shops.destroy', $shop) }}" class="mt-5">
                    @csrf
                    <label for="confirm_name" class="block text-sm font-medium text-stone-700">
                        Wpisz nazwę sklepu, żeby potwierdzić
                    </label>
                    <input type="text" id="confirm_name" name="confirm_name" autocomplete="off"
                        placeholder="{{ $shop->name }}"
                        class="mt-1.5 block w-full max-w-md rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-400">
                    @error('confirm_name')
                        <p class="mt-1.5 text-sm text-rose-700">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="mt-4 rounded-2xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:bg-rose-700">
                        Usuń sklep i konto właściciela
                    </button>
                </form>
            </div>
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✨</span>
                        <span><span class="text-stone-700">„Nadaj pakiet"</span> tylko wypełnia pola presetem. Zmiany zapisuje dopiero <span class="text-stone-700">„Zapisz"</span> — możesz wcześniej dopieścić pojedyncze opcje.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🧩</span>
                        <span>Każdy moduł włączasz <span class="text-stone-700">niezależnie od pakietu</span> — np. korespondencja seryjna dla dobrego klienta na Straganie.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔒</span>
                        <span>Zapis pisze <span class="text-stone-700">snapshot</span> tego sklepu. Przy odnowieniu uprawnienia zostają, a cena idzie za aktualnym cennikiem.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-emerald-600">🎁</span>
                        <span><span class="text-stone-700">Comped</span> = dostęp gratisowy, nie wygasa i omija auto-zejście.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
