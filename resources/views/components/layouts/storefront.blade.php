@props(['shop', 'title' => null])

@php
    $tokens = $shop->themeTokens();
    // Google Analytics/Tag Manager: wstrzykujemy tylko gdy włączone (Ustawienia)
    // i skonfigurowane (Integracje). ID jest zwalidowane do [A-Z0-9-] (Form
    // Request), więc bezpieczne w <script>. GTM- → Tag Manager, G- → GA4.
    $gaId = $shop->tracksWithGoogleAnalytics() ? $shop->googleAnalyticsId() : null;
    $isGtm = $gaId !== null && str_starts_with($gaId, 'GTM-');

    // Nawigacja: jedno źródło pozycji „Informacje" (wirtualna „O sklepie" + strony)
    // dla nagłówka i stopki. Logo (jeśli jest) i adres NASZEJ Polityki prywatności
    // (na centrali, nie na subdomenie sklepu).
    $infoMenu = $shop->informationMenu();
    $logoUrl = $shop->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($shop->logo_path) : null;
@endphp
<!doctype html>
<html lang="pl" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · '.$shop->name : $shop->name }}</title>
    @vite('resources/css/app.css')

    {{-- Motyw sklepu: tokeny palety → zmienne CSS. To jedyne miejsce, z którego
         storefront bierze kolory (bez kompilacji CSS per-sklep). Kolory z configu
         (zaufane), nie z inputu użytkownika. --}}
    <style>
        :root {
            --brand: {{ $tokens['brand'] }};
            --brand-ink: {{ $tokens['brand_ink'] }};
            --surface: {{ $tokens['surface'] }};
            --ink: {{ $tokens['ink'] }};
        }

        body { background: var(--surface); color: var(--ink); }
        .st-brand { color: var(--brand); }
        .st-btn { background: var(--brand); color: var(--brand-ink); }
        .st-border { border-color: color-mix(in srgb, var(--ink) 12%, transparent); }
        /* Panel karty — wyliczany z tokenów, czytelny na jasnym i ciemnym tle. */
        .st-card { background: color-mix(in srgb, var(--ink) 4%, var(--surface)); }
        /* Menu (rozwijane „Informacje" + mobilne): tło z tokenu, hover z --ink. */
        .st-menu { background: var(--surface); }
        .st-menu-item:hover { background: color-mix(in srgb, var(--ink) 7%, transparent); }

        /* Typografia treści stron „Informacje" (HTML z bazy). Bliźniak
           .legal-content, ale kolor DZIEDZICZY z motywu (--ink) — czytelny na
           jasnym i ciemnym szablonie; akcenty (marker listy, link) na --brand. */
        .st-prose { line-height: 1.75; }
        .st-prose h2 { margin-top: 2rem; margin-bottom: 0.75rem; font-size: 1.25rem; font-weight: 600; letter-spacing: -0.01em; }
        .st-prose h3 { margin-top: 1.5rem; margin-bottom: 0.5rem; font-size: 1.05rem; font-weight: 600; }
        .st-prose :is(h2, h3):first-child { margin-top: 0; }
        .st-prose p { margin-bottom: 1rem; }
        .st-prose ul, .st-prose ol { margin-bottom: 1rem; padding-left: 1.5rem; }
        .st-prose ul { list-style: disc; }
        .st-prose ol { list-style: decimal; }
        .st-prose li { margin-bottom: 0.4rem; }
        .st-prose li::marker { color: var(--brand); }
        .st-prose strong { font-weight: 600; }
        .st-prose a { color: var(--brand); text-decoration: underline; text-underline-offset: 2px; }
        .st-prose a:hover { opacity: 0.8; }
        [x-cloak] { display: none !important; }
    </style>

    @livewireStyles

    @if($gaId)
        @if($isGtm)
            {{-- Google Tag Manager --}}
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $gaId }}');</script>
        @else
            {{-- Google Analytics 4 --}}
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '{{ $gaId }}');
            </script>
        @endif
    @endif
</head>

