@props(['id' => null])

{{-- Awaryjna ramka Tag Managera dla przeglądarek bez JavaScriptu. Musi stać na
     początku <body>, dlatego jest osobno od głównego komponentu, który idzie do
     <head>. Dotyczy wyłącznie GTM — Analytics 4 nie ma wariantu bez skryptu. --}}

@if ($id && str_starts_with($id, 'GTM-') && \App\Support\CookieConsent::granted())
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $id }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif
