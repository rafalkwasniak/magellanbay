<x-layouts.storefront :shop="$shop" title="Nowe hasło" :noindex="true">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Nowe hasło'],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Ustaw nowe hasło</h1>

        <div class="st-border mt-8 border-t pt-8">
            <div class="mx-auto max-w-md">
                <div class="st-card st-border rounded-2xl border p-6">
                    <p class="text-sm opacity-80">
                        Konto: <span class="font-medium">{{ $customer->email }}</span>
                    </p>

                    <form method="POST" action="{{ $actionUrl }}" class="mt-4 space-y-4" novalidate data-validate>
                        @csrf
                        <div>
                            <label for="password" class="block text-sm opacity-80">Nowe hasło</label>
                            <input type="password" id="password" name="password" required autofocus autocomplete="new-password"
                                class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                            <p class="mt-1 text-xs opacity-60">Co najmniej 8 znaków, mała i duża litera oraz cyfra.</p>
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm opacity-80">Powtórz hasło</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                                class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                        </div>

                        <button type="submit"
                            class="st-btn w-full rounded-xl px-4 py-3 text-sm font-semibold transition hover:brightness-95">
                            Zapisz nowe hasło
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</x-layouts.storefront>
