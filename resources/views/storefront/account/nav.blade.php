{{-- Zakładki panelu klienta. $active: 'orders' | 'data'. --}}
<nav class="mt-6 flex flex-wrap items-center gap-2 text-sm font-medium">
    <a href="/moje-konto" wire:navigate
        class="rounded-full px-4 py-2 transition {{ $active === 'orders' ? 'st-btn' : 'st-border border hover:brightness-95' }}">Zamówienia</a>
    <a href="/moje-konto/dane" wire:navigate
        class="rounded-full px-4 py-2 transition {{ $active === 'data' ? 'st-btn' : 'st-border border hover:brightness-95' }}">Dane i hasło</a>
    <form method="POST" action="/wyloguj" class="ml-auto">
        @csrf
        <button type="submit" class="st-border rounded-full border px-4 py-2 transition hover:brightness-95">Wyloguj się</button>
    </form>
</nav>
