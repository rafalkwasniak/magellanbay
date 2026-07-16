<div>
    @if ($showButton)
        <div class="mt-4 border-t border-stone-100 pt-4">
            <button type="button" wire:click="create"
                wire:confirm="Wystawić fakturę VAT do zamówienia #{{ $order->number }}? Zostanie utworzona w Fakturowni i wysłana klientowi mailem."
                class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-105">
                <span aria-hidden="true">🧾</span> Stwórz fakturę VAT
            </button>
            <p class="mt-1.5 text-xs text-stone-400">Powstanie w Twojej Fakturowni; klient dostanie ją mailem z linkiem do PDF.</p>
        </div>
    @endif
</div>
