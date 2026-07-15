<div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
    <h2 class="font-semibold text-stone-900">Napisz do klienta</h2>
    <p class="mt-1 text-xs text-stone-400">
        Do: <span class="font-medium text-stone-500">{{ trim($order->buyer_name.' '.$order->buyer_surname) }}</span> ({{ $order->buyer_email }})
    </p>

    @if ($sent)
        <p class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">
            Wiadomość trafiła do kolejki — klient dostanie ją w ciągu kilku minut.
        </p>
    @endif

    <div class="mt-4">
        <label for="message-body" class="sr-only">Treść wiadomości</label>
        <textarea
            id="message-body"
            wire:model.blur="body"
            rows="6"
            placeholder="Np. Dzień dobry, wazon z Pani zamówienia dojechał do nas w innym odcieniu niż na zdjęciu…"
            @class([
                'w-full rounded-2xl border bg-white/70 px-3 py-2 text-sm text-stone-700 placeholder:text-stone-400',
                'border-stone-200 focus:border-amber-400 focus:ring-amber-400' => ! $errors->has('body'),
                'border-rose-300 focus:border-rose-400 focus:ring-rose-400' => $errors->has('body'),
            ])
        ></textarea>

        @error('body')
            <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
        @enderror

        {{-- Sprzedawca nie musi przepisywać, czego dotyczy sprawa — mail sam niesie
             pozycje zamówienia. Mówimy to wprost, bo inaczej wkleiłby je z ręki. --}}
        <p class="mt-1 text-xs text-stone-400">
            Do wiadomości dołączymy pozycje zamówienia z kwotami. Odpowiedź klienta trafi na adres kontaktowy sklepu.
        </p>
    </div>

    <div class="mt-4 flex items-start gap-3">
        <input type="checkbox" id="copy-to-me" wire:model="copyToMe"
            class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20">
        <label for="copy-to-me" class="flex-1 cursor-pointer">
            <span class="block text-sm font-medium text-stone-800">Wyślij kopię do mnie</span>
            <span class="mt-0.5 block text-xs text-stone-500">Kopia trafi na Twój adres e-mail.</span>
        </label>
    </div>

    <div class="mt-4">
        <button
            type="button"
            wire:click="send"
            wire:loading.attr="disabled"
            wire:target="send"
            class="inline-flex items-center rounded-full bg-amber-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="send">Wyślij wiadomość</span>
            <span wire:loading wire:target="send">Wysyłam…</span>
        </button>
    </div>
</div>
