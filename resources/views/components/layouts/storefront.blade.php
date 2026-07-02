@props(['shop', 'title' => null])

@php $tokens = $shop->themeTokens(); @endphp
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
    </style>
</head>

<body class="min-h-full font-sans antialiased">
    {{ $slot }}

    <footer class="st-border mt-16 border-t py-8 text-center text-xs opacity-60">
        Sklep na <a href="https://{{ config('tenancy.central_domain') }}" class="font-semibold">Kramio</a>
    </footer>
</body>

</html>
