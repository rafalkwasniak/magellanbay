@props(['product', 'aspect' => '4 / 3', 'back' => null])

@php
    $main = $product->mainImage();
    // Kafel niesie adres powrotu (URL listy z filtrami/stroną), by przycisk
    // „Wróć" na produkcie wrócił dokładnie tu. Kodujemy, bo w wartości jest `&`.
    $href = $product->storefrontPath().($back ? '?powrot='.urlencode($back) : '');
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'st-card st-border group block overflow-hidden rounded-2xl border transition hover:brightness-[1.02]']) }}>
    <div class="w-full overflow-hidden" style="aspect-ratio: {{ $aspect }};">
        @if ($main)
            <img src="{{ $main->url() }}" alt="{{ $product->name }}"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <div class="st-btn flex h-full w-full items-center justify-center">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-10 w-10 opacity-70" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="16" rx="2" />
                    <circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none" />
                    <path d="M4 17l4.5-4.5 3 3L15 12l5 5" />
                </svg>
            </div>
        @endif
    </div>
    <div class="p-4">
        <h3 class="font-semibold leading-snug">{{ $product->name }}</h3>
        <p class="st-brand mt-1 text-lg font-bold">{{ number_format((float) $product->price_gross, 2, ',', ' ') }} zł</p>
    </div>
</a>
