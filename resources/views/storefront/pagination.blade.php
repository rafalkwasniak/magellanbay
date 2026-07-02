@if ($paginator->hasPages())
    <nav class="mt-10 flex flex-wrap items-center justify-between gap-4 text-sm">
        {{-- Lewo: ile pokazano --}}
        <p class="opacity-70">
            Wyświetlono od <span class="font-medium">{{ $paginator->firstItem() }}</span> do <span class="font-medium">{{ $paginator->lastItem() }}</span>
            z <span class="font-medium">{{ $paginator->total() }}</span>
        </p>

        {{-- Prawo: szybki dostęp do stron --}}
        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="st-border flex h-9 w-9 items-center justify-center rounded-lg border opacity-30" aria-hidden="true">←</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Poprzednia strona"
                    class="st-border flex h-9 w-9 items-center justify-center rounded-lg border transition hover:brightness-105">←</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 opacity-50">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="st-btn flex h-9 min-w-9 items-center justify-center rounded-lg px-2 font-semibold" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="st-border flex h-9 min-w-9 items-center justify-center rounded-lg border px-2 transition hover:brightness-105">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Następna strona"
                    class="st-border flex h-9 w-9 items-center justify-center rounded-lg border transition hover:brightness-105">→</a>
            @else
                <span class="st-border flex h-9 w-9 items-center justify-center rounded-lg border opacity-30" aria-hidden="true">→</span>
            @endif
        </div>
    </nav>
@endif
