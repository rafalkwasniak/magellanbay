@props(['shop', 'heading', 'description' => null])

{{-- Wspólna skorupa działu „Informacje" na storefroncie. Pełną szerokością
     renderuje nagłówek (breadcrumbs → H1 → linia), a pod linią dwie kolumny:
     lewe sticky submenu i prawą treść z `$slot`. Submenu jest DYNAMICZNE — pozycje
     z Shop::informationMenu() (wirtualna „O sklepie" jeśli opis długi + opublikowane
     strony wg `position`, Regulamin włącznie). Aktywną pozycję rozpoznajemy po
     bieżącym URL. Gdy jest tylko jedna pozycja, submenu pomijamy (treść pełną
     szerokością). Konwencja „tytuł = końcówka breadcrumbs". Bliźniak:
     components/storefront/account-shell. --}}
@php
    $infoMenu = $shop->informationMenu();
    $current = '/'.ltrim(request()->path(), '/');
    $hasMenu = count($infoMenu) > 1;
    // Offset przyklejenia lewego menu = wysokość nagłówka-winiety (sticky top-0)
    // + oddech, żeby menu zatrzymywało się TUŻ POD belką, a nie chowało się pod
    // nią. Jedyna zmienna wysokości winiety to obecność logo (h-28) kontra sama
    // nazwa serif — stąd dwie wartości. Inline (bez nowej klasy Tailwind → bez
    // przebudowy CSS); `top` działa tylko przy lg:sticky, na mobile jest bez echa.
    $stickyTop = filled($shop->logo_path) ? '13rem' : '8.5rem';
@endphp

<x-layouts.storefront :shop="$shop" :title="$heading" :description="$description">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => $heading],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">{{ $heading }}</h1>

        <div @class([
            'st-border mt-8 border-t pt-8',
            'grid gap-8 lg:grid-cols-[220px_minmax(0,1fr)]' => $hasMenu,
        ])>
            {{-- Lewe submenu (tylko gdy jest co między czym przełączać) --}}
            @if ($hasMenu)
                <aside class="lg:sticky lg:self-start" style="top: {{ $stickyTop }};">
                    <nav class="flex flex-col gap-1 text-sm font-medium">
                        @foreach ($infoMenu as $item)
                            <a href="{{ $item['url'] }}" wire:navigate
                                @class([
                                    'rounded-xl px-4 py-2.5 transition',
                                    'st-btn' => $current === $item['url'],
                                    'st-border border hover:brightness-95' => $current !== $item['url'],
                                ])>{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                </aside>
            @endif

            {{-- Prawa kolumna --}}
            <div class="min-w-0">
                {{ $slot }}
            </div>
        </div>
    </main>
</x-layouts.storefront>
