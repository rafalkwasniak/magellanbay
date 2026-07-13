@php use App\Enums\PaymentMethod; use App\Enums\DeliveryMethod; @endphp
<x-layouts.storefront :shop="$shop" title="Zamówienie #{{ $order->number }}">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Moje konto', 'url' => '/moje-konto'],
            ['label' => 'Zamówienie #'.$order->number],
        ]" />

        <div class="mt-4 flex flex-wrap items-center gap-4">
            <h1 class="st-brand font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Zamówienie #{{ $order->number }}</h1>
            <span class="rounded-full px-3 py-1 text-sm font-medium {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
        </div>
        <p class="mt-2 text-sm opacity-60">Złożone {{ $order->created_at->format('d.m.Y, H:i') }}</p>

        <div class="st-border mt-8 grid gap-6 border-t pt-8 md:grid-cols-2">
            {{-- Pozycje + sumy --}}
            <div class="st-card st-border rounded-3xl border p-6">
                <h2 class="font-semibold">Podsumowanie</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-3 text-sm">
                            <span class="opacity-80">{{ $item->sale_unit->formatQuantity((float) $item->quantity) }} × {{ $item->name }}</span>
                            <span class="shrink-0 tabular-nums">{{ \App\Support\Money::pln($item->line_total_gross) }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="st-border mt-4 flex items-baseline justify-between border-t pt-4">
                    <span class="opacity-70">Razem (brutto)</span>
                    <span class="text-xl font-bold tabular-nums">{{ \App\Support\Money::pln($order->total_gross) }}</span>
                </div>
                <p class="mt-1 text-right text-xs opacity-60">{{ \App\Support\Money::pln($order->total_net) }} netto</p>
            </div>

            {{-- Płatność + dostawa --}}
            <div class="space-y-6">
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="font-semibold">Płatność — {{ $order->payment_method->label() }}</h2>
                    @if ($order->payment_method === PaymentMethod::BankTransfer)
                        <dl class="mt-4 space-y-1.5 text-sm">
                            @if ($shop->bankAccountHolderName())
                                <div class="flex justify-between gap-3"><dt class="opacity-60">Odbiorca</dt><dd class="text-right font-medium">{{ $shop->bankAccountHolderName() }}</dd></div>
                            @endif
                            @if ($shop->formattedBankAccountNumber())
                                <div class="flex justify-between gap-3"><dt class="opacity-60">Numer konta</dt><dd class="text-right font-mono">{{ $shop->formattedBankAccountNumber() }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-3"><dt class="opacity-60">Tytuł przelewu</dt><dd class="text-right font-medium">Zamówienie #{{ $order->number }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="opacity-60">Kwota</dt><dd class="text-right font-bold">{{ \App\Support\Money::pln($order->total_gross) }}</dd></div>
                        </dl>
                    @else
                        <p class="mt-2 text-sm opacity-70">Płatność na miejscu przy odbiorze zamówienia.</p>
                    @endif
                </div>

                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="font-semibold">Dostawa — {{ $order->delivery_method->label() }}</h2>
                    @if ($order->delivery_method === DeliveryMethod::Pickup)
                        <p class="mt-2 text-sm opacity-70">Odbiór pod adresem sklepu:</p>
                        <p class="mt-1 text-sm font-medium">
                            {{ $shop->street }} {{ $shop->building_number }}{{ $shop->apartment_number ? '/'.$shop->apartment_number : '' }},
                            {{ $shop->postal_code }} {{ $shop->city }}
                        </p>
                    @endif
                </div>

                @if (filled($order->note))
                    <div class="st-card st-border rounded-3xl border p-6">
                        <h2 class="font-semibold">Uwagi</h2>
                        <p class="mt-2 whitespace-pre-line text-sm opacity-80">{{ $order->note }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-8">
            <a href="/moje-konto" wire:navigate class="text-sm underline underline-offset-4 opacity-70 hover:opacity-100">← Wróć do zamówień</a>
        </div>
    </main>
</x-layouts.storefront>
