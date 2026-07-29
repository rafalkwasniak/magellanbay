<x-layouts.panel title="Mój pakiet">
    <x-slot:heading>Mój pakiet</x-slot:heading>

    @php($active = $shop->subscriptionActive())
    @php($endsAt = $shop->subscription_ends_at)
    @php($daysLeft = $endsAt !== null && $active && ! $shop->comped ? (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false) : null)

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-8">
            {{-- Stan abonamentu na wierzchu: nazwa pakietu, termin, cena. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm text-stone-500">Twój pakiet</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900">{{ $shop->packageName() }}</p>
                    </div>
                    @if (! $active)
                        <span class="shrink-0 rounded-full bg-rose-50 px-3 py-1 text-xs font-medium text-rose-700">Abonament wygasł</span>
                    @elseif ($shop->comped)
                        <span class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Dostęp bezpłatny</span>
                    @elseif ($shop->priceYearly() <= 0)
                        <span class="shrink-0 rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-600">Za darmo, bez limitu czasu</span>
                    @else
                        <span class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Aktywny</span>
                    @endif
                </div>

                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    @if ($shop->priceYearly() > 0)
                        <div class="rounded-2xl border border-stone-200 bg-white/60 px-4 py-3">
                            <dt class="text-xs text-stone-400">Opłata roczna</dt>
                            <dd class="mt-0.5 font-semibold text-stone-900">{{ \App\Support\Money::pln($shop->priceYearly()) }}</dd>
                        </div>
                    @endif

                    <div class="rounded-2xl border border-stone-200 bg-white/60 px-4 py-3">
                        <dt class="text-xs text-stone-400">{{ $active ? 'Opłacony do' : 'Wygasł' }}</dt>
                        <dd class="mt-0.5 font-semibold text-stone-900">
                            @if ($shop->comped)
                                bezterminowo
                            @elseif ($shop->priceYearly() <= 0)
                                nie wygasa
                            @elseif ($endsAt === null)
                                bezterminowo
                            @else
                                {{ $endsAt->format('d.m.Y') }}
                            @endif
                        </dd>
                    </div>
                </dl>

                {{-- Ostrzeżenia: po terminie mówimy, co przestało działać; przed
                     terminem przypominamy dopiero, gdy zostało ≤ 30 dni. --}}
                @if (! $active)
                    <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                        <p class="font-medium">Twój sklep działa teraz na zasadach pakietu {{ config('shop.packages.'.config('shop.default_package').'.name') }}</p>
                        <p class="mt-1 text-xs text-rose-800">
                            Sklep i zamówienia działają dalej — wyłączone są funkcje płatnego pakietu. Po opłaceniu wszystko wraca
                            takie, jak było, razem z ustawieniami.
                        </p>
                    </div>
                @elseif ($daysLeft !== null && $daysLeft <= 30)
                    <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <p class="font-medium">
                            @if ($daysLeft <= 0)
                                Abonament kończy się dziś
                            @else
                                Abonament kończy się za {{ $daysLeft }} {{ trans_choice('{1}dzień|[2,4]dni|[5,*]dni', $daysLeft) }}
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-amber-800">Napisz do nas, żeby przedłużyć — zdążymy bez przerwy w działaniu sklepu.</p>
                    </div>
                @endif
            </div>

            {{-- Co jest w pakiecie: czytane z UPRAWNIEŃ SKLEPU, więc widać też
                 moduły nadane poza pakietem. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Co masz w pakiecie</h2>
                <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($features as $feature)
                        <li class="flex items-start gap-2 text-sm text-stone-600">
                            <span class="mt-0.5 text-amber-600">✓</span>
                            <span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Wyższe pakiety: pogrubione to, co dochodzi (ta sama logika co na
                 stronie głównej, więc obietnica i panel mówią jednym głosem). --}}
            @if ($upgrades !== [])
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Co dostaniesz wyżej</h2>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        @foreach ($upgrades as $package)
                            @php($quote = $quotes[$package['key']] ?? null)
                            <div class="rounded-2xl border border-stone-200 bg-white/60 p-5">
                                <p class="font-semibold text-stone-900">{{ $package['name'] }}</p>
                                <p class="mt-1 text-sm text-stone-500">
                                    {{ number_format($package['price_yearly'], 0, ',', ' ') }} zł / rok
                                    <span class="text-stone-400">· {{ intdiv($package['price_yearly'], 10) }} zł/mies., 2 miesiące gratis</span>
                                </p>

                                {{-- Kwota przejścia DZIŚ: zawsze pełny rok nowego
                                     pakietu, pomniejszony o resztówkę obecnego. --}}
                                @if ($quote !== null && $quote['kind'] === 'credit')
                                    <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                        Zmiana teraz: <span class="font-semibold">{{ \App\Support\Money::pln($quote['amount']) }}</span>
                                        <span class="block text-xs text-amber-800">
                                            rok w nowym pakiecie minus {{ \App\Support\Money::pln($quote['credit']) }} zniżki
                                            za {{ $quote['days_left'] }} {{ trans_choice('{1}niewykorzystany dzień|[2,4]niewykorzystane dni|[5,*]niewykorzystanych dni', $quote['days_left']) }}
                                        </span>
                                        <span class="mt-1 block text-xs text-amber-800">Nowy termin: {{ $quote['new_ends_at']->format('d.m.Y') }}</span>
                                    </p>
                                @elseif ($quote !== null && $quote['kind'] === 'full')
                                    <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                        Zmiana teraz: <span class="font-semibold">{{ \App\Support\Money::pln($quote['amount']) }}</span>
                                        <span class="block text-xs text-amber-800">pełny rok, licząc od dnia opłacenia</span>
                                    </p>
                                @endif
                                <ul class="mt-4 space-y-2 text-sm">
                                    @foreach ($package['features'] as $feature)
                                        <li class="flex items-start gap-2 {{ $feature['is_new'] ? 'font-medium text-stone-900' : 'text-stone-500' }}">
                                            <span class="mt-0.5 {{ $feature['is_new'] ? 'text-amber-600' : 'text-stone-300' }}">✓</span>
                                            <span>{{ $feature['label'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <aside class="space-y-6 lg:col-span-4">
            {{-- Zużycie limitów — jedyne liczby, które sprzedawca musi pilnować sam. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Wykorzystanie</h2>

                @php($productsPct = $usage['products_limit'] > 0 ? min(100, (int) round($usage['products'] / $usage['products_limit'] * 100)) : 0)
                <div class="mt-4">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-stone-500">Produkty</span>
                        <span class="font-semibold tabular-nums text-stone-900">{{ $usage['products'] }} / {{ $usage['products_limit'] }}</span>
                    </div>
                    {{-- Pasek jak na Pulpicie: zielony gradient z animacją. Przy
                         wyczerpanym limicie przechodzi w róż — to już komunikat,
                         nie dekoracja. --}}
                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-stone-100">
                        <div class="h-full min-w-[0.5rem] rounded-full transition-all duration-500 {{ $productsPct >= 100 ? 'bg-rose-400' : 'bg-gradient-to-r from-emerald-400 to-emerald-600' }}" style="width: {{ $productsPct }}%"></div>
                    </div>
                    @if ($productsPct >= 100)
                        <p class="mt-2 text-xs text-rose-700">Limit wyczerpany — nowe produkty dodasz w wyższym pakiecie.</p>
                    @endif
                </div>

                @php($aiPct = $usage['ai_limit'] > 0 ? min(100, (int) round($usage['ai_used'] / $usage['ai_limit'] * 100)) : 0)
                <div class="mt-5">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-stone-500">Zadania AI w tym tygodniu</span>
                        <span class="font-semibold tabular-nums text-stone-900">{{ $usage['ai_used'] }} / {{ $usage['ai_limit'] }}</span>
                    </div>
                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-stone-100">
                        <div class="h-full min-w-[0.5rem] rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all duration-500" style="width: {{ $aiPct }}%"></div>
                    </div>
                    <p class="mt-2 text-xs text-stone-400">Pula odnawia się co tydzień.</p>
                </div>
            </div>

            {{-- Zmiana pakietu: zakup online dojdzie osobno, więc mówimy wprost,
                 jak to zrobić dziś, zamiast pokazywać martwy przycisk. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Zmiana pakietu</h2>

                @if ($quotes !== [])
                    {{-- Zasada dopłaty wyłożona wprost, z kwotami dla każdego
                         droższego pakietu. Sprzedawca ma decydować na podstawie
                         liczb, nie domysłów. --}}
                    <p class="mt-2 text-sm text-stone-500">
                        Wyższy pakiet kupujesz <span class="font-medium text-stone-700">na rok</span>, a to, co zostało
                        z obecnego, <span class="font-medium text-stone-700">odejmujemy jako zniżkę</span> — nic nie tracisz,
                        a termin liczy się od nowa.
                    </p>

                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($quotes as $slug => $quote)
                            <li class="flex items-center justify-between gap-3 rounded-xl border border-stone-200 bg-white/60 px-3 py-2">
                                <span class="text-stone-600">{{ config("shop.packages.{$slug}.name") }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-stone-900">
                                    {{ \App\Support\Money::pln($quote['amount']) }}
                                    @if ($quote['kind'] === 'full')
                                        <span class="text-xs font-normal text-stone-400">/ rok</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    @php($sample = reset($quotes))
                    @if ($sample['kind'] === 'credit')
                        <p class="mt-3 text-xs text-stone-400">
                            Zniżka to Twoja opłata roczna × {{ $sample['days_left'] }}
                            {{ trans_choice('{1}dzień|[2,4]dni|[5,*]dni', $sample['days_left']) }} ÷ 365
                            (dziś {{ \App\Support\Money::pln($sample['credit']) }}), zaokrąglone na Twoją korzyść.
                            Im wcześniej zmienisz pakiet, tym większa zniżka.
                        </p>
                    @endif

                    <p class="mt-3 text-sm text-stone-500">Napisz do nas, a przestawimy pakiet od razu.</p>
                @else
                    <p class="mt-2 text-sm text-stone-500">
                        Masz najwyższy pakiet. Jeśli chcesz zejść niżej, napisz do nas — obniżka wejdzie przy odnowieniu,
                        a do końca opłaconego terminu korzystasz z tego, co masz teraz.
                    </p>
                @endif
                <a href="mailto:{{ $contactEmail }}?subject={{ rawurlencode('Zmiana pakietu — '.$shop->name) }}"
                    class="mt-4 inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                    Napisz do nas
                </a>
                <p class="mt-3 text-xs text-stone-400">{{ $contactEmail }}</p>
            </div>
        </aside>
    </div>
</x-layouts.panel>
