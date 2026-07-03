<div>
    @if ($lines->isEmpty())
        {{-- Pusty koszyk --}}
        <div class="st-card st-border mx-auto max-w-lg rounded-3xl border px-8 py-16 text-center">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="mx-auto h-12 w-12 opacity-40" aria-hidden="true">
                <circle cx="9" cy="20" r="1.4" fill="currentColor" stroke="none"/>
                <circle cx="18" cy="20" r="1.4" fill="currentColor" stroke="none"/>
                <path d="M2.5 3h2l2.2 11.2a1.5 1.5 0 0 0 1.5 1.2h8.3a1.5 1.5 0 0 0 1.5-1.2L21 7H6"/>
            </svg>
            <p class="mt-5 text-lg font-semibold">Twój koszyk jest pusty</p>
            <p class="mt-1 opacity-70">Dodaj produkty, a pojawią się tutaj.</p>
            <a href="/" wire:navigate
                class="st-btn mt-6 inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">
                Wróć do sklepu
            </a>
        </div>
    @else
        <div class="grid gap-8 lg:grid-cols-3">
            {{-- Pozycje --}}
            <div class="lg:col-span-2 space-y-4">
                @foreach ($lines as $line)
                    @php($product = $line['product'])
                    @php($image = $product->mainImage())
                    <div wire:key="line-{{ $product->id }}"
                        class="st-card st-border flex items-center gap-4 rounded-2xl border p-4">
                        <div class="st-border h-20 w-20 shrink-0 overflow-hidden rounded-xl border" style="aspect-ratio: 1 / 1;">
                            @if ($image)
                                <img src="{{ $image->url() }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                            @else
                                <div class="st-btn flex h-full w-full items-center justify-center">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-7 w-7 opacity-70" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none"/><path d="M4 17l4.5-4.5 3 3L15 12l5 5"/></svg>
                                </div>
                            @endif
                        </div>

                        @php($atMax = $product->track_stock && $product->stock !== null && $line['quantity'] >= $product->stock)
                        <div class="min-w-0 flex-1">
                            <a href="{{ $product->storefrontPath() }}" wire:navigate class="block truncate font-semibold hover:underline">{{ $product->name }}</a>
                            <p class="mt-0.5 text-sm opacity-70">{{ \App\Support\Money::pln($line['unit_price']) }} / szt.</p>

                            {{-- Ilość: przy 1 szt. lewy przycisk to KOSZ (usuwa), od 2 w górę „−". --}}
                            <div class="mt-3 flex items-center gap-3">
                                <div class="inline-flex items-center gap-1">
                                    @if ($line['quantity'] <= 1)
                                        <button type="button" wire:click="remove({{ $product->id }})"
                                            class="st-border flex h-8 w-8 items-center justify-center rounded-full border transition hover:border-rose-400 hover:text-rose-600"
                                            aria-label="Usuń z koszyka" title="Usuń z koszyka">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4" aria-hidden="true"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m4 5v6m6-6v6"/></svg>
                                        </button>
                                    @else
                                        <button type="button" wire:click="decrement({{ $product->id }})"
                                            class="st-border flex h-8 w-8 items-center justify-center rounded-full border text-lg leading-none transition hover:brightness-95"
                                            aria-label="Zmniejsz ilość">−</button>
                                    @endif
                                    <span class="w-10 text-center font-semibold tabular-nums">{{ $line['quantity'] }}</span>
                                    <button type="button" wire:click="increment({{ $product->id }})" @disabled($atMax)
                                        class="st-border flex h-8 w-8 items-center justify-center rounded-full border text-lg leading-none transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                                        aria-label="Zwiększ ilość">+</button>
                                </div>
                                @if ($atMax)
                                    <span class="text-xs opacity-60">maks. {{ $product->stock }} szt.</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center self-stretch">
                            <span class="font-bold tabular-nums">{{ \App\Support\Money::pln($line['line_total']) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Podsumowanie --}}
            <aside class="lg:col-span-1">
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="font-semibold">Podsumowanie</h2>
                    <div class="mt-4 flex items-baseline justify-between border-t st-border pt-4">
                        <span class="opacity-70">Razem (brutto)</span>
                        <span class="text-xl font-bold tabular-nums">{{ \App\Support\Money::pln($total) }}</span>
                    </div>
                    <a href="/kasa" wire:navigate
                        class="st-btn mt-6 block w-full rounded-full px-8 py-3 text-center text-sm font-semibold shadow-sm transition hover:brightness-105">
                        Przejdź do kasy
                    </a>
                </div>
            </aside>
        </div>
    @endif
</div>
