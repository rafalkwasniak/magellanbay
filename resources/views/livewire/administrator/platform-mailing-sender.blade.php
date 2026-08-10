<div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
    <h2 class="font-semibold text-stone-900">Wysyłka</h2>

    {{-- Komunikaty NAD gałęzią „wysłane": po udanej wysyłce widok przełącza się
         na podsumowanie, a potwierdzenie akcji nie może wtedy zniknąć. --}}
    @if ($sentMessage)
        <p class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $sentMessage }}</p>
    @endif

    @if ($error)
        <p class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $error }}</p>
    @endif

    @if ($mailing->isSent())
        @if ($delivering)
            {{-- Wysyłka w toku: kolejkę opróżnia cron paczkami po `per_minute`,
                 więc odpytujemy co kilka sekund, aż licznik dobije do końca. --}}
            <div class="mt-4" wire:poll.5s>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm">
                    {{-- `delivered + 1` = numer wiadomości W TOKU. „Wysyłam 0 z 1"
                         czytałoby się jak awaria, bo zerowej nikt nie wysyła. --}}
                    <p class="font-medium text-amber-900">{{ 'Wysyłam '.min($delivered + 1, $total).' z '.$total.'…' }}</p>
                    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-amber-100">
                        <div class="h-full rounded-full bg-gradient-to-br from-amber-500 to-rose-500" style="width: {{ $total > 0 ? round($delivered / $total * 100) : 0 }}%"></div>
                    </div>
                    <p class="mt-2 text-xs text-amber-800">
                        Maile wychodzą po {{ config('platform_mail.per_minute') }} na minutę, żeby nie obciążać serwera.
                        Możesz zamknąć tę stronę — wysyłka idzie dalej w tle.
                    </p>
                </div>
            </div>
        @else
            <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
                <p class="font-medium text-emerald-800">
                    ✓ Wysłano {{ $delivered }} {{ trans_choice('{1}wiadomość|[2,4]wiadomości|[5,*]wiadomości', $delivered) }}
                    — {{ $mailing->sent_at->format('d.m.Y, H:i') }}.
                </p>
                @if ($failed > 0)
                    <p class="mt-1 text-xs text-rose-700">
                        {{ $failed }} {{ trans_choice('{1}wiadomość nie dotarła|[2,4]wiadomości nie dotarły|[5,*]wiadomości nie dotarło', $failed) }}
                        — adresy mogły być nieaktualne.
                    </p>
                @endif
                <p class="mt-1 text-xs text-emerald-700">
                    Tej wiadomości nie da się wysłać ponownie ani zmienić. Kolejną możesz napisać od razu — nie ma żadnej karencji.
                </p>
            </div>
        @endif
    @else
        <p class="mt-1 text-sm text-stone-500">
            Najpierw wyślij próbkę do siebie i sprawdź, jak wiadomość wygląda w skrzynce. Do sprzedawców wysyłasz ją raz.
        </p>

        {{-- Próbka: zawsze dostępna. --}}
        <div class="mt-5 border-t border-stone-100 pt-4">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Próbka do siebie</p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <button type="button" wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest"
                    class="inline-flex items-center gap-2 rounded-2xl border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-100">
                    <span aria-hidden="true">✉️</span>
                    <span wire:loading.remove wire:target="sendTest">Wyślij próbkę na {{ auth()->user()->email }}</span>
                    <span wire:loading wire:target="sendTest">Wysyłam…</span>
                </button>
                @if ($mailing->test_sends > 0)
                    <span class="text-xs text-stone-400">
                        Sprawdzono {{ $mailing->test_sends }} {{ $mailing->test_sends === 1 ? 'raz' : 'razy' }}
                    </span>
                @endif
            </div>
            <p class="mt-2 text-xs text-stone-400">Próbek możesz wysłać dowolnie wiele — nie trafiają do sprzedawców.</p>
        </div>

        {{-- Wysyłka właściwa. --}}
        <div class="mt-5 border-t border-stone-100 pt-4">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Do sprzedawców</p>

            @if ($eligible === 0)
                <div class="mt-2 rounded-2xl border border-stone-200 bg-stone-50 p-4 text-sm">
                    <p class="font-medium text-stone-700">Nie masz jeszcze komu wysłać</p>
                    <p class="mt-1 text-xs text-stone-500">
                        Żaden sprzedawca nie zgodził się na wiadomości handlowe od Kramio. Próbki do siebie możesz wysyłać już teraz.
                    </p>
                </div>
            @elseif ($recipients === 0)
                <div class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm">
                    <p class="font-medium text-amber-900">Nikt nie jest zaznaczony</p>
                    <p class="mt-1 text-xs text-amber-800">
                        Wybierz odbiorców na liście obok. Ze zgodą jest ich {{ $eligible }}.
                    </p>
                </div>
            @elseif ($confirming)
                {{-- Potwierdzenie w miejscu: mówi wprost, co się stanie. Operacji
                     nie da się cofnąć, więc decyzja musi być świadoma. --}}
                <div class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="font-medium text-amber-900">Wysłać tę wiadomość do {{ $recipients }} {{ $recipients === 1 ? 'sprzedawcy' : 'sprzedawców' }}?</p>
                    <ul class="mt-2 space-y-1 text-xs text-amber-800">
                        <li>• Trafi tylko do zaznaczonych, którzy mają aktywną zgodę na treści handlowe.</li>
                        <li>• Wysyłka jest jednorazowa — tej treści nie da się już zmienić ani wysłać ponownie.</li>
                        <li>• Kolejną wiadomość możesz napisać i wysłać od razu, bez karencji.</li>
                        <li>• Maile wychodzą po {{ config('platform_mail.per_minute') }} na minutę.</li>
                    </ul>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="send" wire:loading.attr="disabled" wire:target="send"
                            class="inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-105">
                            <span aria-hidden="true">📣</span>
                            <span wire:loading.remove wire:target="send">Tak, wyślij do sprzedawców</span>
                            <span wire:loading wire:target="send">Wysyłam…</span>
                        </button>
                        <button type="button" wire:click="dismiss"
                            class="inline-flex items-center rounded-full border border-stone-200 bg-white px-4 py-1.5 text-sm font-medium text-stone-600 transition hover:bg-stone-50">
                            Nie, wróć
                        </button>
                    </div>
                </div>
            @else
                <div class="mt-2">
                    <button type="button" wire:click="askSend"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-105">
                        <span aria-hidden="true">📣</span> Wyślij do sprzedawców ({{ $recipients }})
                    </button>
                    <p class="mt-2 text-xs text-stone-400">
                        @if ($recipients < $eligible)
                            Zaznaczyłeś {{ $recipients }} z {{ $eligible }} sprzedawców ze zgodą.
                        @else
                            Trafi do wszystkich {{ $eligible }} sprzedawców ze zgodą na wiadomości od Kramio.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    @endif
</div>
