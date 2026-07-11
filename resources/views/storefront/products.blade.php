<x-layouts.storefront :shop="$shop" title="Produkty">
    <div class="mx-auto max-w-6xl px-6 pt-10">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Produkty'],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight">Produkty</h1>

        <div class="st-border mt-8 border-t"></div>

        {{-- Filtruj + Sortuj obok siebie: Filtruj rośnie (flex-1), Sortuj to
             wąski select (mniej miejsca, niższy wiersz). Na wąskich ekranach
             kafle spadają pod siebie. --}}
        @if (count($tagCloud) || $products->isNotEmpty())
            <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-start">
                @if (count($tagCloud))
                    <div class="st-card st-border flex-1 rounded-3xl border p-6 text-left">
                        <h2 class="st-brand font-serif text-xl font-normal tracking-tight">Filtruj</h2>
                        <x-storefront.tag-cloud :tags="$tagCloud" label="" :clearUrl="$hasFilters ? $clearUrl : null" />
                    </div>
                @endif

                @if ($products->isNotEmpty())
                    <div class="st-card st-border rounded-3xl border p-6 text-left sm:w-64 sm:shrink-0">
                        <h2 class="st-brand font-serif text-xl font-normal tracking-tight">Sortuj</h2>
                        <select onchange="if (this.value) window.location.href = this.value"
                            class="st-border mt-4 w-full rounded-xl border bg-transparent px-4 py-2 text-sm">
                            @foreach ($sortOptions as $option)
                                <option value="{{ $option['url'] }}" @selected($option['active'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
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
