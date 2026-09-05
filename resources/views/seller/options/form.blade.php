<x-layouts.panel :title="$group->exists ? 'Grupa: '.$group->name : 'Nowa grupa opcji'">
    <x-slot:heading>{{ $group->exists ? 'Grupa: '.$group->name : 'Nowa grupa opcji' }}</x-slot:heading>

    <x-slot:actions>
        <a href="{{ route('seller.options.index') }}"
            class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur transition hover:bg-white">
            Wróć do listy
        </a>
    </x-slot:actions>

    <form method="POST"
        action="{{ $group->exists ? route('seller.options.update', $group) : route('seller.options.store') }}"
        class="grid gap-6 lg:grid-cols-12" novalidate data-validate>
        @csrf

        <div class="lg:col-span-8 space-y-6">
            <div class="space-y-5 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div>
                    <label for="name" class="block text-sm font-medium text-stone-700">Nazwa grupy</label>
                    <p class="mt-0.5 text-xs text-stone-500">Zobaczy ją kupujący nad polami — np. „Nadruk imienia" albo „Grawer".</p>
                    <input id="name" name="name" type="text" required value="{{ old('name', $group->name) }}"
                        data-msg-required="Podaj nazwę grupy — zobaczy ją kupujący nad polami."
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    @error('name')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                @if ($group->exists)
                    {{-- RODZAJU NIE DA SIĘ ZMIENIĆ. Pokazujemy go jako fakt, nie jako
                         wyłączone pole: wyłączony select kusi do klikania i wygląda
                         jak usterka. Zmiana osierociłaby pola albo pozycje biblioteki,
                         a produkty z tą grupą przestałyby dać się kupić. --}}
                    <div>
                        <span class="block text-sm font-medium text-stone-700">Rodzaj</span>
                        <p class="mt-1.5 rounded-2xl bg-stone-50 px-4 py-2.5 text-sm text-stone-700">
                            {{ $group->kind->label() }}
                            <span class="block text-xs text-stone-500">Rodzaju nie da się zmienić po utworzeniu — załóż nową grupę, jeśli potrzebujesz innego.</span>
                        </p>
                    </div>
                @else
                    <fieldset>
                        <legend class="block text-sm font-medium text-stone-700">Rodzaj</legend>
                        <div class="mt-2 space-y-2">
                            @foreach ($kinds as $kind)
                                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-stone-200 bg-white/80 p-4 transition hover:border-amber-300">
                                    <input type="radio" name="kind" value="{{ $kind->value }}" required class="mt-1 shrink-0"
                                        data-msg-required="Wybierz, czy klient ma wpisywać tekst, czy wybierać z biblioteki."
                                        @checked(old('kind') === $kind->value)>
                                    <span>
                                        <span class="font-medium text-stone-800">{{ $kind->label() }}</span>
                                        <span class="mt-0.5 block text-xs leading-relaxed text-stone-500">{{ $kind->description() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('kind')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </fieldset>
                @endif

                <div>
                    <label for="hint" class="block text-sm font-medium text-stone-700">Podpowiedź dla klienta</label>
                    <p class="mt-0.5 text-xs text-stone-500">Jedno zdanie pod nazwą grupy. Nieobowiązkowe.</p>
                    <input id="hint" name="hint" type="text" value="{{ old('hint', $group->hint) }}"
                        placeholder="np. Wpisz imię, które nadrukujemy na froncie."
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    @error('hint')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="surcharge_gross" class="block text-sm font-medium text-stone-700">Dopłata za wykonanie (zł)</label>
                        <p class="mt-0.5 text-xs text-stone-500">Zero, jeśli koszt jest już w cenie produktu.</p>
                        <input id="surcharge_gross" name="surcharge_gross" type="text" inputmode="decimal"
                            value="{{ old('surcharge_gross', number_format((float) $group->surcharge_gross, 2, ',', '')) }}"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        @error('surcharge_gross')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="excludes_group_id" class="block text-sm font-medium text-stone-700">Wyklucza się z grupą</label>
                        <p class="mt-0.5 text-xs text-stone-500">Klient wypełni jedną albo drugą, nigdy obie.</p>
                        <select id="excludes_group_id" name="excludes_group_id"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <option value="">— z żadną —</option>
                            @foreach ($others as $other)
                                <option value="{{ $other->id }}" @selected((int) old('excludes_group_id', $group->excludes_group_id) === $other->id)>{{ $other->name }}</option>
                            @endforeach
                        </select>
                        @error('excludes_group_id')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <label class="flex items-start gap-3 text-sm text-stone-700">
                    <input type="checkbox" name="required" value="1" class="mt-0.5 shrink-0"
                        @checked(old('required', $group->required))>
                    <span>
                        <span class="font-medium">Obowiązkowa</span>
                        <span class="mt-0.5 block text-xs text-stone-500">
                            Bez jej wypełnienia klient nie doda produktu do koszyka. Zostaw wyłączone, jeśli
                            personalizacja jest dodatkiem — wymuszona na starcie zamienia zwykły zakup w formularz.
                        </span>
                    </span>
                </label>
            </div>

            <button type="submit"
                class="inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                {{ $group->exists ? 'Zapisz zmiany' : 'Utwórz grupę' }}
            </button>
        </div>

        <aside class="lg:col-span-4">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to zobaczy klient</h2>
                @if ($group->exists)
                    <p class="mt-2 text-sm leading-relaxed text-stone-500">
                        Nad {{ $group->isText() ? 'polami' : 'siatką z podglądem' }} pokaże się nazwa grupy
                        @if ($group->hint)
                            i Twoja podpowiedź.
                        @else
                            — możesz dodać pod nią jedno zdanie wyjaśnienia.
                        @endif
                    </p>
                    @if ((float) $group->surcharge_gross > 0)
                        <p class="mt-3 text-sm leading-relaxed text-stone-500">
                            Skorzystanie z tej grupy doda do ceny
                            <span class="font-medium text-stone-700">{{ \App\Support\Money::pln($group->surcharge_gross) }}</span>,
                            widoczne w rozbiciu jako osobna pozycja.
                        </p>
                    @endif
                @else
                    <p class="mt-2 text-sm leading-relaxed text-stone-500">
                        Najpierw utwórz grupę, potem dodasz do niej {{ 'pola albo pozycje biblioteki' }}.
                        Sama grupa jeszcze nic nie robi — pusta jest pytaniem bez odpowiedzi.
                    </p>
                @endif
            </div>
        </aside>
    </form>

    @if ($group->exists)
        {{-- ZAWARTOŚĆ GRUPY — osobny formularz, bo zapisuje się osobno.

             Wiersze wysyłamy w jednym żądaniu: sprzedawca układa formatkę jak
             listę (poprawia dwa limity, przestawia kolejność, dopisuje trzecie
             pole) i chce zobaczyć efekt raz. Kolejność bierze się z KOLEJNOŚCI
             WIERSZY w formularzu, więc nie ma osobnego pola „pozycja" do
             wypełniania ręcznie. --}}
        <div class="mt-6 grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-8">
                @if ($group->isText())
                    @include('seller.options._fields', ['group' => $group])
                @else
                    @include('seller.options._choices', ['group' => $group, 'licensors' => $licensors])
                @endif
            </div>
        </div>
    @endif
</x-layouts.panel>
