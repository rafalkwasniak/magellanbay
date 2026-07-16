<div>
    @if ($visible)
        <div class="mt-4 border-t border-stone-100 pt-4">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Faktura VAT</p>

            @if ($order->hasInvoice())
                {{-- Gotowa: numer, data i bezpośredni link do publicznego PDF w Fakturowni. --}}
                <div class="mt-2 space-y-3">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-sm">
                        <p class="font-medium text-emerald-800">
                            Faktura @if (filled($order->invoice_number))<span class="font-semibold">nr {{ $order->invoice_number }}</span> @endif wystawiona.
                        </p>
                        @if ($order->invoiced_at)
                            <p class="mt-0.5 text-xs text-emerald-700">{{ $order->invoiced_at->format('d.m.Y, H:i') }}</p>
                        @endif
                    </div>

                    @if ($order->invoicePdfUrl())
                        <a href="{{ $order->invoicePdfUrl() }}" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-105">
                            <span aria-hidden="true">⬇</span> Pobierz fakturę VAT
                        </a>
                    @endif

                    <p class="text-xs text-stone-400">Klient dostał tę fakturę mailem z przyciskiem „Pobierz fakturę VAT".</p>
                </div>
            @elseif ($order->isInvoicePending())
                {{-- W przygotowaniu: job w tle. Odpytujemy co kilka sekund, aż stan drgnie
                     (kolejkę drenuje cron ~co minutę), więc sekcja sama pokaże „Pobierz". --}}
                <div class="mt-2" wire:poll.5s>
                    <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm">
                        <span class="mt-0.5 shrink-0" aria-hidden="true">⏳</span>
                        <div>
                            <p class="font-medium text-amber-900">Faktura jest w przygotowaniu…</p>
                            <p class="mt-0.5 text-xs text-amber-700">Wystawiamy ją w tle. Link do pobrania pojawi się tu za chwilę, a klient dostanie maila.</p>
                        </div>
                    </div>
                </div>
            @elseif ($order->invoiceFailed())
                {{-- Błąd ostatniej próby: bez FV, więc można ponowić (guard przepuszcza). --}}
                <div class="mt-2 space-y-3">
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-3 text-sm">
                        <p class="font-medium text-rose-900">Nie udało się wystawić faktury.</p>
                        <p class="mt-0.5 text-xs text-rose-700">Sprawdź dane konta i token w <a href="{{ route('seller.integrations.edit') }}" class="font-medium underline decoration-rose-300 underline-offset-2">Integracjach</a>, a potem spróbuj ponownie.</p>
                    </div>
                    <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create"
                        class="inline-flex items-center rounded-2xl border border-stone-200 bg-white/70 px-4 py-2 text-sm font-medium text-stone-600 transition hover:bg-stone-50 hover:text-stone-800">
                        <span wire:loading.remove wire:target="create">Spróbuj ponownie</span>
                        <span wire:loading wire:target="create">Zlecam…</span>
                    </button>
                </div>
            @elseif ($confirming)
                {{-- Potwierdzenie w miejscu: mówi wprost, co się stanie. Realna FV idzie
                     do KSeF, więc świadoma decyzja, ale ton ciepły (nie alarmowy). --}}
                <div class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="font-medium text-amber-900">Wystawić fakturę VAT do zamówienia #{{ $order->number }}?</p>
                    <ul class="mt-2 space-y-1 text-xs text-amber-800">
                        <li>• Fakturę utworzymy w Twojej <span class="font-medium">Fakturowni</span>.</li>
                        <li>• Klient dostanie ją mailem z linkiem do pobrania PDF.</li>
                        <li>• Fakturę wystawiasz raz — potem będzie tu do pobrania.</li>
                        @unless ($order->is_company)
                            <li>• Klient nie podał danych firmy — będzie to <span class="font-medium">faktura imienna</span> na dane kupującego (bez NIP).</li>
                        @endunless
                    </ul>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create"
                            class="inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-105">
                            <span aria-hidden="true">🧾</span>
                            <span wire:loading.remove wire:target="create">Tak, wystaw fakturę</span>
                            <span wire:loading wire:target="create">Zlecam…</span>
                        </button>
                        <button type="button" wire:click="dismiss"
                            class="inline-flex items-center rounded-full border border-stone-200 bg-white px-4 py-1.5 text-sm font-medium text-stone-600 transition hover:bg-stone-50">
                            Nie, wróć
                        </button>
                    </div>
                </div>
            @else
                {{-- Można wystawić: przycisk otwiera potwierdzenie w miejscu. --}}
                <div class="mt-2">
                    <button type="button" wire:click="askCreate"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-105">
                        <span aria-hidden="true">🧾</span> Stwórz fakturę VAT
                    </button>
                    @if ($order->is_company)
                        <p class="mt-1.5 text-xs text-stone-400">Powstanie w Twojej Fakturowni; klient dostanie ją mailem z linkiem do PDF.</p>
                    @else
                        <p class="mt-1.5 text-xs text-stone-400">Klient nie podał danych do faktury — zostanie wystawiona faktura imienna na dane kupującego.</p>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
