<x-storefront.information-shell :shop="$shop" :heading="$title">
    @if ($document && filled($document->content))
        <div class="st-prose">{!! \App\Support\Prose::render($document->content ?? '') !!}</div>
    @else
        <p class="opacity-70">Treść jest w przygotowaniu.</p>
    @endif
</x-storefront.information-shell>
