<x-layouts.storefront :shop="$shop" :title="$product->name">
    <div class="mx-auto max-w-6xl px-6 pt-10">
        <x-storefront.breadcrumbs :back="$back" :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Produkty', 'url' => '/produkty'],
            ['label' => $product->name],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight">{{ $product->name }}</h1>

        <div class="st-border mt-8 border-t"></div>

        <div class="mt-8 grid gap-8 md:grid-cols-5">
            {{-- Galeria — szersza kolumna (3/5) --}}
            <div class="md:col-span-3">
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

            {{-- Zakup — węższa kolumna po prawej (2/5) --}}
            <div class="md:col-span-2">
                <p class="text-3xl font-bold"><span class="text-lg font-medium opacity-60">Cena:</span> {{ \App\Support\Money::pln($product->price_gross) }}@if ($product->sale_unit->isWeight())<span class="text-lg font-medium opacity-60"> / {{ $product->sale_unit->abbreviation() }}</span>@endif</p>
                @php($lowest = $product->lowestPriceLast30Days())
                @if ($lowest !== null)
                    <p class="mt-1 text-sm opacity-70">Najniższa cena z 30 dni: {{ \App\Support\Money::pln($lowest) }}</p>
                @endif

                {{-- Dostępność jako dana produktu (statyczna); realny limit i tak pilnuje komponent koszyka. --}}
                @if ($product->track_stock && $product->stock !== null)
                    <p class="mt-3 text-sm opacity-70">Dostępne: {{ $product->sale_unit->formatAmount((float) $product->stock) }} {{ $product->sale_unit->abbreviation() }}</p>
                @endif

                {{-- Tylko przycisk „Do koszyka", wyrównany do prawej krawędzi kolumny
                     (compact = komponent nie dubluje linii dostępności). --}}
                <div class="mt-6 flex justify-end">
                    <livewire:add-to-cart :product="$product" :compact="true" />
                </div>

                {{-- Tagi w kaflu jak na wykazie (Filtruj). --}}
                @if (count($productTags))
                    <div class="st-card st-border mt-8 rounded-3xl border p-6 text-left">
                        <h2 class="st-brand font-serif text-xl font-normal tracking-tight">Podobne produkty</h2>
                        <x-storefront.tag-cloud :tags="$productTags" label="" />
                    </div>
                @endif
            </div>
        </div>

        {{-- Opis na całą szerokość pod galerią i zakupem. --}}
        @if (filled($product->description))
            <div class="mt-12">
                <h2 class="st-brand font-serif text-2xl font-normal tracking-tight sm:text-3xl">O produkcie</h2>
                <div class="st-prose st-border mt-6 border-t pt-6 opacity-90">{!! $product->description !!}</div>
            </div>
        @endif
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
