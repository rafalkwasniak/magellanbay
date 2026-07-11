<x-layouts.storefront :shop="$shop" :title="$title">
    <div class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => $title],
        ]" />

        <h1 class="st-brand mt-4 text-3xl font-bold tracking-tight sm:text-4xl">{{ $title }}</h1>

        <div class="st-prose st-border mt-8 border-t pt-8">{!! $shop->description !!}</div>
    </div>
</x-layouts.storefront>
