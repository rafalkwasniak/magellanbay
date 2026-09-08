@props(['tags', 'label' => 'Filtruj:', 'heading' => null, 'clearUrl' => null, 'center' => false])

{{-- Wspólna chmura tagów storefrontu. `tags` = lista pozycji
     {name, count, url, active}; count === null chowa liczbę (np. tag wybrany
     albo tag na karcie produktu). Używana na wykazie (fasety), głównej
     (przeglądanie) i karcie produktu (tagi produktu).

     `heading` daje nagłówek nad chmurą — taki sam jak nad podziałami katalogu.
     Potrzebny tam, gdzie chmura stoi obok innych grup filtrów: bez podpisu
     wyglądała jak ogon poprzedniej osi, a nie jak własny filtr. `label` to
     wariant inline, przed pierwszym kaflem; puste chowa podpis zupełnie. --}}
@if (count($tags))
    <div class="{{ $heading ? 'mt-4' : 'mt-6' }}">
        @if ($heading)
            <p class="text-xs uppercase tracking-wide opacity-60">{{ $heading }}</p>
        @endif

        <div class="flex flex-wrap items-center gap-2 text-sm {{ $heading ? 'mt-2' : '' }} {{ $center ? 'justify-center' : '' }}">
            @if (filled($label))
                <span class="opacity-60">{{ $label }}</span>
            @endif
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
    </div>
@endif
