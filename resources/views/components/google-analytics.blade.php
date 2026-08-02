@props(['id' => null])

{{-- Pomiar Google: wstrzykiwany tylko wtedy, gdy jest co wstrzykiwać.

     Jeden komponent dla CENTRALI (identyfikator z `.env`) i dla SKLEPÓW
     (identyfikator sprzedawcy z Integracji) — inaczej ten sam kod żyłby w dwóch
     layoutach i rozjechałby się przy pierwszej zmianie.

     `GTM-` → Tag Manager, `G-` → Analytics 4. Rozróżnienie jest potrzebne, bo
     to dwa różne skrypty pod jednym pojęciem „statystyki Google"; sprzedawca
     wkleja to, co dostał, i nie musi wiedzieć, które ma.

     Identyfikator jest walidowany do [A-Z0-9-] przy zapisie (Form Request), a
     tutaj i tak przechodzi przez `e()`, więc nie da się nim wstrzyknąć skryptu. --}}

{{-- BRAMKA ZGODY. Bez zgody skrypt NIE POJAWIA SIĘ w wysłanym HTML-u — nie jest
     ukrywany ani wyłączany po stronie przeglądarki, bo wtedy plik zostałby już
     pobrany, a Google zobaczyłoby żądanie. Zgoda musi być UPRZEDNIA, więc
     jedyne poprawne miejsce na tę decyzję jest tutaj, przed renderem. --}}
@if ($id && \App\Support\CookieConsent::granted())
    @php($isGtm = str_starts_with($id, 'GTM-'))

    @if ($isGtm)
        {{-- Google Tag Manager --}}
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $id }}');</script>
    @else
        {{-- Google Analytics 4 --}}
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $id }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $id }}');
        </script>
    @endif
@endif
