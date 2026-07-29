<x-layouts.panel title="Wiadomości do klientów">
    <x-slot:heading>Wiadomości do klientów</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: szkice i historia wysyłek --}}
        <div class="lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-900">Twoje wiadomości</h2>
                        <p class="mt-1 text-sm text-stone-500">Napisz do klientów, którzy zgodzili się na wiadomości od Twojego sklepu.</p>
                    </div>
                    @if ($allowed)
                        <a href="{{ route('seller.mailings.create') }}"
                            class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            Napisz wiadomość
                        </a>
                    @endif
                </div>

                @unless ($allowed)
                    {{-- Bez uprawnienia `bulk_mail` (Pawilon) — zachęta zamiast narzędzia. --}}
                    <div class="mt-6 rounded-2xl border border-dashed border-stone-300 bg-white/40 p-8 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-100 text-2xl">📣</span>
                        <p class="mt-4 font-medium text-stone-700">Wiadomości do klientów w pakiecie Pawilon</p>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-stone-500">
                            Napisz o nowościach albo promocji do klientów, którzy się na to zgodzili — bez zewnętrznych narzędzi.
                            Twój obecny pakiet: <span class="font-medium text-stone-700">{{ $shop?->packageName() ?? 'brak sklepu' }}</span>.
                        </p>
                    </div>
                @elseif ($mailings->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">📣</span>
                        <p class="mt-4 font-medium text-stone-700">Nie napisałeś jeszcze żadnej wiadomości</p>
                        <p class="mt-1 text-sm text-stone-500">Napisz pierwszą, wyślij próbkę do siebie i dopiero potem puść ją do klientów.</p>
                    </div>
                @else
                    <ul class="mt-6 space-y-3">
                        @foreach ($mailings as $mailing)
                            <li class="rounded-2xl border border-stone-200 bg-white/70 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('seller.mailings.edit', $mailing) }}" class="break-words font-semibold text-stone-900 transition hover:text-amber-700">
                                            {{ $mailing->subject }}
                                        </a>
                                        <p class="mt-1 text-xs text-stone-400">
                                            @if ($mailing->isSent())
                                                Wysłano {{ $mailing->sent_at->format('d.m.Y, H:i') }}
                                                · {{ $mailing->recipients_count }} {{ $mailing->recipients_count === 1 ? 'odbiorca' : 'odbiorców' }}
                                            @else
                                                Szkic · utworzony {{ $mailing->created_at->format('d.m.Y') }}
                                                @if ($mailing->test_sends > 0)
                                                    · sprawdzony {{ $mailing->test_sends }} {{ $mailing->test_sends === 1 ? 'raz' : 'razy' }}
                                                @endif
                                            @endif
                                        </p>
                                    </div>
                                    @if ($mailing->isSent())
                                        <span class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Wysłana</span>
                                    @else
                                        <span class="shrink-0 rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-500">Szkic</span>
                                    @endif
                                </div>

                                {{-- Akcje jak przy kodach rabatowych: edycja z lewej,
                                     usuwanie z prawej. Wysłanej wiadomości nie da się
                                     skasować — klienci mają ją w skrzynkach, więc
                                     zostaje jako zapis, co do nich poszło. --}}
                                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-stone-100 pt-3">
                                    <a href="{{ route('seller.mailings.edit', $mailing) }}"
                                        class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100">
                                        {{ $mailing->isSent() ? 'Podejrzyj' : 'Edytuj' }}
                                    </a>

                                    @unless ($mailing->isSent())
                                        <form method="POST" action="{{ route('seller.mailings.destroy', $mailing) }}" class="ml-auto"
                                            onsubmit="return confirm('Usunąć szkic „{{ $mailing->subject }}”? Tej operacji nie da się cofnąć.');">
                                            @csrf
                                            <button type="submit" class="text-sm font-medium text-rose-700 transition hover:text-rose-800">Usuń</button>
                                        </form>
                                    @endunless
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-6">{{ $mailings->links() }}</div>
                @endunless
            </div>
        </div>

        {{-- Kolumna boczna: kto dostanie i kiedy można wysłać --}}
        @if ($allowed)
            <aside class="space-y-6 lg:col-span-4">
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Odbiorcy</h2>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-900">{{ $recipients }}</p>
                    <p class="mt-1 text-sm text-stone-500">
                        {{ $recipients === 1 ? 'klient zgodził się' : 'klientów zgodziło się' }} na wiadomości od Twojego sklepu.
                    </p>
                    <p class="mt-3 text-xs text-stone-400">
                        Zgodę zaznacza sam klient — przy zakładaniu konta albo później w swoim profilu. Nie da się jej dodać za niego,
                        a wypisanie działa natychmiast.
                    </p>
                </div>

                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Kiedy możesz wysłać</h2>
                    @if ($blockedUntil !== null)
                        <p class="mt-3 text-sm font-medium text-amber-700">Od {{ $blockedUntil->format('d.m.Y') }}</p>
                        <p class="mt-1 text-xs text-stone-500">
                            Klienci dostają najwyżej jedną wiadomość na {{ config('bulk_mail.cooldown_days') }} dni. Szkic możesz pisać i testować już teraz.
                        </p>
                    @else
                        <p class="mt-3 text-sm font-medium text-emerald-700">Możesz wysłać teraz</p>
                        <p class="mt-1 text-xs text-stone-500">
                            Po wysyłce kolejna wiadomość odblokuje się po {{ config('bulk_mail.cooldown_days') }} dniach.
                        </p>
                    @endif
                </div>
            </aside>
        @endif
    </div>
</x-layouts.panel>