<body class="min-h-full font-sans antialiased">
    @if($gaId && $isGtm)
        {{-- Google Tag Manager (noscript) --}}
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gaId }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    {{-- Nagłówek globalny — WINIETA. Mobile: kompaktowy pasek (hamburger · brand ·
         koszyk). Desktop: brand wyśrodkowany i duży (logo/serif), cienka linia,
         nav pod spodem. Rozwijane menu i menu mobilne na natywnym <details> —
         storefront jest JS-light (bez app.js), więc zero zależności. --}}
    <header class="st-border sticky top-0 z-30 border-b backdrop-blur" style="background: color-mix(in srgb, var(--ink) 8%, color-mix(in srgb, var(--surface) 92%, transparent));">
        {{-- MOBILE: kompaktowy pasek --}}
        <div class="relative md:hidden">
            <div class="flex items-center justify-between px-4 py-3">
                <details>
                    <summary class="flex cursor-pointer select-none list-none items-center [&::-webkit-details-marker]:hidden" aria-label="Menu">
                        <svg class="h-6 w-6 opacity-80" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </summary>
                    <div class="st-menu absolute inset-x-0 top-full z-40 border-t st-border">
                        <nav class="flex flex-col px-4 py-2 text-sm font-medium">
                            <a href="/produkty" wire:navigate class="st-menu-item st-brand -mx-4 px-4 py-3">Produkty</a>
                            @foreach ($infoMenu as $item)
                                <a href="{{ $item['url'] }}" wire:navigate class="st-menu-item st-brand -mx-4 px-4 py-3">{{ $item['label'] }}</a>
                            @endforeach
                        </nav>
                    </div>
                </details>

                <a href="/" wire:navigate class="absolute left-1/2 flex max-w-[60%] -translate-x-1/2 items-center justify-center">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $shop->name }}" class="h-11 w-auto max-w-full object-contain">
                    @else
                        <span class="st-brand truncate font-serif text-2xl leading-none">{{ $shop->name }}</span>
                    @endif
                </a>

                <livewire:cart-counter :shop-id="$shop->id" />
            </div>
        </div>

        {{-- DESKTOP: winieta wyśrodkowana --}}
        <div class="relative mx-auto hidden max-w-6xl flex-col items-center gap-3 px-6 py-5 md:flex">
            {{-- Koszyk w prawym rogu --}}
            <div class="absolute right-6 top-5">
                <livewire:cart-counter :shop-id="$shop->id" />
            </div>

            {{-- Brand (duży, wyśrodkowany) --}}
            <a href="/" wire:navigate class="flex max-w-full items-center justify-center">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $shop->name }}" class="h-28 w-auto max-w-[18rem] object-contain">
                @else
                    <span class="st-brand text-center font-serif text-4xl leading-none tracking-tight">{{ $shop->name }}</span>
                @endif
            </a>

            {{-- Cienka linia + nawigacja --}}
            <span class="h-px w-14" style="background: color-mix(in srgb, var(--ink) 18%, transparent);"></span>
            <nav class="flex items-center gap-8 text-base font-medium">
                <a href="/produkty" wire:navigate class="st-brand transition hover:brightness-95">Produkty</a>
                @if (count($infoMenu))
                    <details class="group relative">
                        <summary class="st-brand flex cursor-pointer select-none list-none items-center gap-1 transition hover:brightness-95 [&::-webkit-details-marker]:hidden">
                            Informacje
                            <span class="text-[0.6rem] transition group-open:rotate-180">▾</span>
                        </summary>
                        <div class="st-menu st-border absolute left-1/2 z-40 mt-3 min-w-52 -translate-x-1/2 overflow-hidden rounded-2xl border shadow-xl">
                            @foreach ($infoMenu as $item)
                                <a href="{{ $item['url'] }}" wire:navigate class="st-menu-item block px-4 py-2.5 text-sm opacity-80 transition hover:opacity-100">{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </details>
                @endif
            </nav>
        </div>
    </header>

    {{ $slot }}

    {{-- Stopka globalna: brand · Informacje (te same pozycje co w menu + NASZA
         Polityka prywatności) · Kontakt. Pasek „Sklep na Kramio" na dole.
         Lekki tint z --ink, żeby stopka nie zlewała się ze stroną (jak nagłówek). --}}
    <footer class="st-border mt-16 border-t" style="background: color-mix(in srgb, var(--ink) 5%, var(--surface));">
        <div class="mx-auto grid max-w-6xl gap-8 px-6 py-12 sm:grid-cols-2 md:grid-cols-3">
            {{-- Brand + dane firmowe (jeśli sprzedawca uzupełnił) --}}
            <div>
                <a href="/" wire:navigate class="inline-flex items-center gap-2">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $shop->name }}" class="h-9 w-auto max-w-[10rem] object-contain">
                    @else
                        <span class="st-brand font-serif text-xl">{{ $shop->name }}</span>
                    @endif
                </a>
                @if (filled($shop->company_name) || filled($shop->street))
                    <address class="mt-4 text-sm not-italic leading-relaxed opacity-70">
                        @if (filled($shop->company_name))<div>{{ $shop->company_name }}</div>@endif
                        @if (filled($shop->street))
                            <div>{{ $shop->street }} {{ $shop->building_number }}@if (filled($shop->apartment_number))/{{ $shop->apartment_number }}@endif</div>
                        @endif
                        @if (filled($shop->postal_code) || filled($shop->city))
                            <div>{{ $shop->postal_code }} {{ $shop->city }}</div>
                        @endif
                    </address>
                @endif
            </div>

            {{-- Informacje --}}
            <div>
                <h3 class="text-sm font-semibold">Informacje</h3>
                <ul class="mt-3 space-y-2 text-sm opacity-80">
                    @foreach ($infoMenu as $item)
                        <li><a href="{{ $item['url'] }}" wire:navigate class="transition hover:opacity-100">{{ $item['label'] }}</a></li>
                    @endforeach
                    <li><a href="/polityka-prywatnosci" wire:navigate class="transition hover:opacity-100">Polityka prywatności</a></li>
                </ul>
            </div>

            {{-- Kontakt --}}
            @if (filled($shop->contact_email) || filled($shop->contact_phone))
                <div>
                    <h3 class="text-sm font-semibold">Kontakt</h3>
                    <ul class="mt-3 space-y-2 text-sm opacity-80">
                        @if (filled($shop->contact_email))
                            <li><a href="mailto:{{ $shop->contact_email }}" class="transition hover:opacity-100">{{ $shop->contact_email }}</a></li>
                        @endif
                        @if (filled($shop->contact_phone))
                            <li><a href="tel:{{ $shop->contact_phone }}" class="transition hover:opacity-100">{{ $shop->formattedContactPhone() }}</a></li>
                        @endif
                    </ul>
                </div>
            @endif
        </div>

        <div class="st-border border-t py-6 text-center text-xs opacity-60">
            Sklep zbudowany na <a href="https://{{ config('tenancy.central_domain') }}" class="font-semibold">Kramio</a>
        </div>
    </footer>

    @livewireScripts
</body>

</html>
