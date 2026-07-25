@php
    $inputClasses = 'block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15';
    $isFreeShipping = $type === \App\Enums\DiscountType::FreeShipping->value;
    // Sekcje opcjonalne otwieramy same, gdy kod już z nich korzysta — inaczej
    // sprzedawca edytując kod nie zobaczyłby ustawionego ograniczenia.
    $hasLimits = filled($min_items_total) || filled($starts_at) || filled($ends_at);
    $hasAudience = $uses_mode !== 'bez_limitu' || $customer_id !== null || ! $is_active;
@endphp

<div class="grid gap-6 lg:grid-cols-12">
    {{-- Główna kolumna: formularz --}}
    <div class="lg:col-span-8">
        <form wire:submit="save" class="space-y-6">
            {{-- 1. Sam kod --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <label for="codeValue" class="block text-sm font-medium text-stone-700">Kod</label>
                <p class="mt-1 text-sm text-stone-500">To wpisuje klient w koszyku. Wielkość liter nie ma znaczenia.</p>
                <div class="mt-3 flex flex-wrap items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <input id="codeValue" type="text" wire:model.live.debounce.400ms="codeValue" maxlength="32"
                            class="{{ $inputClasses }} font-mono uppercase tracking-wide">
                        @error('codeValue')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="button" wire:click="generateCode"
                        class="shrink-0 rounded-2xl border border-stone-200 bg-white px-5 py-3 text-sm font-medium text-stone-700 transition hover:bg-stone-100">
                        Wygeneruj
                    </button>
                </div>
            </div>

            {{-- 2. Rabat: co daje i na co działa --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h3 class="font-semibold text-stone-900">Rabat</h3>

                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach (\App\Enums\DiscountType::cases() as $case)
                        <button type="button" wire:click="$set('type', '{{ $case->value }}')"
                            @class([
                                'rounded-2xl border px-5 py-3 text-sm font-medium transition',
                                'border-amber-400 bg-amber-50/70 text-stone-900 shadow-sm' => $type === $case->value,
                                'border-stone-200 bg-white/60 text-stone-600 hover:bg-white' => $type !== $case->value,
                            ])>
                            {{ $case->label() }}
                        </button>
                    @endforeach
                </div>

                @unless ($isFreeShipping)
                    <div class="mt-5">
                        <label for="value" class="block text-sm font-medium text-stone-700">Wysokość rabatu</label>
                        <div class="relative mt-1.5">
                            <input id="value" type="text" inputmode="decimal" placeholder="0,00"
                                wire:model.live.debounce.400ms="value"
                                class="{{ $inputClasses }} pr-12">
                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">
                                {{ $type === \App\Enums\DiscountType::Percent->value ? '%' : 'zł' }}
                            </span>
                        </div>
                        @error('value')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="mt-5">
                        <span class="block text-sm font-medium text-stone-700">Dotyczy</span>
                        <div class="mt-2 flex flex-wrap gap-3">
                            @foreach (\App\Enums\DiscountScope::cases() as $case)
                                <button type="button" wire:click="$set('scope', '{{ $case->value }}')"
                                    @class([
                                        'rounded-2xl border px-5 py-3 text-sm font-medium transition',
                                        'border-amber-400 bg-amber-50/70 text-stone-900 shadow-sm' => $scope === $case->value,
                                        'border-stone-200 bg-white/60 text-stone-600 hover:bg-white' => $scope !== $case->value,
                                    ])>
                                    {{ $case->label() }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if ($scope === \App\Enums\DiscountScope::Product->value)
                        <div class="mt-4">
                            <label for="product_id" class="block text-sm font-medium text-stone-700">Produkt</label>
                            <select id="product_id" wire:model.live="product_id" class="{{ $inputClasses }} mt-1.5">
                                <option value="">— wybierz produkt —</option>
                                @foreach ($this->products() as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ \App\Support\Money::pln($product->price_gross) }})</option>
                                @endforeach
                            </select>
                            @error('product_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                            <p class="mt-1.5 text-xs text-stone-400">Rabat zejdzie z wartości tej pozycji w koszyku — reszta zamówienia zostaje w cenie.</p>
                        </div>
                    @endif
                @else
                    <p class="mt-4 rounded-2xl bg-stone-50 px-4 py-3 text-sm text-stone-500">
                        Ten kod zeruje koszt dostawy. Ceny produktów zostają bez zmian, więc nie ustawiasz tu wysokości rabatu.
                    </p>
                @endunless
            </div>

            {{-- 3. Ograniczenia (opcjonalne) --}}
            <details class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur" @if ($hasLimits) open @endif>
                <summary class="cursor-pointer font-semibold text-stone-900">Ograniczenia <span class="font-normal text-stone-400">(opcjonalne)</span></summary>

                <div class="mt-5">
                    <label for="min_items_total" class="block text-sm font-medium text-stone-700">Minimalna wartość produktów</label>
                    <div class="relative mt-1.5">
                        <input id="min_items_total" type="text" inputmode="decimal" placeholder="bez progu"
                            wire:model.live.debounce.400ms="min_items_total"
                            class="{{ $inputClasses }} pr-12">
                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">zł</span>
                    </div>
                    @error('min_items_total')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    <p class="mt-1.5 text-xs text-stone-400">Liczy się sama wartość produktów — koszt wysyłki nie pomaga przekroczyć progu.</p>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="starts_at" class="block text-sm font-medium text-stone-700">Ważny od</label>
                        <input id="starts_at" type="date" wire:model.live="starts_at" class="{{ $inputClasses }} mt-1.5">
                        @error('starts_at')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="ends_at" class="block text-sm font-medium text-stone-700">Ważny do</label>
                        <input id="ends_at" type="date" wire:model.live="ends_at" class="{{ $inputClasses }} mt-1.5">
                        @error('ends_at')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="mt-1.5 text-xs text-stone-400">Puste pola = bez ograniczenia. Dzień „ważny do" liczy się w całości, do północy.</p>
            </details>

            {{-- 4. Dostępność (opcjonalne) --}}
            <details class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur" @if ($hasAudience) open @endif>
                <summary class="cursor-pointer font-semibold text-stone-900">Dostępność <span class="font-normal text-stone-400">(opcjonalne)</span></summary>

                <div class="mt-5">
                    <span class="block text-sm font-medium text-stone-700">Ile razy można użyć</span>
                    <div class="mt-2 flex flex-wrap gap-3">
                        @foreach (['bez_limitu' => 'Bez limitu', 'jednorazowy' => 'Tylko raz', 'limit' => 'Maksymalnie…'] as $mode => $label)
                            <button type="button" wire:click="$set('uses_mode', '{{ $mode }}')"
                                @class([
                                    'rounded-2xl border px-5 py-3 text-sm font-medium transition',
                                    'border-amber-400 bg-amber-50/70 text-stone-900 shadow-sm' => $uses_mode === $mode,
                                    'border-stone-200 bg-white/60 text-stone-600 hover:bg-white' => $uses_mode !== $mode,
                                ])>
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    @if ($uses_mode === 'limit')
                        <div class="mt-3 max-w-sm">
                            <label for="max_uses" class="sr-only">Limit użyć</label>
                            <div class="relative">
                                <input id="max_uses" type="text" inputmode="numeric" placeholder="np. 50"
                                    wire:model.live.debounce.400ms="max_uses"
                                    class="{{ $inputClasses }} pr-12">
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">razy</span>
                            </div>
                            @error('max_uses')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <p class="mt-2 text-xs text-stone-400">Anulowane zamówienie oddaje użycie — kod wraca do puli.</p>
                </div>

                <div class="mt-5">
                    <label for="customer_id" class="block text-sm font-medium text-stone-700">Dla kogo</label>
                    @if ($this->customers()->isEmpty())
                        <p class="mt-1.5 rounded-2xl bg-stone-50 px-4 py-3 text-sm text-stone-500">
                            Kod imienny wystawisz, gdy w sklepie będą aktywowane konta klientów. Na razie kod jest dostępny dla każdego.
                        </p>
                    @else
                        <select id="customer_id" wire:model.live="customer_id" class="{{ $inputClasses }} mt-1.5">
                            <option value="">Dostępny dla wszystkich</option>
                            @foreach ($this->customers() as $customer)
                                <option value="{{ $customer->id }}">{{ trim($customer->name.' '.$customer->surname) ?: $customer->email }} — {{ $customer->email }}</option>
                            @endforeach
                        </select>
                        @error('customer_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                        <p class="mt-1.5 text-xs text-stone-400">Kodu imiennego użyje wyłącznie ten klient, zalogowany na swoje konto.</p>
                    @endif
                </div>

                <div class="mt-5 flex items-start gap-3">
                    <input type="checkbox" id="is_active" wire:model.live="is_active"
                        class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20">
                    <label for="is_active" class="flex-1 cursor-pointer">
                        <span class="block text-sm font-medium text-stone-800">Kod włączony</span>
                        <span class="mt-0.5 block text-sm text-stone-500">Wyłączony kod zostaje na liście, ale przestaje działać w koszyku.</span>
                    </label>
                </div>
            </details>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit"
                    class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                    {{ $code === null ? 'Dodaj kod' : 'Zapisz zmiany' }}
                </button>
                <a href="{{ route('seller.discounts.index', $listQuery) }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-700">Anuluj</a>
            </div>
        </form>
    </div>

    {{-- Prawa kolumna: kod opowiedziany po polsku, składany na żywo --}}
    <aside class="lg:col-span-4 space-y-6">
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
            <h2 class="font-semibold text-stone-900">Jak zadziała</h2>
            <div class="mt-4 rounded-2xl bg-stone-50 px-4 py-3 text-center">
                <span class="font-mono text-lg font-semibold uppercase tracking-wide text-stone-900">{{ $codeValue !== '' ? $codeValue : '—' }}</span>
            </div>
            <ul class="mt-4 space-y-3 text-sm text-stone-500">
                @foreach ($this->summary() as $line)
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        <span>{{ $line }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </aside>
</div>
