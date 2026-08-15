{{-- Link do mechanizmu zgłaszania treści bezprawnych (art. 16 DSA).

     Musi być „łatwo dostępny", więc stoi w stopce KAŻDEGO storefrontu i stron
     centrali — nie w regulaminie, do którego nikt nie zagląda.

     `Central::url()`, a NIE `route()`: renderowany na subdomenie sklepu `route()`
     zbudowałby adres tej subdomeny, czyli sklepu, którego zgłoszenie dotyczy.
     Formularz stoi na centrali, bo obowiązek jest nasz, nie sprzedawcy.

     Bieżący adres doklejamy jako `?adres=` — zgłaszający ma nie przepisywać go
     z paska przeglądarki, a my dostajemy dokładne miejsce, nie samą nazwę sklepu. --}}
<a href="{{ \App\Support\Central::url('/zglos-tresc').'?adres='.urlencode(url()->current()) }}"
    {{ $attributes->merge(['class' => 'underline-offset-2 transition hover:underline']) }}>
    Zgłoś nielegalną treść
</a>
