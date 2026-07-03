@props(['items' => []])

{{-- Okruszki (breadcrumb) storefrontu. `items` = lista pozycji od korzenia do
     bieżącej strony: [{label, url?}]. Pozycja bez `url` = strona bieżąca
     (ostatnia, aria-current). Korzeniem jest zawsze nazwa sklepu, nie generyczne
     „Sklep" (SEO: fraza sklepu ląduje w okruszkach Google). Respektuje motyw
     przez klasy st-*. Poza widoczną ścieżką dokłada schema.org BreadcrumbList
     (JSON-LD), z którego Google renderuje okruszki w wynikach wyszukiwania. --}}
@php $items = array_values(array_filter($items)); @endphp

@if (count($items) > 1)
    @php $last = count($items) - 1; @endphp
    <nav aria-label="Ścieżka nawigacji" class="text-sm">
        <ol class="flex flex-wrap items-center gap-1.5 opacity-70">
            @foreach ($items as $i => $item)
                <li class="flex items-center gap-1.5">
                    @if ($i !== $last && ! empty($item['url']))
                        <a href="{{ $item['url'] }}" class="transition hover:underline hover:opacity-100">{{ $item['label'] }}</a>
                    @else
                        <span @class(['font-medium opacity-100' => $i === $last]) @if ($i === $last) aria-current="page" @endif>{{ $item['label'] }}</span>
                    @endif
                    @if ($i !== $last)
                        <span aria-hidden="true" class="opacity-40">/</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->map(fn ($item, $i) => array_filter([
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['label'],
                'item' => empty($item['url']) ? null : url($item['url']),
            ]))->values()->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endif
