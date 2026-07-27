<x-layouts.storefront :shop="$shop" title="Koszyk" :noindex="true">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Koszyk'],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Koszyk</h1>

        <div class="st-border mt-8 border-t pt-8">
            <livewire:cart :shop-id="$shop->id" />
        </div>
    </main>
</x-layouts.storefront>
