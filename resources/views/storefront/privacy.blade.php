<x-layouts.storefront :shop="$shop" :title="$title">
    <div class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => $title],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">{{ $title }}</h1>

        @if ($document && filled($document->content))
            <div class="st-prose st-border mt-8 border-t pt-8">{!! $document->content !!}</div>
        @else
            <p class="mt-8 opacity-70">Treść jest w przygotowaniu.</p>
        @endif
    </div>
</x-layouts.storefront>
