<x-layouts.storefront :shop="$shop" title="Załóż konto">
    <main class="mx-auto max-w-md px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Załóż konto'],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Załóż konto</h1>
        <p class="mt-3 text-sm leading-relaxed opacity-70">
            Podaj adres e-mail — wyślemy Ci link do ustawienia hasła. Po aktywacji zobaczysz historię
            swoich zamówień i szybciej złożysz kolejne.
        </p>

        <div class="st-card st-border mt-8 rounded-2xl border p-6">
            <form method="POST" action="/rejestracja" class="space-y-4">
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
                    Załóż konto
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-sm opacity-70">
            Masz już konto?
            <a href="/logowanie" wire:navigate class="st-brand font-medium underline underline-offset-2">Zaloguj się</a>
        </p>
    </main>
</x-layouts.storefront>
