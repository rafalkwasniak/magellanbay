@props([
    // Klucz uprawnienia (np. `bulk_mail`) — z niego wyliczamy NAJTAŃSZY pakiet,
    // który tę funkcję daje. Nazwy pakietów nie wpisujemy w treść.
    'feature',
    'icon' => '🔒',
    'title',
    // Sklep, żeby powiedzieć wprost, na czym sprzedawca dziś siedzi.
    'shop' => null,
])

{{-- Jedna zachęta dla WSZYSTKICH zablokowanych ekranów panelu (kody, wiadomości,
     integracje). Wcześniej każdy ekran miał własną kopię: te same klasy, inne
     zdania i — co gorsza — żaden nie prowadził do zakupu, bo pisane były, gdy
     płatności jeszcze nie było. Teraz zakup działa, więc zachęta musi mieć gdzie
     wysłać; inaczej mówi „nie masz" i zostawia sprzedawcę bez wyjścia.

     Zdania składane w PHP, nie dyrektywami w środku tekstu: `@if` przyklejone do
     `}}` albo do `</span>` NIE jest kompilowane jako dyrektywa, a jego `@endif`
     zamyka wtedy nadrzędny blok i cały widok pada na „unexpected end of file". --}}
@php($cheapest = \App\Support\PackageFeatures::cheapestWith($feature))
@php($headline = $cheapest !== null ? $title.' w pakiecie '.$cheapest['name'] : $title)
@php($priceNote = ($cheapest !== null && $cheapest['price_yearly'] > 0)
    ? ', a '.$cheapest['name'].' to '.number_format($cheapest['price_yearly'], 0, ',', ' ').' zł za rok'
    : '')

<div class="rounded-2xl border border-dashed border-stone-300 bg-white/40 p-8 text-center">
    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-100 text-2xl">{{ $icon }}</span>

    <p class="mt-4 font-medium text-stone-700">{{ $headline }}</p>

    <p class="mx-auto mt-1 max-w-sm text-sm text-stone-500">{{ $slot }}</p>

    @if ($shop !== null)
        {{-- Nazwa EFEKTYWNA: po wygaśnięciu sklep działa na zasadach Kramu i tak ma
             być nazwany, inaczej sprzedawca czytałby, że ma pakiet, którego funkcji
             właśnie mu odmawiamy. --}}
        <p class="mt-3 text-xs text-stone-400">
            Twój obecny pakiet: <span class="font-medium text-stone-600">{{ $shop->effectivePackageName() }}</span>{{ $priceNote }}.
        </p>
    @endif

    <a href="{{ route('seller.package.show') }}"
        class="mt-5 inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
        {{ $cheapest !== null ? 'Zobacz pakiet '.$cheapest['name'] : 'Zobacz pakiety' }}
    </a>
</div>
