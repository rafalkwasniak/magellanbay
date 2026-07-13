<x-layouts.storefront :shop="$shop" title="Moje konto">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Moje konto'],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Moje konto</h1>

        @include('storefront.account.nav', ['active' => 'orders'])

        @if (session('status'))
            <div class="st-card st-border mt-6 rounded-xl border p-4 text-sm">{{ session('status') }}</div>
        @endif

        <div class="st-border mt-8 border-t pt-8">
            <h2 class="font-semibold">Historia zamówień</h2>

            @if ($orders->isEmpty())
                <div class="st-card st-border mt-4 rounded-3xl border p-10 text-center">
                    <p class="opacity-70">Nie masz jeszcze zamówień.</p>
                    <a href="/produkty" wire:navigate class="st-brand mt-2 inline-block text-sm underline underline-offset-2">Przejdź do produktów</a>
                </div>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($orders as $order)
                        <li>
                            <a href="/moje-konto/zamowienia/{{ $order->id }}" wire:navigate
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
            @endif
        </div>
    </main>
</x-layouts.storefront>
