<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($metaTitle = ($title ?? '') . (isset($title) ? ' · ' : '') . config('app.name'))

    <title>{{ $metaTitle }}</title>
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/kramio-icon.png') }}">

    {{-- Open Graph dla stron platformy (regulamin, polityka, logowanie).
         Grafika wspólna dla całej centrali — config/seo.php. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="pl_PL">
    <meta property="og:image" content="{{ \App\Support\Seo::platformImage() }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">

    <x-google-verification :code="config('services.google.site_verification')" />
    <x-google-analytics :id="config('services.google.analytics_id')" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-stone-100 text-stone-800 antialiased">
    <x-toasts />

    <div class="relative min-h-full overflow-hidden">
        {{-- miękkie kształty marki --}}
        <div class="pointer-events-none absolute -left-32 -top-20 h-96 w-96 rounded-full bg-amber-300 opacity-30 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -right-20 h-[28rem] w-[28rem] rounded-full bg-rose-300 opacity-30 blur-3xl"></div>

        <div class="relative mx-auto w-full max-w-6xl px-6 py-12">
            <a href="{{ url('/') }}" class="mb-8 inline-flex items-center">
                <img src="{{ asset('images/kramio-logo.png') }}" alt="{{ config('app.name') }} — twój sklep w 15 minut" class="h-12 w-auto">
            </a>

            {{ $slot }}

            {{-- Zmiana decyzji o ciasteczkach — te layouty nie mają stopki,
                 więc link stoi dyskretnie pod treścią. --}}
            <p class="mt-10 text-center text-xs text-stone-400">
                <x-cookie-settings-link class="inline" />
            </p>

        </div>
    </div>
    <x-cookie-consent owner="Kramio" privacy-url="/polityka-prywatnosci">logowanie i Twój panel działały poprawnie</x-cookie-consent>
</body>
</html>
