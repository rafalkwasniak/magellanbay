<x-layouts.panel :title="$licensor->exists ? 'Partner: '.$licensor->name : 'Nowy partner'">
    <a href="{{ route('seller.licensors.index') }}" class="text-sm font-medium text-stone-500 underline-offset-2 hover:underline">← Wszyscy partnerzy</a>

    <form method="POST"
        action="{{ $licensor->exists ? route('seller.licensors.update', $licensor) : route('seller.licensors.store') }}"
        class="mt-4 grid gap-6 lg:grid-cols-12" novalidate data-validate>
        @csrf

        <div class="lg:col-span-8 space-y-6">
            <div class="space-y-5 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div>
                    <label for="name" class="block text-sm font-medium text-stone-700">Nazwa firmy</label>
                    <p class="mt-0.5 text-xs text-stone-500">Ta nazwa pojawi się w rozliczeniu i w rozbiciu ceny widocznym dla kupującego.</p>
                    <input id="name" name="name" type="text" required value="{{ old('name', $licensor->name) }}"
                        data-msg-required="Podaj nazwę firmy — to ona pojawi się w rozliczeniu."
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    @error('name')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="agreement_reference" class="block text-sm font-medium text-stone-700">Numer umowy licencyjnej</label>
                    {{-- Przy sporze to pierwsza rzecz, o którą pyta partner:
                         „na jakiej podstawie użyliście naszego logo". --}}
                    <p class="mt-0.5 text-xs text-stone-500">Nieobowiązkowy, ale przy sporze to pierwsza rzecz, o którą partner zapyta.</p>
                    <input id="agreement_reference" name="agreement_reference" type="text"
                        value="{{ old('agreement_reference', $licensor->agreement_reference) }}"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    @error('agreement_reference')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="contact_person" class="block text-sm font-medium text-stone-700">Osoba kontaktowa</label>
                        <input id="contact_person" name="contact_person" type="text"
                            value="{{ old('contact_person', $licensor->contact_person) }}"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        @error('contact_person')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="contact_email" class="block text-sm font-medium text-stone-700">E-mail</label>
                        <input id="contact_email" name="contact_email" type="email"
                            value="{{ old('contact_email', $licensor->contact_email) }}"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        @error('contact_email')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-stone-700">Notatki</label>
                    <p class="mt-0.5 text-xs text-stone-500">Widoczne tylko dla Ciebie — warunki, terminy rozliczeń, ustalenia z rozmowy.</p>
                    <textarea id="notes" name="notes" rows="4"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">{{ old('notes', $licensor->notes) }}</textarea>
                    @error('notes')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-start gap-3 text-sm text-stone-700">
                    <input type="checkbox" name="is_active" value="1" class="mt-0.5 shrink-0"
                        @checked(old('is_active', $licensor->is_active ?? true))>
                    <span>
                        <span class="font-medium">Aktywny</span>
                        <span class="mt-0.5 block text-xs text-stone-500">
                            Wygaszony partner znika z wyboru przy produktach i grafikach, ale zostaje w rozliczeniach.
                        </span>
                    </span>
                </label>
            </div>

            <button type="submit"
                class="inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                {{ $licensor->exists ? 'Zapisz zmiany' : 'Dodaj partnera' }}
            </button>
        </div>

        <aside class="lg:col-span-4">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak liczy się opłata</h2>
                <p class="mt-2 text-sm leading-relaxed text-stone-500">
                    Kwotę ustawiasz osobno przy <span class="text-stone-700">produkcie</span> (logotyp na awersie)
                    i przy <span class="text-stone-700">grafice graweru</span>. Tutaj definiujesz tylko, KTO ją dostaje.
                </p>
                <p class="mt-3 text-sm leading-relaxed text-stone-500">
                    Jeśli oba znaki należą do <span class="font-medium text-stone-700">tej samej firmy</span>,
                    kupujący płaci raz — wyższą z dwóch kwot. Znaki różnych firm sumują się normalnie.
                </p>
            </div>
        </aside>
    </form>
</x-layouts.panel>
