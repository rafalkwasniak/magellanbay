{{-- `canOrder` = czy sklep ma czym dostarczyć i czym opłacić zamówienie. Kafel
     nie pyta o to sam (jedna odpowiedź na cały wykaz, nie jedna na produkt),
     więc dostaje gotową. Domyślnie false: kafel bez tej wiedzy woli nie
     obiecywać zakupu, niż obiecać go tam, gdzie się nie da dokończyć. --}}
@props(['product', 'aspect' => '1 / 1', 'back' => null, 'canOrder' => false])

@php
    $main = $product->mainImage();
    // Kafel niesie adres powrotu (URL listy z filtrami/stroną), by przycisk
    // „Wróć" na produkcie wrócił dokładnie tu. Kodujemy, bo w wartości jest `&`.
    $href = $product->storefrontPath().($back ? '?powrot='.urlencode($back) : '');
@endphp

<div {{ $attributes->merge(['class' => 'st-card st-border group flex flex-col overflow-hidden rounded-2xl border transition hover:brightness-[1.02]']) }}>
    <a href="{{ $href }}" class="block">
        {{-- Listing: kadr kwadratowy, przycięty (object-cover) — równa siatka. --}}
        <div class="w-full overflow-hidden" style="aspect-ratio: {{ $aspect }};">
            @if ($main)
                <img src="{{ $main->url() }}" alt="{{ $product->name }}"
                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
            @else
                <div class="st-btn flex h-full w-full items-center justify-center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-10 w-10 opacity-70" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="16" rx="2" />
                        <circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none" />
                        <path d="M4 17l4.5-4.5 3 3L15 12l5 5" />
                    </svg>
                </div>
            @endif
        </div>
        <div class="px-4 pt-4">
            {{-- h2, nie h3: na wykazie nazwy produktów wiszą bezpośrednio pod h1
                 („Produkty") — nie ma nad nimi h2, więc h3 skakałby o poziom.
                 Stopień pisma niosą klasy, nie poziom nagłówka.

                 Typografia identyczna jak w kafelku strony głównej: cały storefront
                 mówi szeryfem w kolorze marki (h1 karty produktu, „O produkcie",
                 „Podobne produkty") — wykaz był jedynym miejscem z bezszeryfowym,
                 półgrubym tytułem i wypadał z rytmu.

                 Limit DWÓCH linii, nie jednej: na wykazie nazwa jest jedyną rzeczą,
                 która rozróżnia produkty, a przy jednej linii (~22 znaki na 3 kolumnach,
                 ~15 na 4) ucięcie zjadałoby końcówki — „Shima OpenAir Brown" i „…Black"
                 stałyby się nieodróżnialne. Dwie linie mieszczą ~44/~30 znaków, czyli
                 realne nazwy w całości, a mimo to nic nie ucieka w trzecią linię. --}}
            <h2 class="st-brand font-serif text-2xl font-normal line-clamp-2 sm:text-3xl">{{ $product->name }}</h2>
            {{-- Cena w kolorze tekstu, NIE w kolorze marki — jak na karcie produktu
                 (tam też `font-bold` bez `st-brand`). Odkąd tytuł niesie kolor marki,
                 cena w tym samym kolorze robiła z kafla dwa akcenty jeden pod drugim.
                 Akcentem zostaje przycisk koszyka. --}}
            <p class="mt-1 text-lg font-bold">{{ \App\Support\Money::pln($product->price_gross) }}@if ($product->sale_unit->isWeight())<span class="text-sm font-medium opacity-60"> / {{ $product->sale_unit->abbreviation() }}</span>@endif</p>
        </div>
    </a>

    {{-- Stopka z akcją — poza anchorem (przycisk nie może żyć w <a>), do prawej. --}}
    @if ($canOrder)
        <div class="mt-auto flex justify-end p-4 pt-3">
            <livewire:add-to-cart :product="$product" :compact="true" :with-options="false" wire:key="atc-{{ $product->id }}" />
        </div>
    @endif
</div>
