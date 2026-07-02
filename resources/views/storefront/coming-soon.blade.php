<x-layouts.storefront :shop="$shop" title="Już wkrótce">
    <div class="mx-auto flex min-h-[70vh] max-w-xl flex-col items-center justify-center px-6 text-center">
        @if ($shop->logo_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($shop->logo_path) }}"
                alt="{{ $shop->name }}" class="mb-8 h-20 w-auto object-contain">
        @endif

        <h1 class="st-brand text-3xl font-bold tracking-tight sm:text-4xl">{{ $shop->name }}</h1>
        <p class="mt-4 text-lg opacity-80">Sklep jest w przygotowaniu — zajrzyj już wkrótce!</p>
    </div>
</x-layouts.storefront>
