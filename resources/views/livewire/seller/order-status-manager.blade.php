<div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
    <div class="flex items-center justify-between gap-3">
        <h2 class="font-semibold text-stone-900">Status</h2>
        <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
    </div>

    {{-- Oś czasu: „Złożone" (created_at zamówienia) + kolejne przejścia. --}}
    <ol class="mt-4 space-y-3 border-l border-stone-200 pl-4">
        <li class="relative">
            <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full bg-stone-300"></span>
            <p class="text-sm font-medium text-stone-700">Złożone</p>
            <p class="text-xs text-stone-400">{{ $order->created_at->format('d.m.Y, H:i') }}</p>
        </li>
        @foreach ($events as $event)
            <li class="relative">
                <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full bg-amber-400"></span>
                <p class="text-sm font-medium text-stone-700">{{ $event->to_status->label() }}</p>
                <p class="text-xs text-stone-400">{{ $event->created_at->format('d.m.Y, H:i') }}</p>
                @if (filled($event->note))
                    <p class="mt-0.5 text-xs italic text-stone-500">„{{ $event->note }}"</p>
                @endif
            </li>
        @endforeach
    </ol>

    {{-- Zmiana statusu: wszystkie od razu, od najbardziej prawdopodobnych;
         zalecany pierwszy i wyróżniony, „Anuluj" osobno na końcu. --}}
    <div class="mt-5 border-t border-stone-100 pt-4">
        <label for="status-note" class="text-xs font-medium uppercase tracking-wide text-stone-400">Zmień status</label>

        <textarea
            id="status-note"
            wire:model="note"
            rows="2"
            placeholder="Notatka do tej zmiany (opcjonalnie)"
            class="mt-2 w-full rounded-2xl border border-stone-200 bg-white/70 px-3 py-2 text-sm text-stone-700 placeholder:text-stone-400 focus:border-amber-400 focus:ring-amber-400"
        ></textarea>

        @if (count($likely) > 0)
            <p class="mt-3 text-xs text-stone-400">Zalecany kolejny krok wyróżniony.</p>
            <div class="mt-1.5 flex flex-wrap gap-2">
                @foreach ($likely as $i => $next)
                    <button
                        type="button"
                        wire:click="changeTo('{{ $next->value }}')"
                        @class([
                            'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium transition',
                            'bg-amber-600 text-white shadow-sm hover:bg-amber-700' => $i === 0,
                            'border border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' => $i !== 0,
                        ])
                    >
                        @if ($i === 0)<span aria-hidden="true">✓</span>@endif
                        {{ $next->label() }}
                    </button>
                @endforeach
            </div>
        @endif

        @if (count($others) > 0)
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($others as $other)
                    <button
                        type="button"
                        wire:click="changeTo('{{ $other->value }}')"
                        class="inline-flex items-center rounded-full border border-stone-200 bg-white/70 px-3 py-1 text-sm text-stone-500 transition hover:bg-stone-50 hover:text-stone-700"
                    >{{ $other->label() }}</button>
                @endforeach
            </div>
        @endif

        @if ($canCancel)
            <div class="mt-3 border-t border-stone-100 pt-3">
                <button
                    type="button"
                    wire:click="changeTo('{{ \App\Enums\OrderStatus::Cancelled->value }}')"
                    wire:confirm="Na pewno anulować to zamówienie?"
                    class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-4 py-1.5 text-sm font-medium text-rose-700 transition hover:bg-rose-100"
                >Anuluj zamówienie</button>
            </div>
        @endif
    </div>
</div>
