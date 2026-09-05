<x-layouts.panel title="Zgłoszenie">
    <a href="{{ route('seller.reports.index') }}" class="text-sm font-medium text-stone-500 underline-offset-2 hover:underline">← Wszystkie zgłoszenia</a>

    {{-- Komunikaty pokazuje toast z layoutu panelu, tak jak w pozostałych
         działach — bez dublowania ich w treści ekranu. --}}
    <div class="mt-4 grid gap-6 lg:grid-cols-12">
        {{-- 8/4, nie 7/5: w zbudowanym CSS istnieją WYŁĄCZNIE `lg:col-span-4`
             i `lg:col-span-8`. Klasa spoza buildu nic nie robi po cichu, a wtedy
             oba bloki spadają do jednej kolumny z dwunastu. --}}
        <div class="lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Numer sprawy jako pierwszy: to on jest w temacie każdego
                         maila i po nim zgłaszający pyta o swoją sprawę. --}}
                    <span class="font-mono text-sm font-semibold text-stone-700">{{ $report->reference() }}</span>
                    @php([$badgeBg, $badgeText] = $report->status->badgeClasses())
                    <span class="rounded-full {{ $badgeBg }} px-3 py-1 text-xs font-semibold {{ $badgeText }}">{{ $report->status->label() }}</span>
                    <span class="text-sm font-medium text-stone-800">{{ $report->category->label() }}</span>
                </div>

                <h1 class="mt-4 text-sm font-medium text-stone-500">Zgłoszony adres</h1>
                {{-- `break-words`, nie `break-all` — tej drugiej nie ma w buildzie,
                     a długi adres bez łamania rozpycha kartę. --}}
                <a href="{{ $report->url }}" target="_blank" rel="noopener noreferrer nofollow"
                    class="mt-1 block break-words text-sm text-stone-800 underline decoration-amber-300 underline-offset-2">{{ $report->url }}</a>

                <h2 class="mt-6 text-sm font-medium text-stone-500">Uzasadnienie zgłaszającego</h2>
                <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-stone-700">{{ $report->justification }}</p>
            </div>
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="text-sm font-medium text-stone-500">Kto zgłasza</h2>
                <p class="mt-1 text-sm text-stone-800">{{ $report->reporter_name ?: 'nie podano nazwiska' }}</p>
                <p class="text-sm text-stone-600">{{ $report->reporter_email }}</p>
                <p class="mt-3 text-xs text-stone-400">
                    Zgłoszono {{ $report->created_at->format('d.m.Y H:i') }} z adresu {{ $report->ip_address ?: '—' }}.
                    @if ($report->good_faith)
                        Oświadczenie o dobrej wierze: złożone.
                    @endif
                    @if ($report->acknowledged_at)
                        Potwierdzenie odbioru wysłane {{ $report->acknowledged_at->format('d.m.Y H:i') }}.
                    @endif
                </p>
            </div>

            {{-- Bez karty „czego dotyczy" ze wskazaniem sklepu: w panelu
                 właściciela odpowiedź jest zawsze ta sama i byłaby szumem.
                 Zamiast niej — jedyna rzecz, której na tym ekranie brakuje,
                 czyli droga do treści, o którą chodzi. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="text-sm font-medium text-stone-500">Zanim rozstrzygniesz</h2>
                <p class="mt-2 text-sm leading-relaxed text-stone-600">
                    Otwórz zgłoszony adres i sprawdź, o którą treść chodzi. Jeśli zgłoszenie jest zasadne,
                    zmień albo wyłącz produkt w dziale <a href="{{ route('seller.products.index') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">Produkty</a>,
                    a dopiero potem wróć tutaj po decyzję.
                </p>
                <p class="mt-3 text-xs leading-relaxed text-stone-400">
                    Rozpatrzenie zgłoszenia i zmiana w katalogu to dwie osobne rzeczy — dlatego nie ma tu przycisku
                    „ukryj produkt". Dzięki temu w historii zostaje ślad po obu, a nie po jednym kliknięciu.
                </p>
            </div>
        </aside>
    </div>

    <div class="mt-6 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
        @if ($report->status->isDecided())
            <h2 class="text-sm font-medium text-stone-500">Rozstrzygnięcie</h2>
            <p class="mt-1 text-sm font-semibold text-stone-800">{{ $report->status->label() }}</p>
            <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-stone-700">{{ $report->decision_reason }}</p>
            <p class="mt-3 text-xs text-stone-400">
                {{ $report->decided_at?->format('d.m.Y H:i') }}
                @if ($report->decidedBy)
                    · {{ trim($report->decidedBy->name.' '.$report->decidedBy->surname) }}
                @endif
                · powiadomienie wysłane
            </p>
        @else
            <h2 class="text-sm font-medium text-stone-500">Rozpatrz zgłoszenie</h2>
            <p class="mt-1 text-xs text-stone-500">
                Uzasadnienie trafia do zgłaszającego — pisz je jak pismo, nie jak notatkę.
                Rozstrzygnięcia nie da się cofnąć z tego ekranu.
            </p>

            <form method="POST" action="{{ route('seller.reports.decide', $report) }}" class="mt-4 space-y-4" novalidate data-validate>
                @csrf

                {{-- `required` na grupie radio działa dzięki obsłudze grup
                     w `forms.js` — wcześniej sprawdzał każdy przycisk osobno, więc
                     wybranie „Uznane" zapalało błąd przy „Odrzucone".
                     Źródłem prawdy pozostaje `ContentReportDecisionRequest`. --}}
                <div class="flex flex-wrap gap-3">
                    @foreach ([\App\Enums\ContentReportStatus::Upheld, \App\Enums\ContentReportStatus::Rejected] as $case)
                        <label class="flex items-center gap-2 rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm">
                            <input type="radio" name="status" value="{{ $case->value }}" required
                                data-msg-required="Wybierz, czy zgłoszenie jest uznane, czy odrzucone."
                                @checked(old('status') === $case->value)>
                            <span class="font-medium text-stone-700">{{ $case->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('status')
                    <p class="text-sm text-rose-600">{{ $message }}</p>
                @enderror

                <div>
                    <label for="decision_reason" class="block text-sm font-medium text-stone-700">Uzasadnienie</label>
                    <textarea id="decision_reason" name="decision_reason" rows="5" required
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">{{ old('decision_reason') }}</textarea>
                    @error('decision_reason')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                    Rozstrzygnij i powiadom
                </button>
            </form>
        @endif
    </div>
</x-layouts.panel>
