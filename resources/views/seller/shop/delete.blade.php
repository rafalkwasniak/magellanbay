<x-layouts.panel title="Usunięcie sklepu">
    <x-slot:actions>
        <a href="{{ route('seller.shop.edit') }}"
            class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur transition hover:bg-white">
            ← Wróć do sklepu
        </a>
    </x-slot:actions>

    <div class="max-w-2xl">
        @if ($shop->deletion_scheduled_at)
            {{-- Stan „już zlecone": jedyne, co ma tu teraz sens, to droga odwrotu.
                 Rachunek strat schodzi z ekranu, bo decyzja jest już podjęta. --}}
            <div class="rounded-3xl border border-rose-300 bg-rose-50 p-6">
                <h2 class="text-lg font-semibold text-rose-900">
                    Usuniemy Twój sklep {{ $shop->deletion_scheduled_at->format('d.m.Y') }}
                </h2>
                <p class="mt-2 text-sm text-rose-800">
                    Sklep <span class="font-medium">{{ $shop->name }}</span> jest już niewidoczny dla klientów —
                    pod adresem {{ $shop->host() }} nikt niczego nie zamówi. Tego dnia usuniemy go razem z Twoim
                    kontem, trwale i bez możliwości odzyskania.
                </p>
                <p class="mt-3 text-sm text-rose-800">
                    Do tego dnia wszystko wraca jednym kliknięciem. Nic jeszcze nie zostało skasowane:
                    produkty, zamówienia i klienci czekają nietknięci.
                </p>

                <form method="POST" action="{{ route('seller.deletion.cancel') }}" class="mt-5">
                    @csrf
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        Zatrzymaj usunięcie
                    </button>
                </form>
            </div>
        @else
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="text-lg font-semibold text-stone-900">
                    Zamierzasz usunąć sklep {{ $shop->name }} i swoje konto w Kramio
                </h2>
                <p class="mt-2 text-sm text-stone-600">
                    Konto zniknie razem ze sklepem — w Kramio jedno nie istnieje bez drugiego.
                </p>

                <p class="mt-5 text-sm font-medium text-stone-800">Bezpowrotnie znikną:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-stone-600">
                    <li>{{ $shop->products_count }} {{ trans_choice('produkt|produkty|produktów', $shop->products_count) }} razem ze zdjęciami</li>
                    <li>{{ $shop->orders_count }} {{ trans_choice('zamówienie|zamówienia|zamówień', $shop->orders_count) }} i cała historia sprzedaży</li>
                    <li>{{ $shop->customers_count }} {{ trans_choice('klient|klienci|klientów', $shop->customers_count) }} wraz z ich kontami w Twoim sklepie</li>
                    <li>{{ $shop->pages_count }} {{ trans_choice('strona informacyjna|strony informacyjne|stron informacyjnych', $shop->pages_count) }}</li>
                </ul>

                <p class="mt-4 text-sm text-stone-600">
                    Faktury wystawione przez Twoje konto w systemie fakturowym zostają tam, gdzie były —
                    to osobny system i usunięcie sklepu w Kramio ich nie dotyczy.
                </p>

                @if ($shop->subscriptionActive() && ! $shop->comped && $shop->subscription_ends_at)
                    <div class="mt-5 rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                        <p class="font-medium">Masz opłacony pakiet {{ $shop->packageName() }} do {{ $shop->subscription_ends_at->format('d.m.Y') }}</p>
                        <p class="mt-1 text-xs text-rose-800">
                            Opłata za niewykorzystany okres nie zostanie zwrócona.
                        </p>
                    </div>
                @endif

                <p class="mt-5 text-sm text-stone-600">
                    Adres <span class="font-medium text-stone-800">{{ $shop->host() }}</span> pozostanie zajęty
                    jeszcze przez {{ config('shop.deletion.slug_quarantine_days') }} dni — dzięki temu nikt nie przejmie
                    adresu, który znają Twoi klienci.
                </p>

                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-stone-700">
                    Sklep zniknie z sieci od razu, ale skasujemy go dopiero za
                    {{ config('shop.deletion.grace_days') }} {{ trans_choice('{1}dzień|[2,4]dni|[5,*]dni', config('shop.deletion.grace_days')) }}.
                    Do tego czasu możesz wszystko cofnąć jednym kliknięciem.
                </div>

                <form method="POST" action="{{ route('seller.deletion.store') }}" class="mt-6 space-y-4">
                    @csrf

                    <div>
                        <label for="confirm_name" class="block text-sm font-medium text-stone-700">
                            Wpisz nazwę swojego sklepu
                        </label>
                        <input type="text" id="confirm_name" name="confirm_name" autocomplete="off"
                            placeholder="{{ $shop->name }}"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-400">
                        @error('confirm_name')
                            <p class="mt-1.5 text-sm text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="current_password" class="block text-sm font-medium text-stone-700">
                            Podaj swoje hasło
                        </label>
                        <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-400">
                        @error('current_password')
                            <p class="mt-1.5 text-sm text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-4 pt-1">
                        <button type="submit"
                            class="rounded-2xl bg-rose-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-400">
                            Usuń mój sklep
                        </button>
                        <a href="{{ route('seller.shop.edit') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">
                            Anuluj
                        </a>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-layouts.panel>
