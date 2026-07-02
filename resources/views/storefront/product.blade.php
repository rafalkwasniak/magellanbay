<x-layouts.storefront :shop="$shop" :title="$product->name">
    <div class="mx-auto max-w-5xl px-6 pt-10">
        @php $backToList = str_starts_with($back, '/produkty'); @endphp
        <a href="{{ $back }}" class="text-sm opacity-70 transition hover:opacity-100">← {{ $backToList ? 'Wróć do produktów' : $shop->name }}</a>

        <div class="mt-6 grid gap-8 md:grid-cols-2">
            {{-- Galeria --}}
            <div>
                @php($main = $product->mainImage())
                <div class="st-card st-border overflow-hidden rounded-3xl border" style="aspect-ratio: 1 / 1;">
                    @if ($main)
                        <img id="st-gallery-main" src="{{ $main->url() }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                    @else
                        <div class="st-btn flex h-full w-full items-center justify-center">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-16 w-16 opacity-70" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none"/><path d="M4 17l4.5-4.5 3 3L15 12l5 5"/></svg>
                        </div>
                    @endif
                </div>

                @if ($product->images->count() > 1)
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach ($product->images as $img)
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

                <p class="mt-4 text-3xl font-bold">{{ number_format((float) $product->price_gross, 2, ',', ' ') }} zł</p>
                @php($lowest = $product->lowestPriceLast30Days())
                @if ($lowest !== null)
                    <p class="mt-1 text-sm opacity-70">Najniższa cena z 30 dni: {{ number_format($lowest, 2, ',', ' ') }} zł</p>
                @endif

                <button type="button" class="st-btn mt-6 w-full rounded-full px-8 py-3.5 text-sm font-semibold shadow-sm transition hover:brightness-105 sm:w-auto">
                    Dodaj do koszyka
                </button>
                <p class="mt-2 text-xs opacity-50">Koszyk uruchomimy w następnym kroku.</p>

                @if (filled($product->description))
                    <div class="st-border mt-8 border-t pt-6 leading-relaxed opacity-90">{!! $product->description !!}</div>
                @endif
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
