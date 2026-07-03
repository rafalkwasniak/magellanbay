<x-layouts.storefront :shop="$shop" title="Kasa">
    <main class="mx-auto max-w-6xl px-6 py-12">
        <div class="mb-8 flex items-baseline justify-between gap-4">
            <h1 class="st-brand text-2xl font-bold tracking-tight sm:text-3xl">Kasa</h1>
            <a href="/koszyk" wire:navigate class="text-sm underline underline-offset-4 opacity-70 hover:opacity-100">← Wróć do koszyka</a>
        </div>

        <livewire:checkout :shop-id="$shop->id" />
    </main>
</x-layouts.storefront>
