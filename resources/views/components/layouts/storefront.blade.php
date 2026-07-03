@props(['shop', 'title' => null])

@php
    $tokens = $shop->themeTokens();
    // Google Analytics/Tag Manager: wstrzykujemy tylko gdy włączone (Ustawienia)
    // i skonfigurowane (Integracje). ID jest zwalidowane do [A-Z0-9-] (Form
    // Request), więc bezpieczne w <script>. GTM- → Tag Manager, G- → GA4.
    $gaId = $shop->tracksWithGoogleAnalytics() ? $shop->googleAnalyticsId() : null;
    $isGtm = $gaId !== null && str_starts_with($gaId, 'GTM-');
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

    {{-- Slim pasek użytkowy: powrót do sklepu + koszyk (na każdej podstronie). --}}
    <header class="st-border sticky top-0 z-30 border-b backdrop-blur" style="background: color-mix(in srgb, var(--surface) 82%, transparent);">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
            <a href="/" wire:navigate class="st-brand truncate text-sm font-semibold sm:text-base">{{ $shop->name }}</a>
            <livewire:cart-counter :shop-id="$shop->id" />
        </div>
    </header>

    {{ $slot }}

    <footer class="st-border mt-16 border-t py-8 text-center text-xs opacity-60">
        Sklep na <a href="https://{{ config('tenancy.central_domain') }}" class="font-semibold">Kramio</a>
    </footer>

    @livewireScripts
</body>

</html>
