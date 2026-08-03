@props(['code' => null])

{{-- Potwierdzenie własności strony w Google Search Console. Jedno miejsce
     wstrzykiwania dla centrali i storefrontów — dokładnie jak przy
     `x-google-analytics`, żeby nie rozsiewać tego samego meta tagu po layoutach.

     Nic nie śledzi i nie stawia ciasteczek, więc NIE podlega zgodzie na
     ciasteczka ani włącznikowi w Ustawieniach: albo kod jest, albo go nie ma. --}}
@if (filled($code))
    <meta name="google-site-verification" content="{{ $code }}">
@endif
