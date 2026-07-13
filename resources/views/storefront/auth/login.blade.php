<x-layouts.storefront :shop="$shop" title="Zaloguj się">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Logowanie'],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Zaloguj się</h1>

        <div class="st-border mt-8 border-t pt-8">
            <div class="mx-auto max-w-md">
                @if (session('status'))
                    <div class="st-card st-border mb-6 rounded-xl border p-4 text-sm">{{ session('status') }}</div>
                @endif

                <div class="st-card st-border rounded-2xl border p-6">
                    <form method="POST" action="/logowanie" class="space-y-4">
                        @csrf
                        <div>
                            <label for="email" class="block text-sm opacity-80">E-mail</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                                class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm opacity-80">Hasło</label>
                            <input type="password" id="password" name="password" required autocomplete="current-password"
                                class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-center gap-3 text-sm opacity-80">
                            <input type="checkbox" name="remember" value="1" class="st-border h-5 w-5 rounded border" style="accent-color: var(--brand);">
                            Zapamiętaj mnie
                        </label>

                        <button type="submit"
                            class="st-btn w-full rounded-xl px-4 py-3 text-sm font-semibold transition hover:brightness-95">
                            Zaloguj się
                        </button>
                    </form>
                </div>

                <p class="mt-6 text-center text-sm opacity-70">
                    Nie masz konta?
                    <a href="/rejestracja" wire:navigate class="st-brand font-medium underline underline-offset-2">Załóż konto</a>
                </p>
            </div>
        </div>
    </main>
</x-layouts.storefront>
