<div>
    @if ($visible)
        <div class="mt-4 border-t border-stone-100 pt-4">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Przesyłka InPost</p>

            @if (filled($order->shipment_error))
                {{-- Błąd ostatniej próby. Powód jest KONKRETNY (np. brak środków na
                     koncie InPost) — ShipX nie zgłasza go kodem HTTP, wyławiamy go
                     z transakcji przesyłki, żeby sprzedawca wiedział, co poprawić. --}}
                <div class="mt-2 space-y-3">
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-3 text-sm">
                        <p class="font-medium text-rose-900">Nie udało się nadać przesyłki.</p>
                        <p class="mt-0.5 text-xs text-rose-700">{{ $order->shipment_error }}</p>
                    </div>

                    @if ($order->canBeShipped())
                        <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create"
                            class="inline-flex items-center rounded-2xl border border-stone-200 bg-white/70 px-4 py-2 text-sm font-medium text-stone-600 transition hover:bg-stone-50 hover:text-stone-800">
                            <span wire:loading.remove wire:target="create">Spróbuj ponownie</span>
                            <span wire:loading wire:target="create">Zlecam…</span>
                        </button>
                    @endif
                </div>
            @elseif ($order->isShipmentReady())
                {{-- Nadana i opłacona: numer do śledzenia + etykieta do druku. --}}
                <div class="mt-2 space-y-3">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-sm">
                        <p class="font-medium text-emerald-800">Przesyłka nadana.</p>
                        @if (filled($order->shipment_tracking_number))
                            <p class="mt-1 font-mono text-xs text-emerald-900">{{ $order->shipment_tracking_number }}</p>
                        @endif
                        <p class="mt-0.5 text-xs text-emerald-700">
                            {{ $order->shipment_size?->symbol() ? 'Gabaryt '.$order->shipment_size->symbol().' · ' : '' }}
                            @if ($order->shipped_at){{ $order->shipped_at->format('d.m.Y, H:i') }}@endif
                        </p>

                        {{-- Data odbioru: InPost potwierdził, że klient wyjął paczkę.
                             Sam TERMIN na odstąpienie mieszka w osobnej karcie przy
                             kupującym — dotyczy całego zamówienia, nie tylko przesyłki. --}}
                        @if ($order->delivered_at)
                            <p class="mt-2 border-t border-emerald-200 pt-2 text-xs text-emerald-800">
                                <span class="font-semibold">Data odbioru:</span> {{ $order->delivered_at->format('d.m.Y, H:i') }}
                            </p>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        {{-- Nowa karta: etykietę się drukuje, więc panel z zamówieniem
                             ma zostać otwarty pod spodem (sprzedawca wraca do niego
                             po wydruku, zamiast klikać „wstecz"). --}}
                        <a href="{{ route('seller.orders.label', $order) }}" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-105">
                            <span aria-hidden="true">⬇</span> Pobierz etykietę
                        </a>
                        {{-- Śledzenie tylko na produkcji: numery z konta testowego nie
                             istnieją w wyszukiwarce InPostu, więc link prowadziłby na
                             pustą stronę i wyglądał jak nasza awaria. --}}
                        @if ($order->trackingUrl() && ! $sandbox)
                            <a href="{{ $order->trackingUrl() }}" target="_blank" rel="noopener"
                                class="inline-flex items-center rounded-2xl border border-stone-200 bg-white/70 px-4 py-2 text-sm font-medium text-stone-600 transition hover:bg-stone-50 hover:text-stone-800">
                                Śledź przesyłkę
                            </a>
                        @endif
                    </div>

                    <p class="text-xs text-stone-400">Naklej etykietę na paczkę i wrzuć ją do dowolnego paczkomatu obsługującego nadania.</p>

                    @if ($sandbox)
                        <p class="text-xs text-amber-700">
                            Nadanie testowe (sandbox) — paczka nie istnieje naprawdę, a numeru nie znajdziesz w śledzeniu InPostu.
                            Przełącz konto na produkcyjne w <a href="{{ route('seller.integrations.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Integracjach</a>, gdy skończysz testy.
                        </p>
                    @endif
                </div>
            @elseif ($order->isShipmentPending())
                {{-- Nadana, ale InPost jeszcze jej nie opłacił (zakup jest
                     asynchroniczny). Odpytujemy co kilka sekund — statusy odświeża
                     komenda `shipments:refresh` uruchamiana cronem co minutę. --}}
                <div class="mt-2" wire:poll.5s>
                    <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm">
                        <span class="mt-0.5 shrink-0" aria-hidden="true">⏳</span>
                        <div>
                            <p class="font-medium text-amber-900">Nadajemy przesyłkę…</p>
                            <p class="mt-0.5 text-xs text-amber-700">InPost potwierdza nadanie w ciągu chwili. Numer przesyłki i etykieta pojawią się tutaj same.</p>
                        </div>
                    </div>
                </div>
            @elseif ($order->canBeShipped() && $confirming)
                {{-- Potwierdzenie w miejscu — bliźniak tego przy zmianie statusu.
                     Nadanie pobiera realną opłatę i nie da się go cofnąć z panelu,
                     więc powtarzamy WYBRANY GABARYT i paczkomat docelowy: to dwie
                     rzeczy, które najłatwiej kliknąć źle. --}}
                <div class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="font-medium text-amber-900">Nadać przesyłkę do zamówienia #{{ $order->number }}?</p>
                    <ul class="mt-2 space-y-1 text-xs text-amber-800">
                        <li>• Gabaryt: <strong>{{ $this->selectedSize()->label() }}</strong></li>
                        <li>• Paczkomat: <strong>{{ $order->parcel_locker_code }}</strong>@if (filled($order->parcel_locker_address)) — {{ $order->parcel_locker_address }}@endif</li>
                        <li>• Opłatę pobierze <strong>InPost z Twojego salda</strong>.</li>
                        <li>• Po nadaniu pobierzesz stąd etykietę do druku.</li>
                    </ul>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create"
                            class="inline-flex items-center gap-2 rounded-full bg-amber-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-amber-700">
                            <span aria-hidden="true">📦</span>
                            <span wire:loading.remove wire:target="create">Tak, nadaj przesyłkę</span>
                            <span wire:loading wire:target="create">Nadaję…</span>
                        </button>
                        <button type="button" wire:click="dismiss"
                            class="inline-flex items-center rounded-full border border-stone-200 bg-white px-4 py-1.5 text-sm font-medium text-stone-600 transition hover:bg-stone-50">Nie, wróć</button>
                    </div>
                </div>
            @elseif ($order->canBeShipped())
                {{-- Wybór gabarytu, a potem potwierdzenie (wyżej). --}}
                <div class="mt-2 space-y-3">
                    <div>
                        <label for="shipment-size-{{ $order->id }}" class="block text-xs font-medium text-stone-600">Gabaryt paczki</label>
                        <select id="shipment-size-{{ $order->id }}" wire:model="size"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @foreach ($sizes as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }} — {{ $case->hint() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="button" wire:click="ask"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-105">
                        <span aria-hidden="true">📦</span> Nadaj przesyłkę
                    </button>

                    <p class="text-xs text-stone-400">
                        Paczka pojedzie do paczkomatu <span class="font-medium text-stone-500">{{ $order->parcel_locker_code }}</span>.
                        Opłatę pobierze InPost z Twojego salda.
                    </p>
                </div>
            @endif
        </div>
    @endif
</div>
