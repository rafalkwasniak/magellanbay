<x-storefront.account-shell :shop="$shop" active="orders" heading="Zamówienia" :crumbs="[
    ['label' => $shop->name, 'url' => '/'],
    ['label' => 'Moje konto', 'url' => '/moje-konto'],
    ['label' => 'Zamówienia'],
]">
    @if ($orders->isEmpty())
        <div class="st-card st-border rounded-3xl border p-10 text-center">
            <p class="opacity-70">Nie masz jeszcze zamówień.</p>
            <a href="/produkty" wire:navigate class="st-brand mt-2 inline-block text-sm underline underline-offset-2">Przejdź do produktów</a>
        </div>
    @else
        <ul class="space-y-3">
            @foreach ($orders as $order)
                <li>
                    <a href="/moje-konto/zamowienia/{{ $order->id }}?powrot={{ urlencode(request()->getRequestUri()) }}" wire:navigate
                        class="st-card st-border flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-4 transition hover:brightness-[0.98]">
                        <div>
                            <span class="font-semibold">Zamówienie #{{ $order->number }}</span>
                            <span class="block text-xs opacity-60">{{ $order->created_at->format('d.m.Y') }} · {{ $order->items_count }} poz.</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
                            <span class="font-bold tabular-nums">{{ \App\Support\Money::pln($order->total_gross) }}</span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($orders->hasPages())
            <div class="mt-8">
                {{ $orders->onEachSide(1)->links('storefront.pagination') }}
            </div>
        @endif
    @endif
</x-storefront.account-shell>
