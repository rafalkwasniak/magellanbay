@props(['tags', 'label' => 'Filtruj:', 'clearUrl' => null, 'center' => false])

{{-- Wspólna chmura tagów storefrontu. `tags` = lista pozycji
     {name, count, url, active}; count === null chowa liczbę (np. tag wybrany
     albo tag na karcie produktu). Używana na wykazie (fasety), głównej
     (przeglądanie) i karcie produktu (tagi produktu). --}}
@if (count($tags))
    <div class="mt-6 flex flex-wrap items-center gap-2 text-sm {{ $center ? 'justify-center' : '' }}">
        <span class="opacity-60">{{ $label }}</span>
        @foreach ($tags as $tag)
            <a href="{{ $tag['url'] }}" rel="nofollow"
                class="{{ $tag['active'] ? 'st-btn font-medium' : 'st-border border opacity-80 hover:opacity-100' }} inline-flex items-center gap-1 rounded-full px-3 py-1 transition">
                @if ($tag['active'])<span aria-hidden="true">×</span>@endif
                {{ $tag['name'] }}
                @if (! is_null($tag['count']))<span class="opacity-50">{{ $tag['count'] }}</span>@endif
            </a>
        @endforeach
        @if ($clearUrl)
            <a href="{{ $clearUrl }}" rel="nofollow" class="opacity-60 underline transition hover:opacity-100">Wyczyść</a>
        @endif
    </div>
@endif
