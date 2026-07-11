<x-layouts.storefront :shop="$shop">
    {{-- Style lokalne WYŁĄCZNIE dla boxu 1 produktu na tej stronie. Trzymamy je
         tutaj, nie w layoucie — layout jest współdzielony przez cały storefront. --}}
    <style>
        /* WARIANT BEZ apli (do porównania): kafelek huggujący zdjęcie
           (inline-block, wyśrodkowany), a informacje POD zdjęciem w stylu
           strony produktowej. Zdjęcie ograniczone I szerokością (poziome =
           pełna szerokość kafla) I wysokością (pionowe = max-height związane
           z ekranem). Bez przycinania. */
        .solo { display: inline-block; max-width: 100%; }
        .solo-media { display: block; max-width: 100%; max-height: 68vh; width: auto; height: auto; transition: transform .3s ease; }
        /* Zoom na najechanie — jak na kaflach wykazu (scale 1.05); kafel ma
           overflow-hidden, więc powiększenie jest przycięte do ramki. */
        .solo:hover .solo-media { transform: scale(1.05); }
        /* Panel z info nie rozpycha kafla: width:0 + min-width:100% sprawia, że
           o szerokości boxu decyduje ZDJĘCIE, a tekst zawija się do jego
           szerokości (bez tego długa linia opisu robiłaby box szerszy). */
        .solo-info { width: 0; min-width: 100%; box-sizing: border-box; }
    </style>

    {{-- Hero sklepu — editorial „okładka". Winieta u góry niesie logo/nazwę jako
         STAŁĄ nawigację, więc tu świadomie NIE powtarzamy logo — robimy jeden
         oddechowy gest: nazwa w serif display, duża skala, dużo powietrza.
         Opis sklepu NIE tutaj — jego miejsce to sekcja „O sklepie" na dole. --}}
    <header class="mx-auto max-w-3xl px-6 pt-20 text-center sm:pt-28">
        <h1 class="st-brand font-serif text-5xl font-normal leading-tight tracking-tight sm:text-6xl lg:text-7xl">{{ $shop->name }}</h1>
    </header>

    <main class="mx-auto max-w-6xl px-6 pt-12">
        @switch($products->count())

            {{-- 1 produkt → KAFELEK bez apli (wariant do porównania z wersją
                 z aplą, commit 3408b87). Zdjęcie w naturalnych proporcjach na
                 górze, a POD nim info w stylu strony produktowej: nazwa · część
                 opisu · przycisk „Pokaż produkt". Strona główna NIE sprzedaje
                 (bez ceny/koszyka) — ma zachęcić do wejścia w produkt. --}}
            @case(1)
                @php($p = $products->first())
                @php($main = $p->mainImage())
                @php($excerpt = filled($p->description) ? \Illuminate\Support\Str::of(strip_tags($p->description))->squish()->limit(180, preserveWords: true) : null)
                <section class="text-center">
                    <div class="solo st-card st-border overflow-hidden rounded-3xl border text-left">
                        <a href="{{ $p->storefrontPath() }}" wire:navigate class="block overflow-hidden">
                            @if ($main)
                                <img src="{{ $main->url() }}" alt="{{ $p->name }}" class="solo-media">
                            @else
                                <div class="st-btn flex items-center justify-center" style="width: 26rem; height: 26rem; max-width: 100%;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-16 w-16 opacity-70" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none"/><path d="M4 17l4.5-4.5 3 3L15 12l5 5"/></svg>
                                </div>
                            @endif
                        </a>

                        {{-- Info pod zdjęciem --}}
                        <div class="solo-info p-6">
                            <h2 class="st-brand font-serif text-2xl font-normal tracking-tight sm:text-3xl">
                                <a href="{{ $p->storefrontPath() }}" wire:navigate class="transition hover:opacity-70">{{ $p->name }}</a>
                            </h2>
                            @if ($excerpt)
                                <p class="mt-3 leading-relaxed opacity-80">{{ $excerpt }}</p>
                            @endif
                            <a href="{{ $p->storefrontPath() }}" wire:navigate class="st-btn mt-5 inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">Pokaż produkt</a>
                        </div>
                    </div>
                </section>
                @break

            {{-- 2 produkty → para 50/50: dwa duże panele. --}}
            @case(2)
                <section class="grid gap-6 sm:grid-cols-2">
                    @foreach ($products as $p)
                        <x-storefront.product-card :product="$p" aspect="4 / 5" />
                    @endforeach
                </section>
                @break

            {{-- 3 produkty → tryptyk: jeden szeroki na górze + dwa pod spodem. --}}
            @case(3)
                <section class="space-y-6">
                    <x-storefront.product-card :product="$products[0]" aspect="16 / 9" />
                    <div class="grid gap-6 sm:grid-cols-2">
                        <x-storefront.product-card :product="$products[1]" />
                        <x-storefront.product-card :product="$products[2]" />
                    </div>
                </section>
                @break

            @case(0)
                {{-- Aktywny sklep ma ≥1 produkt; ten przypadek to bezpiecznik. --}}
                @break

            {{-- 4–6 → wspólna witryna (siatka). --}}
            @default
                <section class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($products as $p)
                        <x-storefront.product-card :product="$p" />
                    @endforeach
                </section>
        @endswitch

        {{-- O sklepie — głos sklepu POD ofertą (nie na górze, gdzie psuł odbiór).
             Długi opis (≥ próg) → wycinek + „czytaj więcej" na wirtualną podstronę;
             krótki → cała treść tutaj, z zachowaniem formatowania. --}}
        @if ($shop->hasAbout())
            <section class="mt-16">
                {{-- „O sklepie" w wizualnym kaflu (st-card), tekst do lewej. --}}
                <div class="st-card st-border rounded-3xl border p-8 text-left">
                    <h2 class="st-brand font-serif text-2xl font-normal tracking-tight sm:text-3xl">O sklepie</h2>
                    @if ($shop->aboutInMenu())
                        <p class="mt-5 leading-relaxed opacity-80">{{ \Illuminate\Support\Str::of($shop->aboutPlainText())->limit((int) config('pages.about.menu_threshold'), preserveWords: true) }}</p>
                        <a href="{{ $shop->aboutPath() }}" wire:navigate class="st-brand mt-5 inline-block text-sm font-medium underline-offset-4 hover:underline">Czytaj więcej →</a>
                    @else
                        <div class="st-prose mt-5 opacity-90">{!! $shop->description !!}</div>
                    @endif
                </div>
            </section>
        @endif

        @if (count($tagCloud))
            <section class="mt-16">
                {{-- Tagi w takim samym wizualnym kaflu jak „O sklepie", do lewej,
                     z nagłówkiem „Zobacz produkty". Chmura bez własnej etykiety
                     (label=""), bo nagłówek już jest opisem. --}}
                <div class="st-card st-border rounded-3xl border p-8 text-left">
                    <h2 class="st-brand font-serif text-2xl font-normal tracking-tight sm:text-3xl">Zobacz produkty</h2>
                    <x-storefront.tag-cloud :tags="$tagCloud" label="" />
                </div>
            </section>
        @endif
    </main>
</x-layouts.storefront>
