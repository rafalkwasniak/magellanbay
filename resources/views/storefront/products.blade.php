<x-layouts.storefront :shop="$shop" title="Produkty">
    <div class="mx-auto max-w-6xl px-6 pt-10">
        <a href="/" class="text-sm opacity-70 transition hover:opacity-100">← {{ $shop->name }}</a>
        <h1 class="st-brand mt-4 text-3xl font-bold tracking-tight">Wszystkie produkty</h1>

        @if ($tagCloud)
            <div class="mt-6 flex flex-wrap items-center gap-2 text-sm">
                <span class="opacity-60">Filtruj:</span>
                @foreach ($tagCloud as $tag)
                    <a href="{{ $tag['url'] }}" rel="nofollow"
                        class="{{ $tag['active'] ? 'st-btn font-medium' : 'st-border border opacity-80 hover:opacity-100' }} inline-flex items-center gap-1 rounded-full px-3 py-1 transition">
                        @if ($tag['active'])<span aria-hidden="true">×</span>@endif
                        {{ $tag['name'] }}
                        <span class="opacity-50">{{ $tag['count'] }}</span>
                    </a>
                @endforeach
                @if ($hasFilters)
                    <a href="{{ $clearUrl }}" rel="nofollow" class="opacity-60 underline transition hover:opacity-100">Wyczyść</a>
                @endif
            </div>
        @endif

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

            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $product)
                    <x-storefront.product-card :product="$product" :back="request()->getRequestUri()" />
                @endforeach
            </div>

            {{ $products->onEachSide(1)->links('storefront.pagination') }}
        @endif
    </div>
</x-layouts.storefront>
