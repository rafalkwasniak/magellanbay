<x-storefront.account-shell :shop="$shop" active="data" heading="Edycja danych" :crumbs="[
    ['label' => $shop->name, 'url' => '/'],
    ['label' => 'Moje konto', 'url' => '/moje-konto'],
    ['label' => 'Edycja danych'],
]">
    <div class="grid gap-6 xl:grid-cols-2">
        {{-- Dane profilu --}}
        <div class="st-card st-border rounded-3xl border p-6">
            <h2 class="font-semibold">Twoje dane</h2>
            <p class="mt-1 text-sm opacity-60">Uzupełniamy nimi kasę przy kolejnych zamówieniach.</p>
            <form method="POST" action="/moje-konto/dane" class="mt-4 space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="name" class="block text-sm opacity-80">Imię</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $customer->name) }}"
                            class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                        @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="surname" class="block text-sm opacity-80">Nazwisko</label>
                        <input type="text" id="surname" name="surname" value="{{ old('surname', $customer->surname) }}"
                            class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                        @error('surname') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label for="phone" class="block text-sm opacity-80">Telefon</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}"
                        class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                    @error('phone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm opacity-80">E-mail (login)</label>
                    <input type="email" value="{{ $customer->email }}" disabled
                        class="st-border mt-1 block w-full cursor-not-allowed rounded-xl border bg-transparent px-3 py-2.5 text-sm opacity-60">
                </div>
                <button type="submit" class="st-btn rounded-xl px-5 py-2.5 text-sm font-semibold transition hover:brightness-95">Zapisz dane</button>
            </form>
        </div>

        {{-- Zmiana hasła --}}
        <div class="st-card st-border rounded-3xl border p-6">
            <h2 class="font-semibold">Zmiana hasła</h2>
            <p class="mt-1 text-sm opacity-60">Podaj obecne hasło i ustaw nowe.</p>
            <form method="POST" action="/moje-konto/haslo" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="current_password" class="block text-sm opacity-80">Obecne hasło</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                        class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                    @error('current_password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="block text-sm opacity-80">Nowe hasło</label>
                    <input type="password" id="password" name="password" autocomplete="new-password"
                        class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                    @error('password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm opacity-80">Powtórz nowe hasło</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"
                        class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                </div>
                <button type="submit" class="st-btn rounded-xl px-5 py-2.5 text-sm font-semibold transition hover:brightness-95">Zmień hasło</button>
            </form>
        </div>
    </div>
</x-storefront.account-shell>
