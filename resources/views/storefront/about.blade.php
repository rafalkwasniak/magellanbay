<x-storefront.information-shell :shop="$shop" :heading="$title">
    <div class="st-prose">{!! \App\Support\Prose::render($shop->description ?? '') !!}</div>
</x-storefront.information-shell>
