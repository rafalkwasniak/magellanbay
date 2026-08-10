<x-layouts.panel :title="$mailing ? 'Wiadomość do sprzedawców' : 'Nowa wiadomość'">
    <x-slot:heading>{{ $mailing ? ($mailing->isSent() ? 'Wysłana wiadomość' : 'Szkic wiadomości') : 'Nowa wiadomość' }}</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Potwierdzenia zapisu idą wyłącznie toastem (komponent x-toasts
             w layoucie panelu) — własny blok dublowałby ten sam komunikat. --}}
        <div class="space-y-6 lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                @if ($mailing?->isSent())
                    {{-- Wysłana wiadomość = ZRZUT TEGO, CO POSZŁO. Bez nagłówka
                         „Treść" i bez etykiet: temat stoi tam, gdzie w skrzynce,
                         a pod kreską jest sam mail. Brak formularza mówi sam, że
                         wysłanej się nie edytuje. --}}
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Temat</p>
                    <p class="mt-1 text-lg font-semibold text-stone-900">{{ $mailing->subject }}</p>

                    <div class="mt-5 border-t border-stone-100 pt-5">
                        <div class="legal-content">{!! \App\Support\Prose::render($mailing->body) !!}</div>
                    </div>
                @else
                    <h2 class="font-semibold text-stone-900">Treść</h2>
                    <p class="mt-1 text-sm text-stone-500">
                        Pisz jak w opisie produktu — pogrubienia, listy i odnośniki trafią do skrzynki sprzedawcy tak, jak je widzisz.
                    </p>

                    <form method="POST"
                        action="{{ $mailing ? route('administrator.mailings.update', $mailing) : route('administrator.mailings.store') }}"
                        class="mt-5 space-y-4">
                        @csrf

                        <div>
                            <label for="subject" class="block text-sm font-medium text-stone-700">Temat</label>
                            <input type="text" id="subject" name="subject" maxlength="150" required
                                value="{{ old('subject', $mailing?->subject) }}"
                                placeholder="np. Nowa funkcja w Kramio — kurier pod adres"
                                class="mt-1 block w-full rounded-2xl border border-stone-200 bg-white px-4 py-2.5 text-sm text-stone-800 focus:border-amber-400 focus:outline-none">
                            <p class="mt-1 text-xs text-stone-400">Zobaczą go w skrzynce jako pierwszy — niech mówi, o co chodzi.</p>
                            @error('subject')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="body" class="block text-sm font-medium text-stone-700">Treść</label>
                            <x-rich-editor name="body" :value="old('body', $mailing?->body)" ai-field="mailing_body" :max="config('platform_mail.body_max')">Napisz, co nowego w Kramio — o nowej funkcji, ofercie albo zmianie, która ich dotyczy.</x-rich-editor>
                            @error('body')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit"
                                class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                                {{ $mailing ? 'Zapisz zmiany' : 'Zapisz szkic' }}
                            </button>
                            <a href="{{ route('administrator.mailings.index') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">Wróć do listy</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        {{-- Prawa kolumna: odbiorcy i wysyłka. Oba pojawiają się dopiero przy
             zapisanym szkicu — wybór odbiorców i próbka potrzebują wiadomości,
             która istnieje w bazie. --}}
        <aside class="space-y-6 lg:col-span-4">
            @if ($mailing)
                <livewire:administrator.platform-mailing-recipients :mailing="$mailing" />
                <livewire:administrator.platform-mailing-sender :mailing="$mailing" />
            @endif

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                <ol class="mt-4 space-y-3">
                    @foreach ([
                        ['Napisz treść', 'Zapisz szkic — możesz do niego wracać.'],
                        ['Wybierz odbiorców', 'Domyślnie zaznaczeni są wszyscy ze zgodą.'],
                        ['Sprawdź na sobie', 'Wyślij próbkę na swój adres, tyle razy, ile trzeba.'],
                        ['Wyślij', 'Ten krok jest jednorazowy i nieodwracalny.'],
                    ] as $i => [$title, $desc])
                        <li class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-semibold text-amber-800">{{ $i + 1 }}</span>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-stone-800">{{ $title }}</span>
                                <span class="block text-xs text-stone-500">{{ $desc }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </aside>
    </div>
</x-layouts.panel>
