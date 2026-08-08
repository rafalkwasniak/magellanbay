<div>
    @if (session('pickup_success'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ session('pickup_success') }}
        </div>
    @endif

    @if (session('pickup_error'))
        <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            {{ session('pickup_error') }}
        </div>
    @endif

    {{-- Ostatnie zlecenia. InPost rozstrzyga je z opóźnieniem, więc samo
         kliknięcie niczego nie gwarantuje — dopóki nie ma potwierdzenia,
         mówimy „czekamy", a nie „kurier przyjedzie". --}}
    @if ($recent->isNotEmpty())
        <div class="mb-6 space-y-2">
            @foreach ($recent as $dispatchOrder)
                @if ($dispatchOrder->isRejected())
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm">
                        <p class="font-medium text-rose-900">Zamówienie kuriera odrzucone ({{ $dispatchOrder->created_at->format('d.m.Y, H:i') }}).</p>
                        <p class="mt-0.5 text-xs text-rose-700">{{ $dispatchOrder->error }}</p>
                        <p class="mt-1 text-xs text-rose-700">Paczki wróciły na listę poniżej — możesz zamówić kuriera ponownie.</p>
                    </div>
                @elseif ($dispatchOrder->isAccepted())
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
                        <p class="font-medium text-emerald-800">Kurier zamówiony ({{ $dispatchOrder->created_at->format('d.m.Y, H:i') }}) — paczek: {{ $dispatchOrder->orders()->count() }}.</p>
                        <p class="mt-0.5 text-xs text-emerald-700">InPost przyjął zlecenie. Przygotuj paczki na czas przyjazdu.</p>
                    </div>
                @else
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm" wire:poll.10s>
                        <p class="font-medium text-amber-900">Czekamy na potwierdzenie InPostu…</p>
                        <p class="mt-0.5 text-xs text-amber-700">Zlecenie z {{ $dispatchOrder->created_at->format('d.m.Y, H:i') }}. Potwierdzenie zwykle przychodzi w kilka sekund.</p>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    @if ($pickupAddress === null)
        <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
            <p class="font-medium">Uzupełnij adres sklepu, zanim zamówisz kuriera.</p>
            <p class="mt-1 text-xs text-amber-800">Kurier musi wiedzieć, gdzie przyjechać po paczki. Adres podasz w <a href="{{ route('seller.shop.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">profilu sklepu</a>.</p>
        </div>
    @elseif ($awaiting->isEmpty())
        <div class="rounded-3xl border border-white/60 bg-white/70 p-8 text-center backdrop-blur">
            <p class="text-3xl" aria-hidden="true">🚚</p>
            <p class="mt-3 font-medium text-stone-800">Nie ma paczek czekających na kuriera.</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-stone-500">
                Trafiają tu przesyłki nadane z wyborem <span class="font-medium text-stone-600">„Odbierze je kurier InPost”</span>.
                Paczki, które wrzucasz do paczkomatu, nie wymagają zamawiania odbioru.
            </p>
        </div>
    @else
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-stone-900">Paczki czekające na kuriera</h2>
                    <p class="mt-0.5 text-sm text-stone-500">Zaznacz te, które kurier ma zabrać. Jeden przyjazd obejmuje wszystkie naraz.</p>
                </div>
                <p class="text-xs text-stone-400">Odbiór spod: <span class="font-medium text-stone-500">{{ $pickupAddress['street'] }} {{ $pickupAddress['building_number'] }}, {{ $pickupAddress['post_code'] }} {{ $pickupAddress['city'] }}</span></p>
            </div>

            <div class="mt-4 space-y-2">
                @foreach ($awaiting as $order)
                    <label for="pickup-{{ $order->id }}" class="flex cursor-pointer items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4">
                        <input type="checkbox" id="pickup-{{ $order->id }}" value="{{ $order->id }}" wire:model.live="selected"
                            class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20">
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-stone-800">
                                Zamówienie #{{ $order->number }} — {{ trim($order->buyer_name.' '.$order->buyer_surname) }}
                            </span>
                            <span class="mt-0.5 block text-xs text-stone-500">
                                {{ $order->delivery_method->label() }}
                                @if ($order->shipmentParcelLabel()) · {{ $order->shipmentParcelLabel() }}@endif
                                @if ($order->shipment_tracking_number) · {{ $order->shipment_tracking_number }}@endif
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="mt-4">
                <label for="pickup-comment" class="block text-xs font-medium text-stone-600">Uwaga dla kuriera <span class="font-normal text-stone-400">(opcjonalnie)</span></label>
                <input id="pickup-comment" type="text" wire:model="comment" placeholder="np. dzwonić przed przyjazdem, wejście od podwórza"
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
            </div>

            @if ($confirming)
                {{-- Potwierdzenie w miejscu — jak przy nadaniu. Zamówienie kuriera
                     to realna dopłata u InPostu, więc powtarzamy liczbę paczek
                     i adres, spod którego mają zostać zabrane. --}}
                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="font-medium text-amber-900">Zamówić kuriera po {{ count($selected) }} {{ count($selected) === 1 ? 'paczkę' : 'paczki' }}?</p>
                    <ul class="mt-2 space-y-1 text-xs text-amber-800">
                        <li>• Odbiór spod: <strong>{{ $pickupAddress['street'] }} {{ $pickupAddress['building_number'] }}, {{ $pickupAddress['post_code'] }} {{ $pickupAddress['city'] }}</strong></li>
                        <li>• Odbiór kuriera to <strong>usługa dodatkowo płatna</strong> — opłatę pobierze InPost z Twojego salda.</li>
                        <li>• Jeden przyjazd obejmuje wszystkie zaznaczone paczki.</li>
                    </ul>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="request" wire:loading.attr="disabled" wire:target="request"
                            class="inline-flex items-center gap-2 rounded-full bg-amber-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-amber-700">
                            <span aria-hidden="true">🚚</span>
                            <span wire:loading.remove wire:target="request">Tak, zamów kuriera</span>
                            <span wire:loading wire:target="request">Zamawiam…</span>
                        </button>
                        <button type="button" wire:click="dismiss"
                            class="inline-flex items-center rounded-full border border-stone-200 bg-white px-4 py-1.5 text-sm font-medium text-stone-600 transition hover:bg-stone-50">Nie, wróć</button>
                    </div>
                </div>
            @else
                <button type="button" wire:click="ask" @disabled($selected === [])
                    class="mt-4 inline-flex items-center gap-2 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-50">
                    <span aria-hidden="true">🚚</span> Zamów kuriera po zaznaczone
                </button>
            @endif
        </div>
    @endif
</div>
