<div>
    {{-- Koszyk pojawia się dopiero, gdy coś w nim jest — pusty nie zaśmieca
         winiety. Root <div> zostaje jako kotwica Livewire; na zdarzenie
         `cart-updated` komponent przerenderuje i koszyk się „wsuwa". --}}
    @if ($count > 0)
        <a href="/koszyk" wire:navigate
            class="st-border relative inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition hover:brightness-95"
            aria-label="Koszyk, sztuk: {{ $count }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-5 w-5" aria-hidden="true">
                <circle cx="9" cy="20" r="1.4" fill="currentColor" stroke="none"/>
                <circle cx="18" cy="20" r="1.4" fill="currentColor" stroke="none"/>
                <path d="M2.5 3h2l2.2 11.2a1.5 1.5 0 0 0 1.5 1.2h8.3a1.5 1.5 0 0 0 1.5-1.2L21 7H6"/>
            </svg>
            <span class="hidden sm:inline">Koszyk</span>
            <span class="st-btn ml-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-xs font-bold tabular-nums">{{ $count }}</span>
        </a>
    @endif
</div>
