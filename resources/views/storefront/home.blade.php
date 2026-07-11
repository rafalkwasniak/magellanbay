<x-layouts.storefront :shop="$shop">
    {{-- Style lokalne WYŁĄCZNIE dla boxu 1 produktu na tej stronie. Trzymamy je
         tutaj, nie w layoucie — layout jest współdzielony przez cały storefront.
         Apla wjeżdża na CAŁE zdjęcie na hover (zdjęcie przygasa + powiększa się);
         na dotyku (brak hovera) apla staje się statycznym panelem pod zdjęciem —
         dane zawsze widoczne, zero JS. --}}
    <style>
        /* Box huggający zdjęcie: inline-block wyśrodkowany, więc apla trzyma się
           obrazu (a nie całej kolumny). Zdjęcie ograniczone I szerokością (poziome
           = pełna szerokość treści) I wysokością (pionowe = max-height związane z
           ekranem, nigdy nie przekroczy widoku). Bez przycinania. */
        .solo { display: inline-block; max-width: 100%; }
        .solo-media { display: block; max-width: 100%; max-height: 75vh; width: auto; height: auto; transition: transform .6s cubic-bezier(.22,1,.36,1), filter .5s ease; }
        .solo:hover .solo-media,
        .solo:focus-within .solo-media { transform: scale(1.06); filter: brightness(.42); }
        /* Apla u DOŁU zdjęcia, wysoka tylko na tyle, ile trzeba na tekst.
           Wyjeżdża z dołu (translateY) na hover. */
        .solo-apla {
            position: absolute; left: 0; right: 0; bottom: 0;
            background: color-mix(in srgb, var(--surface) 55%, transparent);
            backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
            transform: translateY(100%);
            transition: transform .5s cubic-bezier(.22,1,.36,1);
        }
        .solo:hover .solo-apla,
        .solo:focus-within .solo-apla { transform: translateY(0); }
        @media (hover: none) {
            .solo-media { filter: none !important; transform: none !important; }
            .solo-apla { position: static; transform: none; background: transparent; backdrop-filter: none; -webkit-backdrop-filter: none; padding-top: 1.5rem; padding-bottom: 0; }
        }
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

            {{-- 1 produkt → BOX z aplą. Zdjęcie w naturalnych proporcjach (bez
                 przycinania). Na hover CAŁE zdjęcie przygasa + powiększa się, a
                 na nie wjeżdża półprzezroczysta apla: nazwa produktu · część
                 opisu · przycisk „Pokaż produkt". Strona główna NIE sprzedaje
                 (bez ceny/koszyka) — ma zachęcić do wejścia w produkt. Nazwa
                 produktu celowo NIE nad zdjęciem — tam winieta niesie nazwę
                 SKLEPU. Style i zachowanie dotykowe: <style> u góry pliku. --}}
            @case(1)
                @php($p = $products->first())
                @php($main = $p->mainImage())
                @php($excerpt = filled($p->description) ? \Illuminate\Support\Str::of(strip_tags($p->description))->squish()->limit(180, preserveWords: true) : null)
                <section class="text-center">
                    <div class="solo group relative overflow-hidden rounded-3xl shadow-sm">
                        @if ($main)
                            <img src="{{ $main->url() }}" alt="{{ $p->name }}" class="solo-media">
                        @else
                            <div class="st-card st-border flex items-center justify-center border" style="width: 26rem; height: 26rem; max-width: 100%;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-16 w-16 opacity-40" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none"/><path d="M4 17l4.5-4.5 3 3L15 12l5 5"/></svg>
                            </div>
                        @endif

                        {{-- Apla — całe zdjęcie. Poza anchorem, bo przycisk Livewire
                             nie może żyć w <a>; nazwa jest linkiem do produktu. --}}
                        <div class="solo-apla flex flex-col items-start gap-3 p-6 text-left">
                            <h2 class="font-serif text-3xl font-normal tracking-tight">{{ $p->name }}</h2>
                            @if ($excerpt)
                                <p class="max-w-md leading-relaxed opacity-80">{{ $excerpt }}</p>
                            @endif
                            <a href="{{ $p->storefrontPath() }}" wire:navigate class="st-btn mt-1 inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">Pokaż produkt</a>
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

        @if ($totalProducts > $products->count())
            <div class="mt-12 text-center">
                <a href="/produkty" class="st-btn inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">
                    Zobacz wszystkie produkty
                </a>
            </div>
        @endif

        {{-- O sklepie — głos sklepu POD ofertą (nie na górze, gdzie psuł odbiór).
             Długi opis (≥ próg) → wycinek + „czytaj więcej" na wirtualną podstronę;
             krótki → cała treść tutaj, z zachowaniem formatowania. --}}
        @if ($shop->hasAbout())
            <section class="mt-16">
                {{-- „O sklepie" w wizualnym kaflu (st-card), tekst do lewej. --}}
                <div class="st-card st-border rounded-3xl border p-8 text-left">
                    <h2 class="st-brand font-serif text-2xl font-normal tracking-tight sm:text-3xl">O sklepie</h2>
                    @if ($shop->aboutInMenu())
                        <p class="mt-5 leading-relaxed opacity-80">{{ \Illuminate\Support\Str::of($shop->aboutPlainText())->limit(280, preserveWords: true) }}</p>
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
