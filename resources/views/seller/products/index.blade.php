<x-layouts.panel title="Produkty">
    <x-slot:heading>Produkty</x-slot:heading>

    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-stone-900">Twoje produkty</h2>
                <p class="mt-1 text-sm text-stone-500">{{ $products->count() }} / {{ $max }} w pakiecie Free</p>
            </div>
            @if ($products->count() < $max)
                <a href="{{ route('seller.products.create') }}"
                    class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                    Dodaj produkt
                </a>
            @endif
        </div>

        @if ($products->isEmpty())
            <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🏷️</span>
                <p class="mt-4 font-medium text-stone-700">Nie masz jeszcze produktów</p>
                <p class="mt-1 text-sm text-stone-500">Dodaj pierwszy produkt — to ostatni krok do otwarcia sklepu.</p>
                <a href="{{ route('seller.products.create') }}"
                    class="mt-5 inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                    Dodaj pierwszy produkt
                </a>
            </div>
        @else
            <ul class="mt-6 divide-y divide-stone-100">
                @foreach ($products as $product)
                    <li class="flex flex-wrap items-center gap-4 py-4">
                        @php($main = $product->mainImage())
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-stone-200 bg-stone-50">
                            @if ($main)
                                <img src="{{ $main->url() }}" alt="" class="h-full w-full object-contain">
                            @else
                                <span class="text-stone-300">🏷️</span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-stone-900">{{ $product->name }}</p>
                            <p class="mt-0.5 text-xs text-stone-500">
                                {{ number_format((float) $product->price_gross, 2, ',', ' ') }} zł
                                · VAT {{ $product->vat_rate->label() }}
                                · {{ $product->track_stock ? $product->stock.' szt.' : 'bez limitu' }}
                            </p>
                        </div>

                        <span @class([
                            'rounded-full px-3 py-1 text-xs font-medium',
                            'bg-emerald-100 text-emerald-700' => $product->is_active,
                            'bg-stone-100 text-stone-500' => ! $product->is_active,
                        ])>{{ $product->is_active ? 'Aktywny' : 'Ukryty' }}</span>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('seller.products.edit', $product) }}"
                                class="rounded-xl border border-stone-200 bg-white/70 px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:bg-white">Edytuj</a>
                            <form method="POST" action="{{ route('seller.products.destroy', $product) }}"
                                onsubmit="return confirm('Usunąć produkt „{{ $product->name }}”?');">
                                @csrf
                                <button type="submit" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-100">Usuń</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.panel>
