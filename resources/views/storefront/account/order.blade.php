@php use App\Enums\PaymentMethod; use App\Enums\DeliveryMethod; use App\Enums\OrderStatus; @endphp
<x-storefront.account-shell :shop="$shop" active="orders" heading="Zamówienie #{{ $order->number }}" :back="$back" :crumbs="[
    ['label' => $shop->name, 'url' => '/'],
    ['label' => 'Moje konto', 'url' => '/moje-konto'],
    ['label' => 'Zamówienia', 'url' => '/moje-konto/zamowienia'],
    ['label' => 'Zamówienie #'.$order->number],
]">
    <div class="flex flex-wrap items-center gap-3">
        <span class="rounded-full px-3 py-1 text-sm font-medium {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
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
            <x-storefront.order-totals :order="$order" />
        </div>

        {{-- Historia zamówienia: „kiedy co się wydarzyło”. Ten sam układ osi, co
             widzi sprzedawca, ale w klasach motywu — kropki i linia w kolorze
             sklepu. Status początkowy dokładamy ręcznie (nie ma go wśród zdarzeń:
             zamówienie się z nim urodziło, nikt go nie zmieniał). Notatka przy
             zdarzeniu to wiadomość, którą sprzedawca wysłał kupującemu mailem. --}}
        <div class="st-card st-border rounded-3xl border p-6">
            <h2 class="st-brand st-box-title">Historia zamówienia</h2>
            <ol class="st-border mt-4 space-y-3 border-l pl-4">
                <li class="relative">
                    <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full opacity-40" style="background: currentColor"></span>
                    <p class="text-sm font-medium">Złożone</p>
                    <p class="text-xs opacity-50">{{ $order->created_at->format('d.m.Y, H:i') }}</p>
                </li>
                <li class="relative">
                    <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full" style="background: var(--brand)"></span>
                    <p class="text-sm font-medium">{{ $initialStatus->label() }}</p>
                    <p class="text-xs opacity-50">{{ $order->created_at->format('d.m.Y, H:i') }}</p>
                </li>
                @foreach ($order->statusEvents as $event)
                    <li class="relative">
                        <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full" style="background: var(--brand)"></span>
                        <p class="text-sm font-medium">{{ $event->to_status->label() }}</p>
                        <p class="text-xs opacity-50">{{ $event->created_at->format('d.m.Y, H:i') }}</p>
                        @if (filled($event->note))
                            <p class="mt-0.5 text-xs italic opacity-70">„{{ $event->note }}"</p>
                        @endif
                    </li>
                @endforeach
            </ol>
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
                @elseif ($order->payment_method === PaymentMethod::Online)
                    @if ($order->isAwaitingOnlinePayment())
                        <p class="mt-2 text-sm opacity-70">Oczekuje na płatność online (BLIK, karta lub szybki przelew).</p>
                        <a href="/platnosc/{{ $order->paymentToken() }}" class="st-btn mt-4 inline-flex items-center gap-2 rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm transition hover:brightness-105">
                            Zapłać — {{ \App\Support\Money::pln($order->total_gross) }}
                        </a>
                    @else
                        <p class="mt-2 text-sm opacity-70">Opłacone online.</p>
                    @endif
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
