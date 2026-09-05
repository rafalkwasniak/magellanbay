{{-- Polityka prywatności pod stałym adresem działu „Informacje".

     Treść przychodzi z kontrolera jako gotowy HTML i ma DWA źródła, zależnie
     od trybu: dokument platformy w Kramio, strona systemowa sklepu w sklepie
     dedykowanym (patrz Storefront\PageController::privacy). Widok nie musi
     o tym wiedzieć — dostaje treść albo nic. --}}
<x-storefront.information-shell :shop="$shop" :heading="$title">
    @if (filled($content))
        <div class="st-prose">{!! \App\Support\Prose::render($content) !!}</div>
    @else
        <p class="opacity-70">Treść jest w przygotowaniu.</p>
    @endif
</x-storefront.information-shell>
