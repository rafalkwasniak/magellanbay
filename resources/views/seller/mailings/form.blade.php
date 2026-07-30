<x-layouts.panel :title="$mailing ? 'Wiadomość do klientów' : 'Nowa wiadomość'">
    <x-slot:heading>{{ $mailing ? ($mailing->isSent() ? 'Wysłana wiadomość' : 'Szkic wiadomości') : 'Nowa wiadomość' }}</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Potwierdzenia zapisu idą wyłącznie toastem (`success` = zielony,
             komponent x-toasts w layoucie panelu) — własny blok nad formularzem
             dublowałby ten sam komunikat w dwóch miejscach. --}}
        <div class="space-y-6 lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                @if ($mailing?->isSent())
                    {{-- Wysłana wiadomość = ZRZUT TEGO, CO POSZŁO do klientów. Bez
                         nagłówka „Treść" i bez etykiet nad ciałem: temat stoi tam,
                         gdzie w skrzynce (na górze), a pod kreską jest sam mail.
                         Wysłanej nie edytujemy — brak formularza mówi to sam. --}}
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Temat</p>
                    <p class="mt-1 text-lg font-semibold text-stone-900">{{ $mailing->subject }}</p>

                    @if ($mailing->product !== null)
                        <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">
                            <span aria-hidden="true">🏷️</span> Promowany produkt: {{ $mailing->product->name }}
                        </p>
                    @endif

                    {{-- Ciało maila: sanitizer na zapisie, Prose na wyjściu — ten sam
                         układ, który zobaczył klient. `legal-content` = gotowa
                         typografia prozy w panelu (jest w zbudowanym CSS). --}}
                    <div class="mt-5 border-t border-stone-100 pt-5">
                        <div class="legal-content">{!! \App\Support\Prose::render($mailing->body) !!}</div>
                    </div>
                @else
                    <h2 class="font-semibold text-stone-900">Treść</h2>
                    <p class="mt-1 text-sm text-stone-500">
                        Pisz jak w opisie produktu — pogrubienia, listy i odnośniki trafią do skrzynki klienta tak, jak je widzisz.
                    </p>

                    <form method="POST"
                        action="{{ $mailing ? route('seller.mailings.update', $mailing) : route('seller.mailings.store') }}"
                        class="mt-5 space-y-4">
                        @csrf

                        <div>
                            <label for="subject" class="block text-sm font-medium text-stone-700">Temat</label>
                            <input type="text" id="subject" name="subject" maxlength="150" required
                                value="{{ old('subject', $mailing?->subject) }}"
                                placeholder="np. Nowa książka już w sprzedaży"
                                class="mt-1 block w-full rounded-2xl border border-stone-200 bg-white px-4 py-2.5 text-sm text-stone-800 focus:border-amber-400 focus:outline-none">
                            <p class="mt-1 text-xs text-stone-400">Zobaczą go w skrzynce jako pierwszy — niech mówi, o co chodzi.</p>
                            @error('subject')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="body" class="block text-sm font-medium text-stone-700">Treść</label>
                            <x-rich-editor name="body" :value="old('body', $mailing?->body)" ai-field="mailing_body" :max="config('bulk_mail.body_max')">Napisz, co nowego u Ciebie — o nowym produkcie, promocji albo zmianie w sklepie.</x-rich-editor>
                            @error('body')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Promowany produkt: jego karta (zdjęcie, cena, zajawka,
                             przycisk) stanie POD treścią maila. Opcjonalny — nie
                             każda wiadomość dotyczy konkretnej rzeczy. --}}
                        <div>
                            <label for="product_id" class="block text-sm font-medium text-stone-700">Promowany produkt <span class="text-stone-400">— opcjonalnie</span></label>
                            <select id="product_id" name="product_id"
                                class="mt-1 block w-full rounded-2xl border border-stone-200 bg-white px-4 py-2.5 text-sm text-stone-800 focus:border-amber-400 focus:outline-none">
                                <option value="">Bez produktu — sama treść</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected((int) old('product_id', $mailing?->product_id) === $product->id)>
                                        {{ $product->name }} — {{ \App\Support\Money::pln($product->price_gross) }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-stone-400">
                                Pod treścią pojawi się karta produktu ze zdjęciem, ceną i przyciskiem do sklepu.
                                Przy obniżce dopiszemy starą cenę i najniższą z 30 dni.
                            </p>
                            @error('product_id')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit"
                                class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                                {{ $mailing ? 'Zapisz zmiany' : 'Zapisz szkic' }}
                            </button>
                            <a href="{{ route('seller.mailings.index') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">Wróć do listy</a>
                        </div>
                    </form>
                @endif
            </div>

            {{-- Wysyłka dopiero przy zapisanym szkicu: próbka potrzebuje treści,
                 którą da się pobrać z bazy. --}}
            {{-- Usuwanie mieszka na LIŚCIE wiadomości (jak przy kodach rabatowych),
                 a nie tutaj: edycja ma służyć pisaniu i wysyłce, a nie kasowaniu. --}}
        </div>

        {{-- Prawa kolumna: wysyłka (stan i akcje) + instrukcja. Sender stał wcześniej
             pod treścią, gdzie ginął, a kolumna boczna świeciła pustką. --}}
        <aside class="space-y-6 lg:col-span-4">
            @if ($mailing)
                <livewire:seller.bulk-mailing-sender :mailing="$mailing" />
            @endif

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                <ol class="mt-4 space-y-3">
                    @foreach ([
                        ['Napisz treść', 'Zapisz szkic — możesz do niego wracać.'],
                        ['Sprawdź na sobie', 'Wyślij próbkę na swój adres, tyle razy, ile trzeba.'],
                        ['Wyślij do klientów', 'Ten krok jest jednorazowy i nieodwracalny.'],
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

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Kto dostanie wiadomość</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-600">
                    @foreach ([
                        ['👥', 'Tylko klienci Twojego sklepu, którzy zaznaczyli zgodę na wiadomości.'],
                        ['↩', 'W stopce każdej wiadomości jest link do wypisania się — wymaga tego prawo i działa natychmiast.'],
                        ['📬', 'Maile o zamówieniach idą niezależnie od tej zgody.'],
                    ] as [$icon, $text])
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 shrink-0" aria-hidden="true">{{ $icon }}</span>
                            <span class="min-w-0">{{ $text }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
