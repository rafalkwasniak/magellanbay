<x-layouts.storefront :shop="$shop">
    {{-- Nagłówek sklepu --}}
    <header class="mx-auto max-w-3xl px-6 pt-16 text-center">
        @if ($shop->logo_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($shop->logo_path) }}"
                alt="{{ $shop->name }}" class="mx-auto mb-6 h-20 w-auto object-contain">
        @endif
        <h1 class="st-brand text-4xl font-bold tracking-tight sm:text-5xl">{{ $shop->name }}</h1>
        @if (filled($shop->description))
            <div class="mx-auto mt-5 max-w-2xl leading-relaxed opacity-80">{!! $shop->description !!}</div>
        @endif
    </header>

    <main class="mx-auto max-w-6xl px-6 pt-12">
        @switch($products->count())

            {{-- 1 produkt → hero: produkt jest bohaterem strony. --}}
            @case(1)
                @php($p = $products->first())
                @php($main = $p->mainImage())
                <section class="grid items-center gap-8 md:grid-cols-2">
                    <div class="st-card st-border overflow-hidden rounded-3xl border" style="aspect-ratio: 1 / 1;">
                        @if ($main)
                            <img src="{{ $main->url() }}" alt="{{ $p->name }}" class="h-full w-full object-cover">
                        @else
                            <div class="st-btn flex h-full w-full items-center justify-center">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-16 w-16 opacity-70" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none"/><path d="M4 17l4.5-4.5 3 3L15 12l5 5"/></svg>
                            </div>
                        @endif
                    </div>
                    <div class="text-center md:text-left">
                        <h2 class="st-brand text-3xl font-bold tracking-tight">{{ $p->name }}</h2>
                        @if (filled($p->description))
                            <div class="mt-4 leading-relaxed opacity-80">{!! $p->description !!}</div>
                        @endif
                        <p class="mt-5 text-2xl font-bold">{{ number_format((float) $p->price_gross, 2, ',', ' ') }} zł</p>
                        <a href="{{ $p->storefrontPath() }}" class="st-btn mt-6 inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">Zobacz produkt</a>
                    </div>
                </section>
                @break

            {{-- 2 produkty → para 50/50: dwa duże panele. --}}
            @case(2)
                <section class="grid gap-6 sm:grid-cols-2">
                    @foreach ($products as $p)
                        <x-storefront.product-card :product="$p" aspect="4 / 5" />
                    @endforeach
                </section>
                @break

            {{-- 3 produkty → tryptyk: jeden szeroki na górze + dwa pod spodem. --}}
            @case(3)
                <section class="space-y-6">
                    <x-storefront.product-card :product="$products[0]" aspect="16 / 9" />
                    <div class="grid gap-6 sm:grid-cols-2">
                        <x-storefront.product-card :product="$products[1]" />
                        <x-storefront.product-card :product="$products[2]" />
                    </div>
                </section>
                @break

            @case(0)
                {{-- Aktywny sklep ma ≥1 produkt; ten przypadek to bezpiecznik. --}}
                @break

            {{-- 4–6 → wspólna witryna (siatka). --}}
            @default
                <section class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($products as $p)
                        <x-storefront.product-card :product="$p" />
                    @endforeach
                </section>
        @endswitch

        @if ($totalProducts > $products->count())
            <div class="mt-12 text-center">
                <a href="/produkty" class="st-btn inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">
                    Zobacz wszystkie produkty
                </a>
            </div>
        @endif

        @if (count($tagCloud))
            <div class="st-border mt-16 border-t pt-8 text-center">
                <x-storefront.tag-cloud :tags="$tagCloud" label="Przeglądaj po tagach:" :center="true" />
            </div>
        @endif
    </main>
</x-layouts.storefront>
