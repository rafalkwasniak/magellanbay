<x-layouts.storefront :shop="$shop" :title="$product->name">
    <div class="mx-auto max-w-6xl px-6 pt-10">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Produkty', 'url' => '/produkty'],
            ['label' => $product->name],
        ]" />

        @php $backToList = str_starts_with($back, '/produkty'); @endphp
        <a href="{{ $back }}" class="mt-3 inline-block text-sm opacity-70 transition hover:opacity-100">← {{ $backToList ? 'Wróć do produktów' : $shop->name }}</a>

        <div class="mt-6 grid gap-8 md:grid-cols-2">
            {{-- Galeria --}}
            <div>
                @php($main = $product->mainImage())
                {{-- Karta produktu: zdjęcie takie, jak dodał sprzedawca — naturalne
                     proporcje, bez przycinania i bez wypełniania. --}}
                @if ($main)
                    <img id="st-gallery-main" src="{{ $main->url() }}" alt="{{ $product->name }}"
                        class="st-border w-full rounded-3xl border">
                @else
                    <div class="st-card st-border flex items-center justify-center overflow-hidden rounded-3xl border" style="aspect-ratio: 1 / 1;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-16 w-16 opacity-70" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none"/><path d="M4 17l4.5-4.5 3 3L15 12l5 5"/></svg>
                    </div>
                @endif

                @if ($product->images->count() > 1)
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach ($product->images as $img)
                            {{-- Miniatury: kwadrat, przycięte (object-cover) — jak kafle na wykazie. --}}
                            <button type="button" data-gallery-thumb="{{ $img->url() }}"
                                class="st-border h-16 w-16 overflow-hidden rounded-xl border transition hover:brightness-105">
                                <img src="{{ $img->url() }}" alt="" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Szczegóły --}}
            <div>
                <h1 class="st-brand text-3xl font-bold tracking-tight">{{ $product->name }}</h1>

                <p class="mt-4 text-3xl font-bold">{{ \App\Support\Money::pln($product->price_gross) }}@if ($product->sale_unit->isWeight())<span class="text-lg font-medium opacity-60"> / {{ $product->sale_unit->abbreviation() }}</span>@endif</p>
                @php($lowest = $product->lowestPriceLast30Days())
                @if ($lowest !== null)
                    <p class="mt-1 text-sm opacity-70">Najniższa cena z 30 dni: {{ \App\Support\Money::pln($lowest) }}</p>
                @endif

                <div class="mt-6">
                    <livewire:add-to-cart :product="$product" />
                </div>

                @if (filled($product->description))
                    <div class="st-border mt-8 border-t pt-6 leading-relaxed opacity-90">{!! $product->description !!}</div>
                @endif

                <x-storefront.tag-cloud :tags="$productTags" label="Tagi:" />
            </div>
        </div>
    </div>

    {{-- Przełączanie zdjęcia głównego z miniatur (zero zależności). --}}
    <script>
        (function () {
            var main = document.getElementById('st-gallery-main');
            if (!main) return;
            document.querySelectorAll('[data-gallery-thumb]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    main.src = btn.getAttribute('data-gallery-thumb');
                });
            });
        })();
    </script>
</x-layouts.storefront>
