{{-- Potwierdzenie wypisu z korespondencji seryjnej. Wypis nastąpił już przy
     wejściu na stronę — to jest ekran potwierdzenia, nie formularz. Układ jak
     w rejestracji: nagłówek na pełnej szerokości, treść w wąskiej kolumnie. --}}
<x-layouts.storefront :shop="$shop" title="Wiadomości od sklepu" :noindex="true">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Wiadomości od sklepu'],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">
            {{ ($restored ?? false) ? 'Znów będziesz dostawać wiadomości' : 'Wypisaliśmy Cię z wiadomości' }}
        </h1>

        <div class="st-border mt-8 border-t pt-8">
            <div class="mx-auto max-w-md">
                @if ($restored ?? false)
                    <div class="st-card st-border rounded-2xl border p-6">
                        <p class="font-semibold text-emerald-600">✓ Zgoda przywrócona</p>
                        <p class="mt-2 text-sm opacity-80">
                            Będziesz znów otrzymywać wiadomości o nowościach i promocjach od sklepu <span class="font-medium">{{ $shop->name }}</span>.
                        </p>
                    </div>
                @else
                    <div class="st-card st-border rounded-2xl border p-6">
                        <p class="font-semibold">Gotowe — to była ostatnia taka wiadomość</p>
                        <p class="mt-2 text-sm opacity-80">
                            Sklep <span class="font-medium">{{ $shop->name }}</span> nie wyśle Ci już wiadomości o nowościach ani promocjach.
                        </p>
                        <p class="mt-3 text-sm opacity-70">
                            Nadal będziesz dostawać e-maile dotyczące swoich zamówień — potwierdzenia i zmiany statusu.
                            Tych nie da się wyłączyć, bo są potrzebne do obsługi zakupów.
                        </p>
                    </div>

                    {{-- Kliknięcie przez pomyłkę (albo przez skaner poczty) naprawia
                         jeden przycisk — dlatego wolno nam wypisywać od razu. --}}
                    <form method="POST" action="{{ $restoreUrl }}" class="mt-6 text-center">
                        @csrf
                        <p class="text-sm opacity-70">Kliknięcie było pomyłką?</p>
                        <button type="submit" class="st-btn mt-3 inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">
                            Przywróć wiadomości
                        </button>
                    </form>
                @endif

                <p class="mt-8 text-center text-sm opacity-70">
                    Ustawienia zmienisz też w <a href="/moje-konto/dane" class="st-brand font-medium underline underline-offset-2">swoim koncie</a>.
                </p>
            </div>
        </div>
    </main>
</x-layouts.storefront>
