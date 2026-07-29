<x-layouts.panel :title="$mailing ? 'Wiadomość do klientów' : 'Nowa wiadomość'">
    <x-slot:heading>{{ $mailing ? ($mailing->isSent() ? 'Wysłana wiadomość' : 'Szkic wiadomości') : 'Nowa wiadomość' }}</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Potwierdzenia zapisu idą wyłącznie toastem (`success` = zielony,
             komponent x-toasts w layoucie panelu) — własny blok nad formularzem
             dublowałby ten sam komunikat w dwóch miejscach. --}}
        <div class="space-y-6 lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Treść</h2>

                @if ($mailing?->isSent())
                    {{-- Wysłanej wiadomości nie edytujemy — klienci mają ją w skrzynkach,
                         więc zapis musi zostać zgodny z tym, co dostali. --}}
                    <p class="mt-1 text-sm text-stone-500">Ta wiadomość została już wysłana, więc jest tylko do odczytu.</p>

                    <div class="mt-5">
                        <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Temat</p>
                        <p class="mt-1 font-medium text-stone-900">{{ $mailing->subject }}</p>
                    </div>
                    @if ($mailing->product !== null)
                        <div class="mt-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Promowany produkt</p>
                            <p class="mt-1 font-medium text-stone-900">{{ $mailing->product->name }}</p>
                        </div>
                    @endif

                    <div class="mt-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Treść</p>
                        {{-- Ten sam układ co w mailu: sanitizer na zapisie, Prose na wyjściu. --}}
                        {{-- `legal-content` = gotowa typografia prozy w panelu (jest
                             w zbudowanym CSS); własna klasa wymagałaby builda. --}}
                        <div class="legal-content mt-1">{!! \App\Support\Prose::render($mailing->body) !!}</div>
                    </div>
                @else
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
            @if ($mailing)
                <livewire:seller.bulk-mailing-sender :mailing="$mailing" />
            @endif
        </div>

        <aside class="space-y-6 lg:col-span-4">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                <ol class="mt-3 space-y-2 text-sm text-stone-600">
                    <li>1. Napisz treść i zapisz szkic.</li>
                    <li>2. Wyślij próbkę do siebie — tyle razy, ile trzeba.</li>
                    <li>3. Gdy wygląda dobrze, wyślij ją do klientów. Ten krok jest jednorazowy.</li>
                </ol>
                <p class="mt-4 text-xs text-stone-400">
                    Wiadomość trafia wyłącznie do klientów Twojego sklepu, którzy zaznaczyli zgodę. W stopce każdej z nich
                    jest link do wypisania się — wymaga tego prawo, a działa natychmiast.
                </p>
            </div>
        </aside>
    </div>
</x-layouts.panel>
