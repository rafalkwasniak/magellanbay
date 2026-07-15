<x-layouts.panel :title="'Zamówienie #'.$order->number">
    <x-slot:heading>Zamówienie #{{ $order->number }}</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: pozycje + podsumowanie --}}
        <div class="space-y-6 lg:col-span-8">
            <livewire:seller.order-editor :order="$order" />

            @if (filled($order->note))
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Uwagi klienta</h2>
                    <p class="mt-2 whitespace-pre-line text-sm text-stone-600">{{ $order->note }}</p>
                </div>
            @endif

            <div class="grid gap-6 md:grid-cols-2 md:items-start">
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Kupujący</h2>
                    <div class="mt-3 space-y-1.5 text-sm text-stone-600">
                        <p class="font-medium text-stone-800">{{ trim($order->buyer_name.' '.$order->buyer_surname) }}</p>
                        <p><a href="mailto:{{ $order->buyer_email }}" class="text-amber-700 hover:underline">{{ $order->buyer_email }}</a></p>
                        @if (filled($order->buyer_phone))
                            <p>{{ app(\App\Services\PhoneService::class)->format($order->buyer_phone) ?? $order->buyer_phone }}</p>
                        @endif
                    </div>

                    @if ($order->is_company)
                        <div class="mt-4 border-t border-stone-100 pt-4 text-sm text-stone-600">
                            <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Dane do faktury</p>
                            <p class="mt-1.5 font-medium text-stone-800">{{ $order->company_name }}</p>
                            @if (filled($order->company_nip))<p>NIP {{ $order->company_nip }}</p>@endif
                            @if (filled($order->company_street))
                                <p class="mt-1">
                                    {{ $order->company_street }} {{ $order->company_building_number }}@if (filled($order->company_apartment_number))/{{ $order->company_apartment_number }}@endif
                                </p>
                                <p>{{ $order->company_postal_code }} {{ $order->company_city }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Dostawa i płatność</h2>
                    <div class="mt-3 space-y-2 text-sm text-stone-600">
                        <div class="flex justify-between gap-3">
                            <span class="text-stone-500">Dostawa</span>
                            <span class="text-right font-medium text-stone-800">{{ $order->delivery_method->label() }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-stone-500">Płatność</span>
                            <span class="text-right font-medium text-stone-800">{{ $order->payment_method->label() }}</span>
                        </div>
                        @if (filled($order->ship_street))
                            <div class="mt-2 border-t border-stone-100 pt-2">
                                <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Adres dostawy</p>
                                <p class="mt-1">
                                    {{ $order->ship_street }} {{ $order->ship_building_number }}@if (filled($order->ship_apartment_number))/{{ $order->ship_apartment_number }}@endif
                                </p>
                                <p>{{ $order->ship_postal_code }} {{ $order->ship_city }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="pt-2">
                <a href="{{ route('seller.orders.index', $listQuery) }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">← Wróć do listy</a>
            </div>
        </div>

        {{-- Kolumna boczna: status + kontakt z klientem --}}
        <aside class="space-y-6 lg:col-span-4">
            <livewire:seller.order-status-manager :order="$order" />
            <livewire:seller.order-messenger :order="$order" />
        </aside>
    </div>
</x-layouts.panel>
