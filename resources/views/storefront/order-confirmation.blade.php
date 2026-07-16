@php use App\Enums\PaymentMethod; use App\Enums\DeliveryMethod; @endphp
<x-layouts.storefront :shop="$shop" title="Dziękujemy za zamówienie">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Zamówienie #'.$order->number],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Dziękujemy za zamówienie</h1>

        <div class="st-border mt-8 border-t pt-8">
            {{-- Potwierdzenie: ptaszek + numer zamówienia --}}
            <div class="st-card st-border rounded-3xl border p-8 text-center">
                <div class="st-btn mx-auto flex h-14 w-14 items-center justify-center rounded-full">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-7 w-7" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <p class="mt-5 text-lg font-semibold">Zamówienie zostało złożone!</p>
                <p class="mt-2 opacity-70">Numer zamówienia: <span class="st-brand font-bold">#{{ $order->number }}</span></p>
                <p class="mt-1 text-sm opacity-70">Potwierdzenie wysłaliśmy na {{ $order->buyer_email }}.</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 md:grid-cols-2">
            {{-- Lewa: pozycje + sumy --}}
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
                @if ($order->delivery_method->isShipped())
                    <div class="st-border mt-4 flex items-baseline justify-between border-t pt-4 text-sm">
                        <span class="opacity-70">Dostawa</span>
                        <span class="shrink-0 tabular-nums">{{ (float) $order->delivery_cost > 0 ? \App\Support\Money::pln($order->delivery_cost) : 'Gratis' }}</span>
                    </div>
                @endif
                <div class="st-border flex items-baseline justify-between {{ $order->delivery_method->isShipped() ? 'mt-3' : 'mt-4 border-t pt-4' }}">
                    <span class="opacity-70">Razem (brutto)</span>
                    <span class="text-xl font-bold tabular-nums">{{ \App\Support\Money::pln($order->total_gross) }}</span>
                </div>
                <p class="mt-1 text-right text-xs opacity-60">{{ \App\Support\Money::pln($order->total_net) }} netto</p>
            </div>

            {{-- Prawa: płatność + dostawa --}}
            <div class="space-y-6">
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="font-semibold">Płatność — {{ $order->payment_method->label() }}</h2>
                    @if ($order->payment_method === PaymentMethod::BankTransfer)
                        <dl class="mt-4 space-y-1.5 text-sm">
                            @if ($shop->bankAccountHolderName())
                                <div class="flex justify-between gap-3"><dt class="opacity-60">Odbiorca</dt><dd class="text-right font-medium">{{ $shop->bankAccountHolderName() }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-3"><dt class="opacity-60">Numer konta</dt><dd class="text-right font-mono">{{ $shop->formattedBankAccountNumber() }}</dd></div>
                            @if (filled($shop->bank_name))
                                <div class="flex justify-between gap-3"><dt class="opacity-60">Bank</dt><dd class="text-right">{{ $shop->bank_name }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-3"><dt class="opacity-60">Tytuł przelewu</dt><dd class="text-right font-medium">Zamówienie #{{ $order->number }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="opacity-60">Kwota</dt><dd class="text-right font-bold">{{ \App\Support\Money::pln($order->total_gross) }}</dd></div>
                        </dl>
                    @else
                        <p class="mt-2 text-sm opacity-70">Zapłacisz na miejscu przy odbiorze zamówienia.</p>
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
                        <p class="mt-2 text-xs opacity-60">Poinformujemy Cię, gdy zamówienie będzie gotowe do odbioru.</p>
                    @elseif ($order->delivery_method->isShipped())
                        <p class="mt-2 text-sm opacity-70">Wyślemy na adres:</p>
                        <p class="mt-1 text-sm font-medium">
                            {{ $order->ship_street }} {{ $order->ship_building_number }}{{ $order->ship_apartment_number ? '/'.$order->ship_apartment_number : '' }},
                            {{ $order->ship_postal_code }} {{ $order->ship_city }}
                        </p>
                        <p class="mt-2 text-xs opacity-60">Poinformujemy Cię, gdy zamówienie będzie gotowe do wysyłki.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-8 text-center">
            <a href="/" wire:navigate class="st-btn inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">Wróć do sklepu</a>
        </div>
    </main>
</x-layouts.storefront>
