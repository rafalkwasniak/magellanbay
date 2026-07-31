@php($centralDomain = config('tenancy.central_domain'))
<x-layouts.guest title="Załóż sklep">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl">
        <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Załóż sklep w 15 minut</h1>
        <p class="mt-2 text-stone-500">Utwórz konto sprzedawcy i zacznij sprzedawać jeszcze dziś.</p>

        <form method="POST" action="{{ route('register.store') }}" class="mt-8 space-y-5" novalidate data-validate>
            @csrf

            {{-- Nazwa sklepu + jego adres (subdomena). Adres tworzymy z nazwy; pole
                 adresu jest tylko podglądem, a o jego dostępności decyduje walidacja. --}}
            <div>
                <label for="shop_name" class="block text-sm font-medium text-stone-700">Nazwa i adres sklepu</label>
                <p class="mt-0.5 text-xs text-stone-500">Adres sklepu utworzymy z nazwy — sprawdź, czy jest wolny.</p>
                <div class="mt-1.5 space-y-2">
                    <input id="shop_name" name="shop_name" type="text" required autofocus
                        value="{{ old('shop_name') }}" placeholder="np. Mój nowy sklep"
                        class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    <div class="flex w-full items-center overflow-hidden rounded-2xl border border-stone-200 bg-stone-100 px-4 py-3 text-sm"
                        title="Adres sklepu — tworzony automatycznie z nazwy">
                        <span id="shop-slug-preview" data-placeholder="moj-nowy-sklep"
                            class="truncate font-medium text-stone-700">{{ old('slug') }}</span><span class="whitespace-nowrap text-stone-400">.{{ $centralDomain }}</span>
                    </div>
                </div>
                @error('shop_name')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
                @error('slug')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-stone-700">Imię</label>
                    <input id="name" name="name" type="text" autocomplete="given-name" required
                        value="{{ old('name') }}"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    @error('name')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="surname" class="block text-sm font-medium text-stone-700">Nazwisko</label>
                    <input id="surname" name="surname" type="text" autocomplete="family-name" required
                        value="{{ old('surname') }}"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    @error('surname')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-stone-700">Adres e-mail</label>
                <input id="email" name="email" type="email" autocomplete="username" required
                    value="{{ old('email') }}"
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('email')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-3 pt-1">
                <label class="flex items-center gap-3 text-sm text-stone-600">
                    <input type="checkbox" name="terms" value="1" required
                        data-msg-required="Aby założyć konto, zaakceptuj Regulamin."
                        @checked(old('terms')) class="shrink-0">
                    <span>
                        Akceptuję
                        <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener"
                            class="font-semibold text-amber-700 hover:text-amber-800">Regulamin</a>.
                    </span>
                </label>
                @error('terms')
                    <p class="text-sm text-rose-600">{{ $message }}</p>
                @enderror

                <label class="flex items-center gap-3 text-sm text-stone-600">
                    <input type="checkbox" name="privacy" value="1" required
                        data-msg-required="Aby założyć konto, zaakceptuj Politykę Prywatności."
                        @checked(old('privacy')) class="shrink-0">
                    <span>
                        Akceptuję
                        <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener"
                            class="font-semibold text-amber-700 hover:text-amber-800">Politykę Prywatności</a>.
                    </span>
                </label>
                @error('privacy')
                    <p class="text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Zgody marketingowej TU NIE MA świadomie — zbieramy ją na ekranie
                 AKTYWACJI konta, tak samo jak u klientów sklepu. Tam adres jest
                 już potwierdzony kliknięciem w link z własnej skrzynki, więc
                 zgoda ma mocniejszy dowód, a rejestracja zostaje krótka. --}}
            <button type="submit"
                class="w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                Załóż konto
            </button>
        </form>
    </div>

    <p class="mt-6 text-center text-sm text-stone-500">
        Masz już konto?
        <a href="{{ route('login') }}" class="font-semibold text-amber-700 hover:text-amber-800">Zaloguj się</a>
    </p>

    {{-- Podgląd subdomeny na żywo. Tylko kosmetyka — slug i jego dostępność
         liczy serwer (SlugService + walidacja). Lustro Str::slug dla PL znaków. --}}
    <script>
        (function () {
            const nameInput = document.getElementById('shop_name');
            const preview = document.getElementById('shop-slug-preview');
            if (!nameInput || !preview) return;

            const placeholder = preview.dataset.placeholder || 'moj-nowy-sklep';
            const pl = { 'ą': 'a', 'ć': 'c', 'ę': 'e', 'ł': 'l', 'ń': 'n', 'ó': 'o', 'ś': 's', 'ź': 'z', 'ż': 'z' };

            function slugify(value) {
                return value.toLowerCase()
                    .replace(/[ąćęłńóśźż]/g, (c) => pl[c] || c)
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    .slice(0, 63)
                    .replace(/-+$/, '');
            }

            function render() {
                const slug = slugify(nameInput.value);
                preview.textContent = slug || placeholder;
                preview.classList.toggle('text-stone-400', !slug);
                preview.classList.toggle('text-stone-700', !!slug);
            }

            nameInput.addEventListener('input', render);
            nameInput.addEventListener('blur', render);
            render();
        })();
    </script>
</x-layouts.guest>
