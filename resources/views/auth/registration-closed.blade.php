<x-layouts.guest title="Rejestracja chwilowo zamknięta">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-2xl">🚧</span>

        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-stone-900">Rejestracja chwilowo zamknięta</h1>

        <p class="mt-3 text-stone-500">
            {{ $notice ?? 'Pracujemy nad Kramio i na chwilę wstrzymaliśmy zakładanie nowych sklepów. Zajrzyj za jakiś czas — wrócimy szybko.' }}
        </p>

        {{-- Logowanie zostaje otwarte: zamknięcie rejestracji nie może odciąć
             sprzedawców, którzy już mają sklepy i właśnie realizują zamówienia. --}}
        <p class="mt-6 text-sm text-stone-500">
            Masz już konto?
            <a href="{{ route('login') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">Zaloguj się</a>
            — Twój sklep działa normalnie.
        </p>
    </div>
</x-layouts.guest>
