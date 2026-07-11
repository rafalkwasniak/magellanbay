<div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
    <div class="flex items-center justify-between gap-3">
        <h2 class="font-semibold text-stone-900">Pozycje</h2>
        <button
            type="button"
            wire:click="toggleEditing"
            @class([
                'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium transition',
                'bg-amber-600 text-white shadow-sm hover:bg-amber-700' => $editing,
                'border border-amber-200 bg-amber-100 text-amber-800 hover:bg-amber-200' => ! $editing,
            ])
        >{{ $editing ? 'Gotowe' : 'Edytuj zamówienie' }}</button>
    </div>

    <div class="mt-4 divide-y divide-stone-100">
        @forelse ($items as $item)
            <div class="flex items-start justify-between gap-4 py-3" wire:key="item-{{ $item->id }}">
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium text-stone-800">{{ $item->name }}</p>

                    @if ($editing)
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            {{-- Ilość: stepper + wpisanie z palca --}}
                            <div @class([
                                'inline-flex items-center rounded-full border bg-white',
                                'border-rose-300' => isset($itemErrors[$item->id]),
                                'border-stone-200' => ! isset($itemErrors[$item->id]),
                            ])>
                                <button type="button" wire:click="decQuantity({{ $item->id }})" class="px-3 py-1 text-stone-500 transition hover:text-stone-800" aria-label="Mniej">−</button>
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    value="{{ $item->sale_unit->inputAmount((float) $item->quantity) }}"
                                    wire:change="setQuantity({{ $item->id }}, $event.target.value)"
                                    class="w-12 border-0 bg-transparent p-0 text-center text-sm text-stone-800 focus:ring-0"
                                >
                                <button type="button" wire:click="incQuantity({{ $item->id }})" class="px-3 py-1 text-stone-500 transition hover:text-stone-800" aria-label="Więcej">+</button>
                                <span class="pr-3 text-xs text-stone-400">{{ $item->sale_unit->abbreviation() }}</span>
                            </div>

                            <span class="text-stone-400">×</span>

                            {{-- Cena jednostkowa (brutto, zamrożona) --}}
                            <div class="inline-flex items-center gap-1 rounded-full border border-stone-200 bg-white pl-3 pr-3 py-1">
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    value="{{ number_format((float) $item->unit_price_gross, 2, ',', '') }}"
                                    wire:change="setPrice({{ $item->id }}, $event.target.value)"
                                    class="w-16 border-0 bg-transparent p-0 text-right text-sm text-stone-800 focus:ring-0"
                                >
                                <span class="text-xs text-stone-400">zł</span>
                            </div>

                            <button
                                type="button"
                                wire:click="removeItem({{ $item->id }})"
                                wire:confirm="Usunąć tę pozycję z zamówienia?"
                                class="text-sm font-medium text-rose-500 transition hover:text-rose-700"
                            >Usuń</button>
                        </div>

                        @if (isset($itemErrors[$item->id]))
                            {{-- Błąd tej pozycji w miejscu podpowiedzi (ta sama linia → bez skoku układu). --}}
                            <p class="mt-1 text-xs font-medium text-rose-600">{{ $itemErrors[$item->id] }}</p>
                        @else
                            <p class="mt-1 text-xs text-stone-400">
                                VAT {{ $item->vat_rate->label() }}
                                @if ($maxQuantities[$item->id] !== null)
                                    · dostępne max {{ $item->sale_unit->formatQuantity($maxQuantities[$item->id]) }}
                                @endif
                            </p>
                        @endif
                    @else
                        <p class="mt-0.5 text-sm text-stone-500">
                            {{ $item->sale_unit->formatQuantity((float) $item->quantity) }} × {{ \App\Support\Money::pln($item->unit_price_gross) }}
                            <span class="text-stone-400">· VAT {{ $item->vat_rate->label() }}</span>
                        </p>
                    @endif
                </div>
                <span class="shrink-0 font-semibold tabular-nums text-stone-900">{{ \App\Support\Money::pln($item->line_total_gross) }}</span>
            </div>
        @empty
            <p class="py-3 text-sm text-stone-500">To zamówienie nie ma już żadnych pozycji.</p>
        @endforelse
    </div>

    @if ($editing)
        {{-- Dodaj produkt: cena z bieżącego sklepu (migawka), ilość respektuje stan --}}
        <div class="mt-4 rounded-2xl border border-amber-100 bg-amber-50/60 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Dodaj produkt</p>

            @if ($products->isEmpty())
                <p class="mt-2 text-sm text-stone-500">Brak aktywnych produktów do dodania.</p>
            @else
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <select
                        wire:model="addProductId"
                        class="min-w-0 flex-1 rounded-2xl border border-stone-200 bg-white/80 px-3 py-2 text-sm text-stone-700 focus:border-amber-400 focus:ring-amber-400"
                    >
                        <option value="">— wybierz produkt —</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">
                                {{ $product->name }} — {{ \App\Support\Money::pln($product->price_gross) }}@if ($product->track_stock && $product->stock !== null) (stan {{ $product->sale_unit->formatQuantity((float) $product->stock) }})@endif
                            </option>
                        @endforeach
                    </select>

                    <input
                        type="text"
                        inputmode="decimal"
                        wire:model="addQuantity"
                        placeholder="Ilość"
                        class="w-24 rounded-2xl border border-stone-200 bg-white/80 px-3 py-2 text-sm text-stone-700 placeholder:text-stone-400 focus:border-amber-400 focus:ring-amber-400"
                    >

                    <button
                        type="button"
                        wire:click="addProduct"
                        class="inline-flex items-center rounded-full bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-amber-700"
                    >Dodaj</button>
                </div>
                @if ($addError)
                    <p class="mt-2 text-xs font-medium text-rose-600">{{ $addError }}</p>
                @else
                    <p class="mt-2 text-xs text-stone-400">Produkt dodaje się w aktualnej cenie sklepu. Cenę pozycji możesz potem zmienić wyżej.</p>
                @endif
            @endif
        </div>
    @endif

    <dl class="mt-4 space-y-2 border-t border-stone-100 pt-4 text-sm">
        <div class="flex justify-between">
            <dt class="text-stone-500">Produkty (brutto)</dt>
            <dd class="tabular-nums text-stone-700">{{ \App\Support\Money::pln($order->items_total) }}</dd>
        </div>
        @if ($order->delivery_method->isShipped())
            <div class="flex justify-between">
                <dt class="text-stone-500">Dostawa</dt>
                <dd class="tabular-nums text-stone-700">{{ (float) $order->delivery_cost > 0 ? \App\Support\Money::pln($order->delivery_cost) : 'gratis' }}</dd>
            </div>
        @endif
        <div class="flex justify-between text-stone-400">
            <dt>w tym netto</dt>
            <dd class="tabular-nums">{{ \App\Support\Money::pln($order->total_net) }}</dd>
        </div>
        <div class="flex justify-between text-stone-400">
            <dt>w tym VAT</dt>
            <dd class="tabular-nums">{{ \App\Support\Money::pln($order->total_vat) }}</dd>
        </div>
        <div class="flex justify-between border-t border-stone-100 pt-2 text-base font-bold text-stone-900">
            <dt>Razem</dt>
            <dd class="tabular-nums">{{ \App\Support\Money::pln($order->total_gross) }}</dd>
        </div>
    </dl>
</div>
