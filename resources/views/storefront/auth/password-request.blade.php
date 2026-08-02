<x-layouts.storefront :shop="$shop" title="Nie pamiętam hasła" :noindex="true">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Nie pamiętam hasła'],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Nie pamiętam hasła</h1>

        <div class="st-border mt-8 border-t pt-8">
            <div class="mx-auto max-w-md">
                @if (session('status'))
                    <div class="st-card st-border mb-6 rounded-xl border p-4 text-sm">{{ session('status') }}</div>
                @endif

                <div class="st-card st-border rounded-2xl border p-6">
                    <p class="text-sm opacity-80">
                        Podaj adres e-mail, którym się logujesz. Wyślemy na niego link do ustawienia nowego hasła.
                    </p>

                    <form method="POST" action="/nie-pamietam-hasla" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <label for="email" class="block text-sm opacity-80">E-mail</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                                class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                            class="st-btn w-full rounded-xl px-4 py-3 text-sm font-semibold transition hover:brightness-95">
                            Wyślij link
                        </button>
                    </form>
                </div>

                <p class="mt-6 text-center text-sm opacity-70">
                    Hasło jednak się przypomniało?
                    <a href="/logowanie" wire:navigate class="st-brand font-medium underline underline-offset-2">Wróć do logowania</a>
                </p>
            </div>
        </div>
    </main>
</x-layouts.storefront>
