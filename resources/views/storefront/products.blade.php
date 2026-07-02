<x-layouts.storefront :shop="$shop" title="Produkty">
    <div class="mx-auto max-w-6xl px-6 pt-10">
        <a href="/" class="text-sm opacity-70 transition hover:opacity-100">← {{ $shop->name }}</a>
        <h1 class="st-brand mt-4 text-3xl font-bold tracking-tight">Wszystkie produkty</h1>

        @if ($products->isEmpty())
            <p class="mt-8 opacity-70">Nie ma jeszcze produktów.</p>
        @else
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $product)
                    <x-storefront.product-card :product="$product" />
                @endforeach
            </div>

            @if ($products->hasPages())
                <nav class="mt-10 flex items-center justify-between text-sm">
                    @if ($products->onFirstPage())
                        <span class="opacity-40">← Poprzednie</span>
                    @else
                        <a href="{{ $products->previousPageUrl() }}" class="st-brand font-semibold">← Poprzednie</a>
                    @endif

                    <span class="opacity-60">Strona {{ $products->currentPage() }} z {{ $products->lastPage() }}</span>

                    @if ($products->hasMorePages())
                        <a href="{{ $products->nextPageUrl() }}" class="st-brand font-semibold">Następne →</a>
                    @else
                        <span class="opacity-40">Następne →</span>
                    @endif
                </nav>
            @endif
        @endif
    </div>
</x-layouts.storefront>
