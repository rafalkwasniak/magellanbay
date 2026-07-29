<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — Twój sklep internetowy w 5 minut</title>
    <meta name="description" content="{{ config('app.name') }} to platforma, na której uruchomisz własny sklep internetowy w kilka minut — bez wiedzy technicznej. Własny adres, gotowa strona, płatności i dostawy.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-stone-100 text-stone-800 antialiased">
    @php($domain = config('tenancy.central_domain'))

    <div class="relative min-h-full overflow-hidden">
        {{-- miękkie kształty marki --}}
        <div class="pointer-events-none absolute -left-40 -top-24 h-[30rem] w-[30rem] rounded-full bg-amber-300 opacity-30 blur-3xl"></div>
        <div class="pointer-events-none absolute right-0 top-1/3 h-[28rem] w-[28rem] rounded-full bg-rose-300 opacity-25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 left-1/4 h-[26rem] w-[26rem] rounded-full bg-orange-200 opacity-30 blur-3xl"></div>

        <div class="relative mx-auto w-full max-w-6xl px-6">
            {{-- Nawigacja --}}
            <header class="flex items-center justify-between py-6">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-white">◐</span>
                    <span class="text-xl font-semibold tracking-tight text-stone-900">{{ config('app.name') }}</span>
                </a>
                <nav class="flex items-center gap-2 sm:gap-4">
                    <a href="#funkcje" class="hidden text-sm font-medium text-stone-500 transition hover:text-stone-800 sm:inline">Możliwości</a>
                    <a href="#jak-to-dziala" class="hidden text-sm font-medium text-stone-500 transition hover:text-stone-800 sm:inline">Jak to działa</a>
                    <a href="#pakiety" class="hidden text-sm font-medium text-stone-500 transition hover:text-stone-800 sm:inline">Pakiety</a>
                    <a href="{{ route('login') }}" class="rounded-2xl px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-white/70">Zaloguj się</a>
                    <a href="{{ route('register') }}" class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">Załóż sklep</a>
                </nav>
            </header>

            {{-- Hero --}}
            <section class="grid items-center gap-12 py-12 lg:grid-cols-2 lg:py-20">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/70 px-3 py-1 text-xs font-medium text-amber-700 backdrop-blur">
                        Pakiet Kram — zacznij bez opłat
                    </span>
                    <h1 class="mt-5 text-4xl font-semibold leading-tight tracking-tight text-stone-900 sm:text-5xl">
                        Twój sklep internetowy <span class="bg-gradient-to-br from-amber-500 to-rose-500 bg-clip-text text-transparent">w 5 minut</span>
                    </h1>
                    <p class="mt-5 max-w-xl text-lg text-stone-600">
                        Kiedy konkurencja dopiero konfiguruje sklep, Ty już sprzedajesz. Załóż konto, dodaj pierwszy produkt i publikuj — bez wiedzy technicznej.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a href="{{ route('register') }}" class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            Załóż sklep za darmo
                        </a>
                        <a href="{{ route('login') }}" class="rounded-2xl border border-stone-200 bg-white/70 px-6 py-3.5 text-sm font-semibold text-stone-700 backdrop-blur transition hover:bg-white">
                            Mam już konto
                        </a>
                    </div>
                    <p class="mt-4 text-sm text-stone-500">Bez karty. Własny adres <span class="font-medium text-stone-700">twojsklep.{{ $domain }}</span> od razu.</p>
                </div>

                {{-- Makieta sklepu (dane przykładowe) --}}
                <div class="relative">
                    <div class="overflow-hidden rounded-3xl border border-white/60 bg-white/80 shadow-2xl shadow-amber-900/10 backdrop-blur-xl">
                        <div class="flex items-center gap-2 border-b border-stone-100 px-4 py-3">
                            <span class="h-3 w-3 rounded-full bg-rose-300"></span>
                            <span class="h-3 w-3 rounded-full bg-amber-300"></span>
                            <span class="h-3 w-3 rounded-full bg-emerald-300"></span>
                            <span class="ml-3 flex-1 truncate rounded-lg bg-stone-100 px-3 py-1 text-xs text-stone-500">bukiety-anny.{{ $domain }}</span>
                        </div>
                        <div class="p-5">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-stone-900">Bukiety Anny</p>
                                    <p class="text-xs text-stone-500">Kwiaty i florystyka okolicznościowa</p>
                                </div>
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-rose-400 text-white">🌷</span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                @foreach ([['Bukiet wiosenny', '89 zł', '💐'], ['Wiązanka ślubna', '249 zł', '💍'], ['Kompozycja w skrzynce', '139 zł', '🪴'], ['Storczyk w doniczce', '69 zł', '🌸']] as [$name, $price, $icon])
                                    <div class="rounded-2xl border border-stone-100 bg-white p-3">
                                        <div class="flex h-16 items-center justify-center rounded-xl bg-stone-50 text-2xl">{{ $icon }}</div>
                                        <p class="mt-2 truncate text-xs font-medium text-stone-800">{{ $name }}</p>
                                        <p class="text-xs font-semibold text-amber-700">{{ $price }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="pointer-events-none absolute -right-4 -top-4 rounded-2xl border border-white/60 bg-white/90 px-3 py-2 text-xs font-medium text-stone-700 shadow-lg backdrop-blur">
                        Przykładowy sklep
                    </div>
                </div>
            </section>

            {{-- Pakiety (dane realne z config/shop.php — jedno źródło prawdy) --}}
            <section id="pakiety" class="py-10">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-stone-900">Zacznij za darmo, rośnij, gdy zechcesz</h2>
                    <p class="mt-3 text-stone-600">Pakiet Kram jest bezpłatny bez limitu czasu. Płatne pakiety odblokowują płatności online, wysyłki i faktury — zmienisz je w każdej chwili.</p>
                    <p class="mt-2 text-stone-600">Obsługa zwrotów konsumenckich jest w <span class="font-medium text-stone-800">każdym</span> pakiecie, także darmowym — razem z pouczeniem w mailu, formularzem odstąpienia dla klienta i rozliczeniem zwrotu w panelu.</p>
                </div>
                <div class="mt-8 grid gap-5 sm:grid-cols-3">
                    @foreach (config('shop.packages') as $pkg)
                        @php($ent = $pkg['entitlements'])
                        @php($yearly = (int) $pkg['price_yearly'])
                        @php($monthly = intdiv($yearly, 10))
                        @php($featured = ($pkg['name'] ?? '') === 'Stragan')
                        @php($features = array_values(array_filter([
                            ['label' => 'Do '.$ent['max_products'].' produktów'],
                            ['label' => 'Własny adres i strona sklepu'],
                            ['label' => 'Opisy z korektą AI'],
                            // Zwroty są w KAŻDYM pakiecie, także darmowym — prawo
                            // odstąpienia od umowy przysługuje konsumentowi
                            // niezależnie od tego, ile sprzedawca płaci nam za
                            // sklep, więc nie da się tego zamknąć za bramką.
                            ['label' => 'Zwroty 14 dni zgodne z prawem'],
                            ['label' => $ent['online_payments'] ? 'Płatności online Paynow' : 'Przelew i odbiór osobisty'],
                            $ent['courier_shipping'] ? ['label' => 'Wysyłka kurierem i przez InPost'] : null,
                            $ent['invoices'] ? ['label' => 'Integracja z Fakturownią'] : null,
                            $ent['order_editing'] ? ['label' => 'Edycja zamówień'] : null,
                            $ent['discount_codes'] ? ['label' => 'Kody rabatowe w koszyku', 'soon' => true] : null,
                            $ent['bulk_mail'] ? ['label' => 'Newsletter produktowy', 'soon' => true] : null,
                        ])))
                        <div class="relative rounded-3xl border bg-white/70 p-6 backdrop-blur {{ $featured ? 'border-amber-300 ring-2 ring-amber-400/50 shadow-lg shadow-amber-900/5' : 'border-white/60' }}">
                            @if ($featured)
                                <span class="absolute -top-3 left-6 rounded-full bg-gradient-to-br from-amber-500 to-rose-500 px-3 py-1 text-[11px] font-semibold text-white shadow">Najczęściej wybierany</span>
                            @endif
                            <p class="font-semibold text-stone-900">{{ $pkg['name'] }}</p>
                            @if ($yearly === 0)
                                <p class="mt-3">
                                    <span class="text-3xl font-semibold tracking-tight text-stone-900">0 zł</span>
                                </p>
                                <p class="mt-1 text-xs text-stone-500">Za darmo, bez limitu czasu · bez karty</p>
                            @else
                                <p class="mt-3">
                                    <span class="text-3xl font-semibold tracking-tight text-stone-900">{{ $yearly }} zł</span>
                                    <span class="text-sm text-stone-500">/ rok</span>
                                </p>
                                <p class="mt-1 text-xs text-stone-500">To {{ $monthly }} zł/mies. — płacisz za 10 miesięcy, 2 gratis</p>
                            @endif
                            <ul class="mt-5 space-y-2 text-sm">
                                @foreach ($features as $feature)
                                    @php($soon = $feature['soon'] ?? false)
                                    <li class="flex items-start gap-2 {{ $soon ? 'text-stone-400' : 'text-stone-600' }}">
                                        <span class="mt-0.5 {{ $soon ? 'text-stone-300' : 'text-amber-600' }}">✓</span>
                                        <span>
                                            {{ $feature['label'] }}
                                            @if ($soon)
                                                <span class="ml-1 align-middle rounded-full bg-stone-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-stone-500">wkrótce</span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-xs text-stone-500">Ceny brutto, rozliczenie roczne. Pakiet zmienisz w każdej chwili.</p>
            </section>

            {{-- Funkcje --}}
            <section id="funkcje" class="py-16 lg:py-24">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-stone-900">Wszystko, czego potrzebujesz, by sprzedawać</h2>
                    <p class="mt-3 text-stone-600">Skupiamy się na tym, co najważniejsze — żebyś Ty mógł skupić się na swoich produktach.</p>
                </div>
                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['🏪', 'Własny adres sklepu', 'Każdy sklep dostaje subdomenę {nazwa}.'.$domain.' od razu po rejestracji.'],
                        ['⚡', 'Gotowy w kilka minut', 'Rejestracja, dodanie produktu i publikacja — bez wiedzy technicznej.'],
                        ['✨', 'Korekta opisów przez AI', 'Napisz szkic — AI poprawi styl, ortografię i interpunkcję jednym kliknięciem.'],
                        ['🔎', 'SEO od ręki', 'Przyjazne adresy, meta opisy, mapy strony i dane produktów dla wyszukiwarek.'],
                        ['💳', 'Płatności i dostawy', 'Odbiór osobisty, przelew i kolejne metody — konfigurowane w kilka chwil.'],
                        ['🧾', 'Dane firmy z NIP', 'Pobierz nazwę i adres firmy po numerze NIP i przyspiesz konfigurację.'],
                    ] as [$icon, $title, $desc])
                        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-amber-900/5">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-100 to-rose-100 text-xl">{{ $icon }}</span>
                            <h3 class="mt-4 font-semibold text-stone-900">{{ $title }}</h3>
                            <p class="mt-1.5 text-sm text-stone-500">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Jak to działa --}}
            <section id="jak-to-dziala" class="py-16 lg:py-20">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-stone-900">Od pomysłu do sprzedaży w czterech krokach</h2>
                    <p class="mt-3 text-stone-600">Bez umów, bez instalacji, bez czekania.</p>
                </div>
                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['Załóż konto', 'Podaj nazwę sklepu i e-mail. Adres sklepu tworzymy automatycznie.'],
                        ['Aktywuj sklep', 'Ustaw hasło, uzupełnij dane firmy i adres — także z NIP.'],
                        ['Dodaj produkt', 'Zdjęcie, cena, krótki opis — wsparty przez AI.'],
                        ['Sprzedawaj', 'Sklep publikuje się automatycznie. Udostępnij swój adres klientom.'],
                    ] as $i => [$title, $desc])
                        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-sm font-semibold text-white">{{ $i + 1 }}</span>
                            <h3 class="mt-4 font-semibold text-stone-900">{{ $title }}</h3>
                            <p class="mt-1.5 text-sm text-stone-500">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Dla kogo jest Kramio (branże, nie konkretne sklepy) --}}
            <section class="py-16 lg:py-20">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-stone-900">Dla kogo jest Kramio</h2>
                    <p class="mt-3 text-stone-600">Dla twórców i lokalnych marek, które chcą sprzedawać po swojemu — bez prowizji marketplace'u i bez agencji.</p>
                </div>
                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['Rękodzieło i sztuka', 'Ceramika, biżuteria, ilustracja — rzeczy robione ręcznie.', '🏺'],
                        ['Dom i zapachy', 'Świece, dekoracje, tekstylia i drobiazgi do wnętrz.', '🕯️'],
                        ['Moda i galanteria', 'Ubrania, torby, dodatki ze skóry i tkanin.', '👜'],
                        ['Lokalna żywność', 'Kawa, miód, przetwory, wypieki i produkty regionalne.', '☕'],
                        ['Dla dzieci', 'Zabawki, ubranka i wyprawki od małych twórców.', '🧸'],
                        ['Rośliny i florystyka', 'Bukiety, kompozycje i rośliny doniczkowe.', '🌷'],
                    ] as [$title, $desc, $icon])
                        <div class="flex items-start gap-4 rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-100 to-rose-100 text-2xl">{{ $icon }}</span>
                            <div class="min-w-0">
                                <p class="font-semibold text-stone-900">{{ $title }}</p>
                                <p class="mt-0.5 text-xs text-stone-500">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- CTA końcowe --}}
            <section class="py-12">
                <div class="overflow-hidden rounded-[2rem] border border-white/60 bg-gradient-to-br from-amber-500 to-rose-500 p-10 text-center shadow-2xl shadow-rose-500/20 sm:p-14">
                    <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Otwórz swój sklep już dziś</h2>
                    <p class="mx-auto mt-3 max-w-xl text-white/90">Załóż konto za darmo i sprawdź, jak szybko można zacząć sprzedawać własne produkty.</p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ route('register') }}" class="rounded-2xl bg-white px-6 py-3.5 text-sm font-semibold text-stone-900 shadow-lg transition hover:bg-stone-50">
                            Załóż sklep za darmo
                        </a>
                        <a href="{{ route('login') }}" class="rounded-2xl border border-white/40 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10">
                            Zaloguj się
                        </a>
                    </div>
                </div>
            </section>

            {{-- Stopka --}}
            <footer class="border-t border-stone-200/70 py-10">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-white">◐</span>
                        <span class="font-semibold tracking-tight text-stone-900">{{ config('app.name') }}</span>
                    </a>
                    <nav class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-stone-500">
                        <a href="{{ route('register') }}" class="transition hover:text-stone-800">Załóż sklep</a>
                        <a href="{{ route('login') }}" class="transition hover:text-stone-800">Zaloguj się</a>
                        <a href="{{ route('legal.terms') }}" class="transition hover:text-stone-800">Regulamin</a>
                        <a href="{{ route('legal.privacy') }}" class="transition hover:text-stone-800">Polityka prywatności</a>
                    </nav>
                </div>
                <p class="mt-6 text-center text-xs text-stone-400">© {{ now()->year }} {{ config('app.name') }}. Widok sklepu w nagłówku to wizualizacja produktu.</p>
            </footer>
        </div>
    </div>
</body>
</html>
