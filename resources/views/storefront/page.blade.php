<x-storefront.information-shell :shop="$shop" :heading="$page->title"
    :description="\App\Support\Seo::pageDescription($page, $shop)">
    @unless ($page->published)
        {{-- Podgląd właściciela/administratora: strona ukryta dla klientów. --}}
        <div class="st-border st-card mb-6 rounded-2xl border px-4 py-3 text-sm opacity-80">
            Podgląd — ta strona jest ukryta i nie widzą jej klienci.
        </div>
    @endunless

    @if (filled($page->content))
        <div class="st-prose">{!! \App\Support\Prose::render($page->content ?? '') !!}</div>
    @else
        <p class="opacity-70">Treść tej strony jest w przygotowaniu.</p>
    @endif
</x-storefront.information-shell>
