<div>
    @if ($limited && ! $compact)
        <p class="mb-2 text-sm opacity-70">
            Dostępne: <span class="font-semibold">{{ $stock }}</span> szt.
            @if ($inCart > 0)
                <span class="opacity-60">(w koszyku: {{ $inCart }})</span>
            @endif
        </p>
    @endif

    @php($pad = $compact ? 'px-5 py-2.5' : 'px-8 py-3')

    @if ($canAdd)
        <button type="button" wire:click="add"
            x-data="{ done: false }"
            x-on:click="done = true; setTimeout(() => done = false, 1600)"
            class="st-btn inline-flex items-center justify-center gap-2 rounded-full {{ $pad }} text-sm font-semibold shadow-sm transition hover:brightness-105">
            <span wire:loading.remove wire:target="add">
                <span x-show="!done">Do koszyka</span>
                <span x-show="done" x-cloak>Dodano&nbsp;✓</span>
            </span>
            <span wire:loading wire:target="add" x-cloak>Dodaję…</span>
        </button>
    @elseif ($active && $limited && $inCart > 0)
        {{-- Wszystkie dostępne sztuki są już w koszyku. --}}
        <span class="inline-flex items-center justify-center gap-2 rounded-full border st-border {{ $compact ? 'px-4 py-2.5' : 'px-6 py-3' }} text-sm font-medium opacity-80">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            {{ $compact ? 'Masz maksimum' : 'Masz w koszyku wszystkie dostępne sztuki' }}
        </span>
    @else
        <span class="inline-flex items-center justify-center rounded-full border st-border {{ $compact ? 'px-4 py-2.5' : 'px-6 py-3' }} text-sm font-medium opacity-60">
            Chwilowo niedostępny
        </span>
    @endif
</div>
