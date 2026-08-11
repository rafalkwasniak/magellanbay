<x-layouts.panel title="Ustawienia">
    <x-slot:actions>
        <span class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur">
            {{ $runtime['env'] }} · PHP {{ $runtime['php'] }}
        </span>
    </x-slot:actions>

    {{-- Ten sam podział 8/4 co w pozostałych działach: po lewej STAN (czy coś się
         pali), po prawej PRZEŁĄCZNIKI (czym to ugasić bez wgrywania kodu). --}}
    <div class="grid gap-6 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Integracje</h2>
                <p class="mt-1 text-sm text-stone-500">
                    Czy usługi platformy są wpięte. Kluczy ani sekretów tu nie pokazujemy —
                    do diagnozy wystarczy „jest / nie ma".
                </p>

                <ul class="mt-5 space-y-2">
                    @foreach ($integrations as $integration)
                        <li class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 px-4 py-3">
                            <span class="mt-0.5 shrink-0 text-lg">{{ $integration['ok'] ? '✅' : '⚠️' }}</span>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-stone-800">{{ $integration['name'] }}</span>
                                <span @class([
                                    'mt-0.5 block text-xs',
                                    'text-stone-500' => $integration['ok'],
                                    'text-amber-800' => ! $integration['ok'],
                                ])>{{ $integration['detail'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Zadania w tle i poczta</h2>
                <p class="mt-1 text-sm text-stone-500">
                    Faktury i maile idą kolejką. Stojący worker jest niewidoczny dla wszystkich,
                    dopóki ktoś nie zapyta „gdzie moja faktura".
                </p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['W kolejce', $queue['queued'], 'Zadania czekające na wykonanie', false],
                        ['Nieudane zadania', $queue['failed'], 'Wymagają ręcznego przejrzenia', $queue['failed'] > 0],
                        ['Maile do wysłania', $queue['mail_pending'], 'Czekają na przemielenie przez cron', false],
                        ['Maile nieudane', $queue['mail_failed'], 'Nie dotarły do adresata', $queue['mail_failed'] > 0],
                    ] as [$label, $value, $hint, $alarming])
                        <div @class([
                            'rounded-2xl border px-4 py-3',
                            'border-rose-200 bg-rose-50' => $alarming,
                            'border-stone-200 bg-white/60' => ! $alarming,
                        ])>
                            <p class="text-sm font-medium text-stone-500">{{ $label }}</p>
                            <p @class([
                                'mt-1 text-2xl font-semibold tabular-nums',
                                'text-rose-700' => $alarming,
                                'text-stone-900' => ! $alarming,
                            ])>{{ $value }}</p>
                            <p class="mt-0.5 text-xs text-stone-400">{{ $hint }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Błędy w logach</h2>
                <p class="mt-1 text-sm text-stone-500">
                    Liczba wpisów ERROR i wyżej w dziennych logach. Treść zostaje w plikach —
                    ten ekran ma powiedzieć „zajrzyj", a nie zastąpić log.
                </p>

                @if ($logErrors === [])
                    <p class="mt-4 text-sm text-stone-500">Brak plików logu z ostatnich dni.</p>
                @else
                    <ul class="mt-4 space-y-1">
                        @foreach ($logErrors as $day)
                            <li class="flex items-baseline justify-between gap-4 rounded-2xl px-3 py-2 {{ $day['errors'] > 0 ? 'bg-rose-50' : '' }}">
                                <span class="text-sm tabular-nums text-stone-600">{{ $day['date'] }}</span>
                                <span @class([
                                    'text-sm font-medium tabular-nums',
                                    'text-rose-700' => $day['errors'] > 0,
                                    'text-stone-400' => $day['errors'] === 0,
                                ])>{{ $day['errors'] }} {{ trans_choice('błąd|błędy|błędów', $day['errors']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Środowisko</h2>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    @foreach ([
                        ['Środowisko', $runtime['env']],
                        ['PHP', $runtime['php']],
                        ['Laravel', $runtime['laravel']],
                        ['Strefa czasowa', $runtime['timezone']],
                    ] as [$label, $value])
                        <div>
                            <dt class="text-xs text-stone-400">{{ $label }}</dt>
                            <dd class="mt-0.5 text-stone-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                {{-- APP_DEBUG na produkcji pokazuje obcym ludziom ślady stosu i
                     zawartość `.env`. Dlatego nie jest jedną z czterech linijek
                     wyżej, tylko osobnym alarmem. --}}
                @if ($runtime['debug'])
                    <p class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <span class="font-medium">APP_DEBUG jest włączony.</span>
                        Na produkcji pokazuje obcym ślady stosu i zawartość konfiguracji — wyłącz w <code>.env</code>.
                    </p>
                @else
                    <p class="mt-4 text-xs text-stone-400">APP_DEBUG wyłączony — tak ma być na produkcji.</p>
                @endif
            </div>
        </div>

        <aside class="space-y-6 lg:col-span-4">
            <form method="POST" action="{{ route('administrator.settings.update') }}"
                class="space-y-5 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                @csrf

                <div>
                    <h2 class="font-semibold text-stone-900">Przełączniki</h2>
                    <p class="mt-1 text-sm text-stone-500">Działają od razu po zapisaniu, bez wgrywania kodu.</p>
                </div>

                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4">
                    <input type="checkbox" name="registration_open" value="1" @checked($registrationOpen)
                        class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-emerald-600 focus:ring-4 focus:ring-amber-500/20">
                    <span>
                        <span class="block text-sm font-medium text-stone-800">Rejestracja sprzedawców otwarta</span>
                        <span class="mt-0.5 block text-xs text-stone-500">
                            Wyłączenie zamyka formularz zakładania sklepu. Logowanie zostaje otwarte —
                            sprzedawcy, którzy już mają sklepy, pracują normalnie.
                        </span>
                    </span>
                </label>

                <div>
                    <label for="maintenance_notice" class="block text-sm font-medium text-stone-700">Komunikat o przerwie</label>
                    <textarea name="maintenance_notice" id="maintenance_notice" rows="3" maxlength="300"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">{{ old('maintenance_notice', $maintenanceNotice) }}</textarea>
                    <p class="mt-1.5 text-xs text-stone-400">
                        Puste = brak baneru. Pokaże się nad każdym ekranem panelu i na zamkniętej rejestracji —
                        ale nie na sklepach sprzedawców.
                    </p>
                    @error('maintenance_notice') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                    class="w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                    Zapisz
                </button>
            </form>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to czytać</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">⚙️</span>
                        <span>Cennik, progi i dane firmy <span class="text-stone-700">nie są tutaj</span> — żyją w plikach <code>config/</code> i zmieniają się razem z kodem. Trzymanie ich w dwóch miejscach naraz kończy się rozjazdem.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔑</span>
                        <span>Klucze i sekrety <span class="text-stone-700">nigdy nie trafiają na ekran</span>. Widać tylko, czy integracja jest wpięta — klucz w przeglądarce to klucz w jej historii.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">💾</span>
                        <span>Kopie zapasowe świecą na czerwono, bo <span class="text-stone-700">naprawdę ich nie ma</span>. To jedyna pozycja, która blokuje spokojne wpuszczenie sprzedawców.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
