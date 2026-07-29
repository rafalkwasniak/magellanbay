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
        {{-- Wysłane = zamknięte. Wiadomość jest już w skrzynkach klientów,
             więc zostaje jako zapis historyczny. --}}
        <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
            <p class="font-medium text-emerald-800">
                ✓ Wysłano {{ $mailing->sent_at->format('d.m.Y, H:i') }}
                do {{ $mailing->recipients_count }} {{ $mailing->recipients_count === 1 ? 'klienta' : 'klientów' }}.
            </p>
            <p class="mt-1 text-xs text-emerald-700">
                Tej wiadomości nie da się wysłać ponownie ani zmienić. Aby napisać do klientów jeszcze raz, utwórz nową.
            </p>
        </div>
    @else
        <p class="mt-1 text-sm text-stone-500">
            Najpierw wyślij próbkę do siebie i sprawdź, jak wiadomość wygląda w skrzynce. Do klientów wysyłasz ją raz.
        </p>

        {{-- Próbka: zawsze dostępna, nie zużywa karencji. --}}
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
            <p class="mt-2 text-xs text-stone-400">Próbek możesz wysłać dowolnie wiele — nie liczą się do limitu i nie trafiają do klientów.</p>
        </div>

        {{-- Wysyłka właściwa. --}}
        <div class="mt-5 border-t border-stone-100 pt-4">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Do klientów</p>

            @if ($blockedUntil !== null)
                <div class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm">
                    <p class="font-medium text-amber-900">Kolejną wiadomość wyślesz od {{ $blockedUntil->format('d.m.Y') }}</p>
                    <p class="mt-1 text-xs text-amber-800">
                        Klienci dostają od Ciebie najwyżej jedną wiadomość na {{ config('bulk_mail.cooldown_days') }} dni.
                        Szkic poczeka — możesz go dopracowywać i testować do woli.
                    </p>
                </div>
            @elseif ($recipients === 0)
                <div class="mt-2 rounded-2xl border border-stone-200 bg-stone-50 p-4 text-sm">
                    <p class="font-medium text-stone-700">Nie masz jeszcze komu wysłać</p>
                    <p class="mt-1 text-xs text-stone-500">
                        Nikt z Twoich klientów nie zaznaczył zgody na wiadomości. Zgodę zaznacza się przy zakładaniu konta
                        w Twoim sklepie albo później, w profilu klienta. Próbki do siebie możesz wysyłać już teraz.
                    </p>
                </div>
            @elseif ($confirming)
                {{-- Potwierdzenie w miejscu: mówi wprost, co się stanie. Operacji
                     nie da się cofnąć, więc decyzja musi być świadoma. --}}
                <div class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="font-medium text-amber-900">Wysłać tę wiadomość do {{ $recipients }} {{ $recipients === 1 ? 'klienta' : 'klientów' }}?</p>
                    <ul class="mt-2 space-y-1 text-xs text-amber-800">
                        <li>• Wiadomość trafi tylko do klientów, którzy zgodzili się na jej otrzymywanie.</li>
                        <li>• Wysyłka jest jednorazowa — tej treści nie da się już zmienić ani wysłać ponownie.</li>
                        <li>• Kolejną wiadomość wyślesz najwcześniej za {{ config('bulk_mail.cooldown_days') }} dni.</li>
                        <li>• Maile wychodzą po {{ config('bulk_mail.per_minute') }} na minutę, więc ostatni dotrze za jakiś czas.</li>
                    </ul>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="send" wire:loading.attr="disabled" wire:target="send"
                            class="inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-105">
                            <span aria-hidden="true">📣</span>
                            <span wire:loading.remove wire:target="send">Tak, wyślij do klientów</span>
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
                        <span aria-hidden="true">📣</span> Wyślij do klientów ({{ $recipients }})
                    </button>
                    <p class="mt-2 text-xs text-stone-400">
                        Trafi do {{ $recipients }} {{ $recipients === 1 ? 'klienta, który zgodził się' : 'klientów, którzy zgodzili się' }} na wiadomości od Twojego sklepu.
                    </p>
                </div>
            @endif
        </div>
    @endif
</div>
