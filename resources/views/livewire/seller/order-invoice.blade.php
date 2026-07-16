<div>
    @if ($visible)
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-stone-900">Faktura VAT</h2>
                <span class="shrink-0 text-2xl" aria-hidden="true">🧾</span>
            </div>

            @if ($order->hasInvoice())
                {{-- Gotowa: numer, data i bezpośredni link do publicznego PDF w Fakturowni. --}}
                <div class="mt-4 space-y-3">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
                        <p class="font-medium text-emerald-800">
                            Faktura @if (filled($order->invoice_number))<span class="font-semibold">nr {{ $order->invoice_number }}</span> @endif wystawiona.
                        </p>
                        @if ($order->invoiced_at)
                            <p class="mt-0.5 text-xs text-emerald-700">{{ $order->invoiced_at->format('d.m.Y, H:i') }}</p>
                        @endif
                    </div>

                    @if ($order->invoicePdfUrl())
                        <a href="{{ $order->invoicePdfUrl() }}" target="_blank" rel="noopener"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            <span aria-hidden="true">⬇</span> Pobierz fakturę VAT
                        </a>
                    @endif

                    <p class="text-xs text-stone-400">Klient dostał tę fakturę mailem z przyciskiem „Pobierz fakturę VAT".</p>
                </div>
            @elseif ($order->isInvoicePending())
                {{-- W przygotowaniu: job w tle. Odpytujemy co kilka sekund, aż stan drgnie
                     (kolejkę drenuje cron ~co minutę), więc karta sama przełączy się na „Pobierz". --}}
                <div class="mt-4" wire:poll.5s>
                    <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm">
                        <span class="mt-0.5 shrink-0" aria-hidden="true">⏳</span>
                        <div>
                            <p class="font-medium text-amber-900">Faktura jest w przygotowaniu…</p>
                            <p class="mt-0.5 text-xs text-amber-700">Wystawiamy ją w tle. Link do pobrania pojawi się tu za chwilę, a klient dostanie maila.</p>
                        </div>
                    </div>
                </div>
            @elseif ($order->invoiceFailed())
                {{-- Błąd ostatniej próby: bez FV, więc można ponowić (guard przepuszcza). --}}
                <div class="mt-4 space-y-3">
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm">
                        <p class="font-medium text-rose-900">Nie udało się wystawić faktury.</p>
                        <p class="mt-0.5 text-xs text-rose-700">Sprawdź dane konta i token w <a href="{{ route('seller.integrations.edit') }}" class="font-medium underline decoration-rose-300 underline-offset-2">Integracjach</a>, a potem spróbuj ponownie.</p>
                    </div>
                    <button type="button" wire:click="create"
                        wire:confirm="Ponowić próbę wystawienia faktury VAT do zamówienia #{{ $order->number }}?"
                        class="inline-flex w-full items-center justify-center rounded-2xl border border-stone-200 bg-white/70 px-4 py-2.5 text-sm font-medium text-stone-600 transition hover:bg-stone-50 hover:text-stone-800">
                        Spróbuj ponownie
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
