<x-layouts.storefront :shop="$shop">
    <header class="mx-auto max-w-3xl px-6 pt-20 text-center">
        @if ($shop->logo_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($shop->logo_path) }}"
                alt="{{ $shop->name }}" class="mx-auto mb-8 h-24 w-auto object-contain">
        @endif

        <h1 class="st-brand text-4xl font-bold tracking-tight sm:text-5xl">{{ $shop->name }}</h1>

        @if (filled($shop->description))
            <div class="mx-auto mt-6 max-w-2xl leading-relaxed opacity-90">{!! $shop->description !!}</div>
        @endif

        {{-- Placeholder — wykaz produktów dojdzie następnym krokiem. --}}
        <a href="#" class="st-btn mt-10 inline-block rounded-full px-7 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">
            Zobacz produkty
        </a>
    </header>
</x-layouts.storefront>
