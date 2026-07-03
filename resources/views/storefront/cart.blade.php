<x-layouts.storefront :shop="$shop" title="Koszyk">
    <main class="mx-auto max-w-6xl px-6 py-12">
        <h1 class="st-brand mb-8 text-2xl font-bold tracking-tight sm:text-3xl">Koszyk</h1>

        <livewire:cart :shop-id="$shop->id" />
    </main>
</x-layouts.storefront>
