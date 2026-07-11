<x-layouts.storefront :shop="$shop" :title="$page->title">
    <div class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => $page->title],
        ]" />

        @unless ($page->published)
            {{-- Podgląd właściciela/administratora: strona ukryta dla klientów. --}}
            <div class="st-border st-card mt-4 rounded-2xl border px-4 py-3 text-sm opacity-80">
                Podgląd — ta strona jest ukryta i nie widzą jej klienci.
            </div>
        @endunless

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">{{ $page->title }}</h1>

        @if (filled($page->content))
            <div class="st-prose st-border mt-8 border-t pt-8">{!! $page->content !!}</div>
        @else
            <p class="mt-8 opacity-70">Treść tej strony jest w przygotowaniu.</p>
        @endif
    </div>
</x-layouts.storefront>
