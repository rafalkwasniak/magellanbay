@php use App\Enums\PaymentMethod; use App\Enums\DeliveryMethod; @endphp
<x-storefront.account-shell :shop="$shop" active="orders" heading="Zamówienie #{{ $order->number }}" :back="$back" :crumbs="[
    ['label' => $shop->name, 'url' => '/'],
    ['label' => 'Moje konto', 'url' => '/moje-konto'],
    ['label' => 'Zamówienia', 'url' => '/moje-konto/zamowienia'],
    ['label' => 'Zamówienie #'.$order->number],
]">
    <div class="flex flex-wrap items-center gap-3">
        <span class="rounded-full px-3 py-1 text-sm font-medium {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
        <span class="text-sm opacity-60">Złożone {{ $order->created_at->format('d.m.Y, H:i') }}</span>
    </div>

    <div class="mt-6 space-y-6">
        {{-- Lewa kolumna: podsumowanie + faktura --}}
        <div class="space-y-6">
        <div class="st-card st-border rounded-3xl border p-6">
            <h2 class="st-brand st-box-title">Podsumowanie</h2>
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
        </div>

        {{-- Płatność + dostawa --}}
        <div class="space-y-6">
            <div class="st-card st-border rounded-3xl border p-6">
                <h2 class="st-brand st-box-title">Płatność — {{ $order->payment_method->label() }}</h2>
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
                <h2 class="st-brand st-box-title">Dostawa — {{ $order->delivery_method->label() }}</h2>
                @if ($order->delivery_method === DeliveryMethod::Pickup)
                    <p class="mt-2 text-sm opacity-70">Odbiór pod adresem sklepu:</p>
                    <p class="mt-1 text-sm font-medium">
                        {{ $shop->street }} {{ $shop->building_number }}{{ $shop->apartment_number ? '/'.$shop->apartment_number : '' }},
                        {{ $shop->postal_code }} {{ $shop->city }}
                    </p>
                @elseif ($order->delivery_method->requiresShippingAddress())
                    <p class="mt-2 text-sm opacity-70">Wysyłka na adres:</p>
                    <p class="mt-1 text-sm font-medium">
                        {{ $order->ship_street }} {{ $order->ship_building_number }}{{ $order->ship_apartment_number ? '/'.$order->ship_apartment_number : '' }},
                        {{ $order->ship_postal_code }} {{ $order->ship_city }}
                    </p>
                @elseif ($order->delivery_method->requiresParcelLocker())
                    <p class="mt-2 text-sm opacity-70">Wysyłka do paczkomatu:</p>
                    <p class="mt-1 text-sm font-medium">{{ $order->parcel_locker_code }}</p>
                    @if (filled($order->parcel_locker_address))
                        <p class="mt-0.5 text-sm opacity-70">{{ $order->parcel_locker_address }}</p>
                    @endif
                @endif
            </div>

            @if (filled($order->note))
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="st-brand st-box-title">Uwagi</h2>
                    <p class="mt-2 whitespace-pre-line text-sm opacity-80">{{ $order->note }}</p>
                </div>
            @endif
        </div>

        @if ($order->hasInvoice() && $order->invoicePdfUrl())
            {{-- Faktura VAT na samym dole. --}}
            <div class="st-card st-border rounded-3xl border p-6">
                <h2 class="st-brand st-box-title">Faktura VAT</h2>
                <p class="mt-2 text-sm opacity-70">
                    @if (filled($order->invoice_number))
                        Faktura nr <span class="font-medium">{{ $order->invoice_number }}</span> do tego zamówienia jest gotowa.
                    @else
                        Faktura do tego zamówienia jest gotowa.
                    @endif
                </p>
                <a href="{{ $order->invoicePdfUrl() }}" target="_blank" rel="noopener"
                    class="st-btn mt-4 inline-flex items-center gap-2 rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm transition hover:brightness-105">
                    <span aria-hidden="true">⬇</span> Pobierz fakturę VAT
                </a>
            </div>
        @endif
    </div>
</x-storefront.account-shell>
