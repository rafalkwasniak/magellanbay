<div>
    @if ($returns->isNotEmpty())
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
            <h2 class="font-semibold text-stone-900">Zwroty</h2>
            <p class="mt-1 text-sm text-stone-500">
                Klient odstąpił od umowy. Zamówienie jest już pomniejszone o zwrócone pozycje —
                <span class="font-medium text-stone-600">stan magazynowy nie został zmieniony</span>, o powrocie towaru na półkę decydujesz sam.
            </p>

            <ul class="mt-5 space-y-4">
                @foreach ($returns as $return)
                    <li class="rounded-2xl border border-stone-200 bg-white/70 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-stone-900">
                                    Zgłoszenie z {{ $return->created_at->format('d.m.Y, H:i') }}
                                </p>
                                <p class="mt-0.5 text-xs text-stone-500">{{ $return->customer_name }} · {{ $return->customer_address }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-semibold tabular-nums text-stone-900">{{ \App\Support\Money::pln($return->refund_gross) }}</p>
                        </div>

                        <ul class="mt-3 space-y-1 text-sm text-stone-600">
                            @foreach ($return->items as $line)
                                <li class="flex justify-between gap-3">
                                    <span class="min-w-0 break-words">
                                        {{ $line->orderItem?->sale_unit->formatQuantity((float) $line->quantity) ?? $line->quantity }}
                                        × {{ $line->orderItem?->name ?? 'pozycja zamówienia' }}
                                    </span>
                                    <span class="shrink-0 tabular-nums">{{ \App\Support\Money::pln($line->refund_gross) }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @if (filled($return->bank_account))
                            <p class="mt-3 text-sm text-stone-600">
                                Numer konta do zwrotu: <span class="font-mono font-medium text-stone-800">{{ $return->bank_account }}</span>
                            </p>
                        @endif

                        @if (filled($return->note))
                            <p class="mt-3 whitespace-pre-line rounded-2xl bg-stone-50 p-3 text-sm text-stone-600">{{ $return->note }}</p>
                        @endif

                        {{-- Jedyna decyzja sprzedawcy: czy pieniądze już wróciły.
                             Zwrotu się nie „akceptuje" — odstąpienie działa z mocy prawa. --}}
                        <div class="mt-4">
                            @if ($return->isRefunded())
                                <p class="inline-flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-800">
                                    <span aria-hidden="true">✓</span> Pieniądze zwrócone {{ $return->refunded_at->format('d.m.Y') }}
                                </p>
                            @else
                                {{-- Termin z art. 32 ust. 1: 14 dni od otrzymania oświadczenia.
                                     Po jego upływie zmieniamy ton na czerwony — to już zaległość. --}}
                                <p class="text-sm {{ $return->isRefundOverdue() ? 'font-semibold text-rose-700' : 'text-stone-600' }}">
                                    @if ($return->isRefundOverdue())
                                        Termin zwrotu pieniędzy minął {{ $return->refundDeadline()->format('d.m.Y') }}
                                    @else
                                        Pieniądze oddaj do <span class="font-semibold text-stone-800">{{ $return->refundDeadline()->format('d.m.Y') }}</span>
                                    @endif
                                </p>
                                <button type="button" wire:click="markRefunded({{ $return->id }})" wire:loading.attr="disabled"
                                    class="mt-2 inline-flex items-center gap-2 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-105">
                                    <span aria-hidden="true">💸</span> Pieniądze zwrócone
                                </button>
                                <p class="mt-1.5 text-xs text-stone-400">Odnotuje, że rozliczyłeś ten zwrot. Przelew i ewentualną fakturę korygującą wystawiasz sam.</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($pendingTotal > 0)
                <p class="mt-5 rounded-2xl border px-4 py-3 text-sm {{ $pendingDeadline?->isPast() ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
                    Do zwrotu klientowi: <span class="font-semibold">{{ \App\Support\Money::pln($pendingTotal) }}</span>
                    @if ($pendingDeadline !== null)
                        @if ($pendingDeadline->isPast())
                            — termin minął <span class="font-semibold">{{ $pendingDeadline->format('d.m.Y') }}</span>.
                        @else
                            — <span class="font-semibold">do {{ $pendingDeadline->format('d.m.Y') }}</span> (14 dni od otrzymania oświadczenia).
                        @endif
                    @endif
                    Możesz wstrzymać wypłatę do chwili otrzymania towaru albo dowodu jego odesłania — ale to zawiesza wykonanie, nie przesuwa terminu.
                    @if ($order->isFullyReturned() && (float) $order->delivery_cost > 0)
                        Zwrot obejmuje całe zamówienie, więc oddaj też koszt dostawy ({{ \App\Support\Money::pln($order->delivery_cost) }}).
                    @endif
                </p>
            @endif
        </div>
    @endif
</div>
