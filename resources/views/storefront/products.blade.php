<x-layouts.storefront :shop="$shop" title="Produkty">
    <div class="mx-auto max-w-6xl px-6 pt-10">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Produkty'],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight">Wszystkie produkty</h1>

        <x-storefront.tag-cloud :tags="$tagCloud" :clearUrl="$hasFilters ? $clearUrl : null" />

        @if ($products->isEmpty())
            @if ($hasFilters)
                <p class="mt-8 opacity-70">Brak produktów dla wybranych filtrów. <a href="{{ $clearUrl }}" class="underline">Wyczyść filtry</a>.</p>
            @else
                <p class="mt-8 opacity-70">Nie ma jeszcze produktów.</p>
            @endif
        @else
            <div class="mt-6 flex flex-wrap items-center gap-2 text-sm">
                <span class="opacity-60">Sortuj:</span>
                @foreach ($sortOptions as $option)
                    @if ($option['active'])
                        <span class="st-btn rounded-full px-3 py-1 font-medium">{{ $option['label'] }}</span>
                    @else
                        <a href="{{ $option['url'] }}" rel="nofollow"
                            class="st-border rounded-full border px-3 py-1 opacity-80 transition hover:opacity-100">{{ $option['label'] }}</a>
                    @endif
                @endforeach
            </div>

            {{-- Liczba kolumn skalowana do wielkości katalogu (klasy statyczne dla Tailwinda). --}}
            <div class="mt-8 grid gap-6 sm:grid-cols-2 {{ ($columns ?? 3) === 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }}">
                @foreach ($products as $product)
                    <x-storefront.product-card :product="$product" :back="request()->getRequestUri()" />
                @endforeach
            </div>

            {{ $products->onEachSide(1)->links('storefront.pagination') }}
        @endif
    </div>
</x-layouts.storefront>
