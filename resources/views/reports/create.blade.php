<x-layouts.public title="Zgłoś nielegalną treść">
    <article class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl sm:p-10">
        <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Zgłoś nielegalną treść</h1>
        <p class="mt-2 text-stone-500">
            Sklepy w Kramio prowadzą niezależni sprzedawcy, a my udostępniamy im narzędzie i miejsce.
            Jeśli w którymś sklepie widzisz treść, która narusza prawo, napisz nam o tym tutaj — sprawdzimy zgłoszenie
            i poinformujemy Cię o rozstrzygnięciu.
        </p>

        {{-- Potwierdzenie wysyłki pokazuje toast z layoutu (`x-toasts`), nie
             formularz. Dwa komunikaty o tym samym to szum. --}}
        <form method="POST" action="{{ route('reports.store') }}" class="mt-8 space-y-5" novalidate data-validate>
            @csrf

            {{-- Pułapka na boty — wzorzec z rejestracji. Styl wpisany w znacznik,
                 nie w klasach Tailwinda: klasa, której zabrakłoby w zbudowanym CSS,
                 po cichu nic nie robi i pole wyskoczyłoby ludziom w formularzu. --}}
            <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
                <label for="website">Nie wypełniaj tego pola</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off" value="">
            </div>

            @error('website')
                <p class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
            @enderror

            <div>
                <label for="url" class="block text-sm font-medium text-stone-700">Adres zgłaszanej strony</label>
                <p class="mt-0.5 text-xs text-stone-500">Wklej pełny adres, razem z „https://" — im dokładniej, tym szybciej znajdziemy treść.</p>
                <input id="url" name="url" type="url" required value="{{ old('url', $prefilledUrl) }}"
                    placeholder="https://nazwa-sklepu.{{ config('tenancy.central_domain') }}/produkt/12-nazwa"
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('url')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="category" class="block text-sm font-medium text-stone-700">Czego dotyczy zgłoszenie</label>
                <select id="category" name="category" required
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    <option value="">— wybierz —</option>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('category')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="justification" class="block text-sm font-medium text-stone-700">Dlaczego uważasz tę treść za bezprawną</label>
                <p class="mt-0.5 text-xs text-stone-500">Napisz własnymi słowami. Jeśli chodzi o Twoje prawa (znak towarowy, zdjęcie, tekst), napisz też, skąd wynikają.</p>
                <textarea id="justification" name="justification" rows="6" required
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">{{ old('justification') }}</textarea>
                @error('justification')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="reporter_email" class="block text-sm font-medium text-stone-700">Twój adres e-mail</label>
                <p class="mt-0.5 text-xs text-stone-500">Potrzebny, żeby wysłać Ci potwierdzenie i rozstrzygnięcie.</p>
                <input id="reporter_email" name="reporter_email" type="email" required value="{{ old('reporter_email') }}"
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('reporter_email')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="reporter_name" class="block text-sm font-medium text-stone-700">Imię i nazwisko lub nazwa <span class="font-normal text-stone-400">— nieobowiązkowe</span></label>
                <input id="reporter_name" name="reporter_name" type="text" value="{{ old('reporter_name') }}"
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('reporter_name')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                <label class="flex items-start gap-3 text-xs leading-relaxed text-amber-900">
                    <input type="checkbox" name="good_faith" value="1" class="mt-0.5 shrink-0" required
                        data-msg-required="Potwierdź, że zgłoszenie składasz w dobrej wierze."
                        @checked(old('good_faith'))>
                    <span>
                        Oświadczam, że informacje w tym zgłoszeniu są, według mojej najlepszej wiedzy,
                        <span class="font-semibold">prawidłowe i kompletne</span>, a zgłoszenie składam w dobrej wierze.
                    </span>
                </label>
                @error('good_faith')
                    <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                Wyślij zgłoszenie
            </button>
        </form>

        <p class="mt-8 text-xs leading-relaxed text-stone-500">
            Zgłoszenie rozpatruje {{ config('company.name') }} jako operator Kramio. Dane z formularza przetwarzamy
            wyłącznie po to, żeby je rozpatrzyć i odpowiedzieć — zasady opisuje
            <a href="{{ route(\App\Enums\LegalDocumentType::Privacy->routeName()) }}" class="underline underline-offset-2">Polityka Prywatności</a>.
            Reklamacje dotyczące zakupów kieruj do sprzedawcy prowadzącego dany sklep, nie tutaj.
        </p>
    </article>
</x-layouts.public>
