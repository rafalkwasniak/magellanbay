{{-- Publiczny formularz zgłoszenia treści bezprawnej, w DWÓCH szatach.

     Kramio: strona centrali, bursztynowa skóra platformy, tekst mówiący
     wprost, że sklepy prowadzą niezależni sprzedawcy, a zgłoszenie rozpatruje
     operator. Tam to prawda i jest ważna — zgłaszający musi wiedzieć, że pisze
     do kogoś innego niż sprzedawca.

     Sklep dedykowany: ta sama strona w SZACIE SKLEPU, bo tam nie ma operatora
     ani „innych sklepów". Zgłoszenie idzie do właściciela i tylko jego dotyczy,
     więc formularz w cudzych barwach i z mową o platformie byłby po prostu
     nieprawdziwy — a przy okazji wyglądałby jak strona z innego serwisu,
     na którą klient trafił przez pomyłkę.

     POLA są wspólne (`reports/_fields`) — różnią się wyłącznie klasami. --}}
@php
    $dedicated = \App\Support\Mode::dedicated();
@endphp

@if ($dedicated)
    @php
        $ui = [
            'label' => 'block text-sm font-medium',
            'hint' => 'mt-0.5 text-xs opacity-70',
            'input' => 'st-border mt-1.5 block w-full rounded-xl border bg-transparent px-4 py-2.5 text-sm focus:outline-none',
            'error' => 'mt-1.5 text-sm text-rose-600',
            'notice' => 'st-card st-border rounded-xl border p-3',
            'noticeText' => 'flex items-start gap-3 text-xs leading-relaxed',
            'button' => 'st-btn inline-flex rounded-xl px-5 py-2.5 text-sm font-semibold transition hover:brightness-110',
        ];
    @endphp

    <x-layouts.storefront :shop="$shop" title="Zgłoś nielegalną treść">
        <main class="mx-auto max-w-2xl px-6 pt-10 pb-16">
            <h1 class="st-box-title text-3xl font-semibold tracking-tight">Zgłoś nielegalną treść</h1>
            <p class="mt-3 text-sm leading-relaxed opacity-80">
                Jeśli w tym sklepie widzisz treść, która narusza prawo — zdjęcie, opis, nazwę produktu —
                napisz nam o tym tutaj. Sprawdzimy zgłoszenie i odpiszemy Ci z uzasadnieniem.
            </p>
            <p class="mt-2 text-sm leading-relaxed opacity-80">
                <span class="font-medium">To nie jest miejsce na reklamacje ani zwroty.</span>
                Jeśli chodzi o Twoje zamówienie, napisz na nasz zwykły adres kontaktowy — załatwimy to szybciej.
            </p>

            {{-- Potwierdzenie wysyłki pokazuje toast z layoutu, nie formularz.
                 Dwa komunikaty o tym samym to szum. --}}
            <form method="POST" action="{{ route('reports.store') }}" class="mt-8 space-y-5" novalidate data-validate>
                @include('reports._fields', [
                    'ui' => $ui,
                    'urlPlaceholder' => 'https://'.$shop->host().'/produkt/12-nazwa',
                ])
            </form>

            <p class="mt-8 text-xs leading-relaxed opacity-60">
                Zgłoszenie rozpatruje {{ $shop->company_name ?: $shop->name }}. Dane z formularza przetwarzamy wyłącznie po to,
                żeby je rozpatrzyć i odpowiedzieć — zasady opisuje
                <a href="{{ $shop->privacyPath() }}" class="underline underline-offset-2">Polityka prywatności</a>.
            </p>
        </main>
    </x-layouts.storefront>
@else
    @php
        $ui = [
            'label' => 'block text-sm font-medium text-stone-700',
            'hint' => 'mt-0.5 text-xs text-stone-500',
            'input' => 'mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15',
            'error' => 'mt-1.5 text-sm text-rose-600',
            'notice' => 'rounded-xl border border-amber-200 bg-amber-50 p-3',
            'noticeText' => 'flex items-start gap-3 text-xs leading-relaxed text-amber-900',
            'button' => 'inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105',
        ];
    @endphp

    <x-layouts.public title="Zgłoś nielegalną treść">
        <article class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl sm:p-10">
            <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Zgłoś nielegalną treść</h1>
            <p class="mt-2 text-stone-500">
                Sklepy w {{ config('app.name') }} prowadzą niezależni sprzedawcy, a my udostępniamy im narzędzie i miejsce.
                Jeśli w którymś sklepie widzisz treść, która narusza prawo, napisz nam o tym tutaj — sprawdzimy zgłoszenie
                i poinformujemy Cię o rozstrzygnięciu.
            </p>

            <form method="POST" action="{{ route('reports.store') }}" class="mt-8 space-y-5" novalidate data-validate>
                @include('reports._fields', [
                    'ui' => $ui,
                    'urlPlaceholder' => 'https://nazwa-sklepu.'.config('tenancy.central_domain').'/produkt/12-nazwa',
                ])
            </form>

            <p class="mt-8 text-xs leading-relaxed text-stone-500">
                Zgłoszenie rozpatruje {{ config('company.name') }} jako operator {{ config('app.name') }}. Dane z formularza przetwarzamy
                wyłącznie po to, żeby je rozpatrzyć i odpowiedzieć — zasady opisuje
                <a href="{{ route(\App\Enums\LegalDocumentType::Privacy->routeName()) }}" class="underline underline-offset-2">Polityka Prywatności</a>.
                Reklamacje dotyczące zakupów kieruj do sprzedawcy prowadzącego dany sklep, nie tutaj.
            </p>
        </article>
    </x-layouts.public>
@endif
