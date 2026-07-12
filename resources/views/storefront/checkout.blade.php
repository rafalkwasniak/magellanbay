<x-layouts.storefront :shop="$shop" title="Kasa">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Koszyk', 'url' => '/koszyk'],
            ['label' => 'Kasa'],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Kasa</h1>

        <div class="st-border mt-8 border-t pt-8">
            <livewire:checkout :shop-id="$shop->id" />
        </div>
    </main>
</x-layouts.storefront>
