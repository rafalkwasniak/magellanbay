<x-layouts.storefront :shop="$shop" title="Aktywuj konto" :noindex="true">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Aktywuj konto'],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Aktywuj konto</h1>

        <div class="st-border mt-8 border-t pt-8">
            <div class="mx-auto max-w-md">
                <p class="text-sm leading-relaxed opacity-70">
                    Kończysz zakładanie konta dla <strong class="st-brand">{{ $customer->email }}</strong>.
                    Ustaw hasło — dane poniżej możesz uzupełnić teraz albo później w profilu.
                </p>

                <div class="st-card st-border mt-6 rounded-2xl border p-6">
            <form method="POST" action="{{ $actionUrl }}" class="space-y-4">
                @csrf

                <div>
                    <label for="password" class="block text-sm opacity-80">Hasło</label>
                    <input type="password" id="password" name="password" required autofocus autocomplete="new-password"
                        class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm opacity-80">Powtórz hasło</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                        class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                </div>

                <div class="st-border mt-2 border-t pt-4">
                    <p class="text-xs uppercase tracking-wide opacity-50">Dane (opcjonalnie)</p>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div>
                            <label for="name" class="block text-sm opacity-80">Imię</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $customer->name) }}"
                                class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                        </div>
                        <div>
                            <label for="surname" class="block text-sm opacity-80">Nazwisko</label>
                            <input type="text" id="surname" name="surname" value="{{ old('surname', $customer->surname) }}"
                                class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="phone" class="block text-sm opacity-80">Telefon</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}"
                            class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                    </div>
                </div>

                {{-- Zgoda marketingowa: osobno od danych i od regulaminu, domyślnie
                     niezaznaczona — musi być uprzednia i dobrowolna (art. 10 uśude). --}}
                <div class="st-border mt-2 border-t pt-4">
                    <label for="marketing_email" class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" id="marketing_email" name="marketing_email" value="1"
                            @checked(old('marketing_email'))
                            class="st-border mt-0.5 h-4 w-4 shrink-0 rounded border bg-transparent">
                        <span class="text-sm leading-relaxed opacity-80">
                            {{ config('legal.marketing_consent.text') }}
                            <span class="block text-xs opacity-60">Możesz to zmienić w każdej chwili w swoim profilu.</span>
                        </span>
                    </label>
                </div>

                <button type="submit"
                    class="st-btn w-full rounded-xl px-4 py-3 text-sm font-semibold transition hover:brightness-95">
                    Aktywuj konto
                </button>
            </form>
                </div>
            </div>
        </div>
    </main>
</x-layouts.storefront>
