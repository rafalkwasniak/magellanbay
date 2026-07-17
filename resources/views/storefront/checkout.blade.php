@php($geowidgetToken = config('services.inpost.geowidget_token'))

@if ($shop->parcelLockerAvailable() && filled($geowidgetToken))
    @push('head')
        {{-- Geowidget InPostu: mapa wyboru paczkomatu. Ładowany tylko w kasie
             sklepu, który oferuje paczkomat i ma token. Skrypt async — pole kodu
             działa niezależnie, więc wolne łącze nie blokuje zakupu. --}}
        <link rel="stylesheet" href="https://geowidget.inpost.pl/inpost-geowidget.css">
        <script src="https://geowidget.inpost.pl/inpost-geowidget.js" defer></script>
        <script>
            // Most między callbackiem widgetu (wymaga GLOBALNEJ funkcji w atrybucie
            // onpoint) a Alpine w kasie — przez zdarzenie okna, bez wiązania na sztywno.
            window.afterInpostPointSelected = function (point) {
                window.dispatchEvent(new CustomEvent('inpost-point-selected', { detail: point }));
            };
        </script>
    @endpush
@endif

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
