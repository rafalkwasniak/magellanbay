<x-layouts.panel title="Zamówienia">
    <x-slot:heading>Zamówienia</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
    {{-- Główna kolumna: lista zamówień --}}
    <div class="lg:col-span-8">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-stone-900">Twoje zamówienia</h2>
                <p class="mt-1 text-sm text-stone-500">
                    @if ($orders && $orders->total() > 0)
                        {{ $orders->total() }} {{ trans_choice('zamówienie|zamówienia|zamówień', $orders->total()) }}
                    @else
                        Tu pojawią się zamówienia złożone w Twoim sklepie.
                    @endif
                </p>
            </div>
        </div>

        @if (! $orders || $orders->total() === 0)
            <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">📦</span>
                <p class="mt-4 font-medium text-stone-700">Nie masz jeszcze zamówień</p>
                <p class="mt-1 text-sm text-stone-500">Gdy klient złoży zamówienie, zobaczysz je tutaj — z danymi i statusem.</p>
            </div>
        @else
            <div class="mt-6 space-y-2">
                @foreach ($orders as $order)
                    <a href="{{ route('seller.orders.show', $order) }}"
                        class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white/80 px-4 py-3.5 shadow-sm transition hover:border-amber-300 hover:shadow-md">
                        {{-- Lewa: numer, kupujący, pozycje · data --}}
                        <div class="min-w-0">
                            <p class="font-semibold text-stone-900">Zamówienie #{{ $order->number }}</p>
                            <p class="mt-0.5 truncate text-sm font-medium text-stone-700">
                                {{ $order->is_company && filled($order->company_name) ? $order->company_name : trim($order->buyer_name.' '.$order->buyer_surname) }}
                            </p>
                            <p class="text-xs text-stone-400">
                                {{ $order->items_count }} {{ trans_choice('pozycja|pozycje|pozycji', $order->items_count) }} · {{ $order->created_at->format('d.m.Y, H:i') }}
                            </p>
                        </div>
                        {{-- Prawa: wartość + aktualny status --}}
                        <div class="flex shrink-0 flex-col items-end gap-1.5 text-right">
                            <span class="font-bold tabular-nums text-stone-900">{{ \App\Support\Money::pln($order->total_gross) }}</span>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $order->status->badgeClasses() }}">
                                {{ $order->status->label() }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>

            @if ($orders->hasPages())
                <div class="mt-6">
                    {{ $orders->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
    </div>
    </div>

    {{-- Kolumna pomocnicza: miejsce na filtry (wyszukiwarka, daty, status) — wkrótce --}}
    <aside class="lg:col-span-4 space-y-6">
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-stone-900">Filtry</h2>
                <span class="rounded-full bg-stone-200/70 px-2 py-0.5 text-[10px] font-medium text-stone-500">wkrótce</span>
            </div>
            <p class="mt-2 text-sm text-stone-500">Wyszukiwarka zamówień oraz filtry (zakres dat, status) pojawią się tutaj.</p>
        </div>
    </aside>
    </div>
</x-layouts.panel>
