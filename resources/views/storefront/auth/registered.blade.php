<x-layouts.storefront :shop="$shop" title="Sprawdź skrzynkę">
    <main class="mx-auto max-w-md px-6 pt-10 pb-16 text-center">
        <h1 class="st-brand font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Sprawdź skrzynkę</h1>

        <div class="st-card st-border mt-8 rounded-2xl border p-6 text-left text-sm leading-relaxed opacity-80">
            <p>
                Wysłaliśmy link aktywacyjny@if (filled($email)) na <strong class="st-brand">{{ $email }}</strong>@endif.
            </p>
            <p class="mt-3">
                Kliknij w niego, ustaw hasło i gotowe — od razu będziesz zalogowany. Link jest ważny przez 24 godziny.
            </p>
            <p class="mt-3 opacity-70">
                Nie widzisz wiadomości? Sprawdź folder ze spamem lub ofertami.
            </p>
        </div>

        <a href="/" wire:navigate class="st-brand mt-6 inline-block text-sm underline underline-offset-2">Wróć do sklepu</a>
    </main>
</x-layouts.storefront>
