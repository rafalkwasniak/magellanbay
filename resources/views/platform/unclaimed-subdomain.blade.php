{{-- Strona subdomeny, pod którą nie stoi żaden sklep. Dwa warianty tej samej
     karty: adres WOLNY (zachęta z konkretnym adresem) i adres zajęty
     (zarezerwowany, w kwarantannie po usuniętym sklepie albo o niepoprawnym
     kształcie) — wtedy zapraszamy do Kramio, ale bez obiecywania tego adresu.

     Wszystkie linki budujemy przez Central::url: strona żyje na SUBDOMENIE,
     więc route() i url() wskazałyby storefront, a tam `/rejestracja` zakłada
     konto klienta sklepu, nie sklep. --}}
@php($freeProducts = config('shop.packages.'.config('shop.default_package').'.entitlements.max_products'))

<x-layouts.guest :title="$free ? 'Ten adres jest wolny' : 'Nie ma tu sklepu'">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl">

        {{-- Sam adres jest tu bohaterem — po to ktoś tu trafił. `break-words`, bo
             etykieta subdomeny może mieć do 63 znaków bez spacji. --}}
        <p class="text-center text-lg font-semibold break-words text-stone-900">{{ $host }}</p>

        @if ($free)
            <p class="mt-1 text-center text-sm font-medium text-emerald-700">Ten adres jest wolny</p>

            <h1 class="mt-6 text-3xl font-semibold tracking-tight text-stone-900">
                Możesz mieć tu swój sklep
            </h1>
            <p class="mt-3 text-stone-600">
                Pod tym adresem nie ma jeszcze nikogo. Załóż tu sklep w Kramio, a klienci
                sami wejdą, wybiorą i złożą zamówienie — nawet w środku nocy, kiedy Ty
                już śpisz.
            </p>

            <ul class="mt-6 space-y-2 text-sm text-stone-700">
                <li class="flex gap-2">
                    <span aria-hidden="true" class="text-amber-600">✓</span>
                    <span>Prawdziwy sklep: produkty ze zdjęciami, koszyk, zamówienia w panelu</span>
                </li>
                <li class="flex gap-2">
                    <span aria-hidden="true" class="text-amber-600">✓</span>
                    <span>Do {{ $freeProducts }} produktów w darmowym pakiecie</span>
                </li>
                <li class="flex gap-2">
                    <span aria-hidden="true" class="text-amber-600">✓</span>
                    <span>Bez karty i bez okresu próbnego — darmowy znaczy darmowy</span>
                </li>
                <li class="flex gap-2">
                    <span aria-hidden="true" class="text-amber-600">✓</span>
                    <span>Uruchomienie zajmuje około 15 minut</span>
                </li>
            </ul>

            {{-- Adres jedzie do formularza w parametrze: rejestracja odtworzy
                 z niego nazwę sklepu, więc pod polem od razu widać ten sam
                 adres, po który ktoś tu przyszedł. --}}
            <a href="{{ \App\Support\Central::url('/rejestracja?adres='.$slug) }}"
                class="mt-8 block w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-3.5 text-center text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                Zajmij ten adres za darmo
            </a>
        @else
            <p class="mt-1 text-center text-sm font-medium text-stone-500">Adres zajęty</p>

            <h1 class="mt-6 text-3xl font-semibold tracking-tight text-stone-900">
                Nie ma tu sklepu
            </h1>
            <p class="mt-3 text-stone-600">
                Tego adresu nie da się w tej chwili zająć — jest zarezerwowany albo
                zajmował go sklep, który zniknął. Ale swój sklep w Kramio założysz
                za darmo, pod własnym adresem.
            </p>

            <ul class="mt-6 space-y-2 text-sm text-stone-700">
                <li class="flex gap-2">
                    <span aria-hidden="true" class="text-amber-600">✓</span>
                    <span>Prawdziwy sklep: produkty ze zdjęciami, koszyk, zamówienia w panelu</span>
                </li>
                <li class="flex gap-2">
                    <span aria-hidden="true" class="text-amber-600">✓</span>
                    <span>Do {{ $freeProducts }} produktów w darmowym pakiecie, bez karty</span>
                </li>
            </ul>

            <a href="{{ \App\Support\Central::url('/rejestracja') }}"
                class="mt-8 block w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-3.5 text-center text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                Załóż sklep za darmo
            </a>
        @endif

        <p class="mt-4 text-center text-sm text-stone-500">
            <a href="{{ \App\Support\Central::url('/') }}" class="font-semibold text-amber-700 hover:text-amber-800">
                Zobacz, czym jest Kramio
            </a>
        </p>
    </div>
</x-layouts.guest>
