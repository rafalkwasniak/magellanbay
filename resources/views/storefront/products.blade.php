{{-- Wykaz produktów — ten sam widok dla /produkty i dla stron kategorii.
     `$category` niepuste znaczy, że stoimy na stronie jednego podziału:
     zmienia się nagłówek, okruszki i opis, ale filtrowanie jest to samo. --}}
@php($isCategory = ($category ?? null) !== null)
@php($isPartner = ($licensor ?? null) !== null)

<x-layouts.storefront :shop="$shop"
    :title="$isPartner ? $licensor->name : ($isCategory ? $category->name : 'Produkty')"
    :description="$isPartner
        ? 'Produkty z logotypem '.$licensor->name.' w sklepie '.$shop->name.'.'
        : ($isCategory
            ? ($category->description ? \Illuminate\Support\Str::limit(strip_tags($category->description), 155) : $category->name.' — produkty w sklepie '.$shop->name)
            : \App\Support\Seo::listingDescription($shop))">
    <div class="mx-auto max-w-6xl px-6 pt-10">
        {{-- Okruszki niosą hierarchię, której NIE MA w adresie: „Rzym" mieszka
             pod /geografia/rzym, żeby przeniesienie go pod innego rodzica nie
             było przeprowadzką adresu. Ścieżkę pokazujemy tutaj. --}}
        <x-storefront.breadcrumbs :items="array_merge(
            [['label' => $shop->name, 'url' => '/'], ['label' => 'Produkty', 'url' => '/produkty']],
            $isPartner ? [['label' => $licensor->name]] : [],
            collect($crumbs ?? [])->map(fn ($node) => [
                'label' => $node->name,
                'url' => $node->id === ($category?->id) ? null : $node->storefrontPath(),
            ])->all(),
        )" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">
            {{ $isPartner ? $licensor->name : ($isCategory ? $category->name : 'Produkty') }}
        </h1>

        {{-- Strona partnera mówi WPROST, czym jest. Kupujący, który trafił tu
             z linku od organizatora biegu, ma od razu wiedzieć, że nie jest
             na stronie tego organizatora, tylko w sklepie, który ma jego znak
             na licencji. --}}
        @if ($isPartner)
            <p class="mt-4 max-w-2xl opacity-80">
                Produkty z logotypem <span class="font-medium">{{ $licensor->name }}</span>, oferowane na podstawie
                udzielonej nam licencji. Część ceny każdego z nich trafia do właściciela znaku.
            </p>
        @endif

        {{-- Sprzedaż serii wstrzymana. Dział zostaje widoczny razem z produktami:
             wracają za tydzień, a wygaszenie strony kosztowałoby pozycję
             w wyszukiwarce, której nie odzyskuje się w tydzień. --}}
        @if ($isCategory && $category->salesSuspended())
            <div class="st-border st-card mt-6 rounded-2xl border px-5 py-4">
                <p class="font-medium">Sprzedaż wstrzymana</p>
                <p class="mt-1 text-sm opacity-80">{{ $category->suspensionMessage() }}</p>
            </div>
        @endif

        @if ($isCategory && filled($category->description))
            <div class="st-prose mt-4 max-w-3xl opacity-80">{!! nl2br(e($category->description)) !!}</div>
        @endif

        {{-- Zejście niżej w tej samej gałęzi (Włochy → Rzym, Mediolan…). --}}
        @if ($isCategory && ($children ?? collect())->isNotEmpty())
            <div class="mt-6 flex flex-wrap gap-2">
                @foreach ($children as $child)
                    <a href="{{ $child->storefrontPath() }}"
                        class="st-border st-card rounded-full border px-4 py-1.5 text-sm transition hover:opacity-80">{{ $child->name }}</a>
                @endforeach
            </div>
        @endif

        <div class="st-border mt-8 border-t"></div>

        {{-- Filtruj + Sortuj obok siebie: Filtruj rośnie (flex-1), Sortuj to
             wąski select (mniej miejsca, niższy wiersz). Na wąskich ekranach
             kafle spadają pod siebie. --}}
        @if (count($tagCloud) || count($axisPanels ?? []) || $products->isNotEmpty())
            <div class="mt-8 flex flex-col gap-4 sm:flex-row">
                @if (count($tagCloud) || count($axisPanels ?? []))
                    <div class="st-card st-border flex-1 rounded-3xl border p-6 text-left">
                        <h2 class="st-brand st-box-title">Filtruj</h2>

                        {{-- Podziały katalogu: każda oś osobno, bo są niezależne.
                             Liczba przy pozycji obejmuje całą gałąź, inaczej
                             „Włochy" pokazywałyby zero, mając pod sobą Rzym. --}}
                        @foreach ($axisPanels ?? [] as $panel)
                            <div class="mt-4">
                                <p class="text-xs uppercase tracking-wide opacity-60">{{ $panel['label'] }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($panel['items'] as $item)
                                        <a href="{{ $item['url'] }}"
                                            class="st-border rounded-full border px-3 py-1 text-sm transition hover:opacity-80 {{ $item['active'] ? 'st-card font-medium' : '' }}">
                                            {{ $item['name'] }}
                                            <span class="opacity-50">{{ $item['active'] ? '×' : $item['count'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        @if (count($tagCloud))
                            <x-storefront.tag-cloud :tags="$tagCloud" label="" :clearUrl="$hasFilters ? $clearUrl : null" />
                        @elseif ($hasFilters)
                            <a href="{{ $clearUrl }}" class="mt-4 inline-block text-sm underline opacity-70">Wyczyść filtry</a>
                        @endif
                    </div>
                @endif

                @if ($products->isNotEmpty())
                    <div class="st-card st-border rounded-3xl border p-6 text-left sm:w-64 sm:shrink-0">
                        <h2 class="st-brand st-box-title">Sortuj</h2>
                        <select onchange="if (this.value) window.location.href = this.value"
                            class="st-border mt-4 w-full rounded-xl border bg-transparent px-4 py-2 text-sm">
                            @foreach ($sortOptions as $option)
                                <option value="{{ $option['url'] }}" @selected($option['active'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        @endif

        @if ($products->isEmpty())
            @if ($hasFilters)
                <p class="mt-8 opacity-70">Brak produktów dla wybranych filtrów. <a href="{{ $clearUrl }}" class="underline">Wyczyść filtry</a>.</p>
            @elseif ($isCategory)
                <p class="mt-8 opacity-70">W tym dziale nie ma jeszcze produktów.</p>
            @else
                <p class="mt-8 opacity-70">Nie ma jeszcze produktów.</p>
            @endif
        @else
            {{-- Liczba kolumn skalowana do wielkości katalogu (klasy statyczne dla Tailwinda). --}}
            {{-- Pytanie „czy w tym sklepie da się kupić" zadajemy RAZ na wykaz, nie
                 raz na kafel — odpowiedź jest ta sama dla wszystkich produktów. --}}
            @php($canOrder = $shop->acceptsOrders())
            <div class="mt-8 grid gap-6 sm:grid-cols-2 {{ ($columns ?? 3) === 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }}">
                @foreach ($products as $product)
                    <x-storefront.product-card :product="$product" :back="request()->getRequestUri()" :can-order="$canOrder" />
                @endforeach
            </div>

            {{ $products->onEachSide(1)->links('storefront.pagination') }}
        @endif
    </div>
</x-layouts.storefront>
