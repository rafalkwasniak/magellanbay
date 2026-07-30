<x-layouts.panel title="Mój profil">
    <x-slot:heading>Mój profil</x-slot:heading>

    @php($avatarUrl = $user->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path) : null)

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: formularz --}}
        <div class="lg:col-span-8">
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-6" enctype="multipart/form-data" novalidate data-validate>
                @csrf

                {{-- Dane osobowe --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Dane osobowe</h2>
                    <p class="mt-1 text-sm text-stone-500">Twoje dane konta w panelu {{ config('app.name') }}.</p>

                    <div class="mt-6 space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="name" class="block text-sm font-medium text-stone-700">Imię</label>
                                <input id="name" name="name" type="text" required autocomplete="given-name"
                                    value="{{ old('name', $user->name) }}"
                                    data-msg-required="Podaj imię."
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('name')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="surname" class="block text-sm font-medium text-stone-700">Nazwisko</label>
                                <input id="surname" name="surname" type="text" required autocomplete="family-name"
                                    value="{{ old('surname', $user->surname) }}"
                                    data-msg-required="Podaj nazwisko."
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('surname')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="email" class="block text-sm font-medium text-stone-700">Adres e-mail</label>
                                <input id="email" name="email" type="email" required autocomplete="email"
                                    value="{{ old('email', $user->email) }}"
                                    data-msg-required="Podaj adres e-mail."
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                <p class="mt-1.5 text-xs text-stone-400">To również Twój login do panelu.</p>
                                @error('email')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-stone-700">Telefon <span class="text-stone-400">(opcjonalnie)</span></label>
                                <input id="phone" name="phone" type="tel" autocomplete="tel"
                                    value="{{ old('phone', $user->phone) }}"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('phone')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Awatar (osobny box, analogicznie do logo sklepu) --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Awatar</h2>
                    <p class="mt-1 text-sm text-stone-500">Twoje zdjęcie profilowe — pokazujemy je w panelu {{ config('app.name') }}.</p>

                    <div class="mt-6">
                        <label for="avatar" class="block text-sm font-medium text-stone-700">Zdjęcie <span class="text-stone-400">(opcjonalnie)</span></label>
                        <div class="mt-1.5 flex items-center gap-4">
                            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full border border-stone-200 bg-stone-100">
                                <img id="avatar-preview"
                                    src="{{ $avatarUrl ?? '' }}"
                                    alt="Awatar"
                                    class="h-full w-full object-cover {{ $avatarUrl ? '' : 'hidden' }}">
                                <span id="avatar-placeholder" class="text-lg font-semibold text-stone-500 {{ $avatarUrl ? 'hidden' : '' }}">
                                    {{ strtoupper(mb_substr($user->name, 0, 1).mb_substr($user->surname ?? '', 0, 1)) }}
                                </span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <input id="avatar" name="avatar" type="file" accept="image/png,image/jpeg,image/webp"
                                    class="block w-full text-sm text-stone-500 file:mr-4 file:rounded-xl file:border-0 file:bg-amber-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-amber-800 file:transition hover:file:bg-amber-200">
                                <p class="mt-1.5 text-xs text-stone-400">PNG, JPG lub WebP, do 2 MB. Najlepiej kwadratowe.</p>
                                @if ($user->avatar_path)
                                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-stone-600">
                                        <input type="checkbox" name="remove_avatar" value="1" class="shrink-0">
                                        <span>Usuń obecny awatar</span>
                                    </label>
                                @endif
                            </div>
                        </div>
                        @error('avatar')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Zgoda na informacje handlowe od Kramio. Odwoływalna w każdej
                     chwili — zgoda musi dać się wycofać tak łatwo, jak udzielić
                     (RODO art. 7 ust. 3). Maile o fakturach i pakiecie idą
                     niezależnie od niej, więc mówimy o tym wprost. --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Wiadomości od Kramio</h2>
                    <p class="mt-1 text-sm text-stone-500">Nowości w platformie, oferty i kody rabatowe.</p>

                    <label class="mt-4 flex items-start gap-3 text-sm text-stone-600">
                        <input type="checkbox" name="marketing" value="1"
                            @checked(old('marketing', $user->hasMarketingConsent())) class="mt-0.5 shrink-0">
                        <span>{{ config('legal.seller_marketing_consent.text') }}</span>
                    </label>

                    <p class="mt-3 text-xs text-stone-400">
                        Niezależnie od tej zgody wysyłamy wiadomości niezbędne do obsługi konta — faktury za pakiet,
                        informacje o terminie abonamentu i ważne komunikaty techniczne.
                    </p>
                </div>

                {{-- Zmiana hasła --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Zmiana hasła <span class="text-sm font-normal text-stone-400">(opcjonalnie)</span></h2>
                    <p class="mt-1 text-sm text-stone-500">Zostaw puste, jeśli nie chcesz zmieniać hasła.</p>

                    <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-3">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-stone-700">Aktualne hasło</label>
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('current_password')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-stone-700">Nowe hasło</label>
                            <input id="password" name="password" type="password" autocomplete="new-password"
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <p class="mt-1.5 text-xs text-stone-400">Min. 8 znaków, w tym duża i mała litera oraz cyfra.</p>
                            @error('password')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-stone-700">Powtórz nowe hasło</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                                data-match="password" data-msg-match="Hasła muszą być takie same."
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        Zapisz profil
                    </button>
                </div>
            </form>
        </div>

        {{-- Kolumna pomocnicza --}}
        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Twoje konto</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔒</span>
                        <span>Aby zmienić hasło, podaj aktualne — to chroni Twoje konto.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✉️</span>
                        <span>Adres e-mail jest Twoim loginem — po zmianie loguj się nowym adresem.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🖼️</span>
                        <span>Najlepszy awatar to kwadrat (np. 512×512 px) — pokazujemy go w panelu.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>

    {{-- Podgląd awatara na żywo (zero zależności). --}}
    <script>
        (function () {
            const input = document.getElementById('avatar');
            const preview = document.getElementById('avatar-preview');
            const placeholder = document.getElementById('avatar-placeholder');
            if (!input || !preview) return;

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                if (!file) return;
                preview.src = URL.createObjectURL(file);
                preview.classList.remove('hidden');
                if (placeholder) placeholder.classList.add('hidden');
            });
        })();
    </script>
</x-layouts.panel>
