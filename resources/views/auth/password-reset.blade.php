<x-layouts.guest title="Nowe hasło">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl">
        <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Ustaw nowe hasło</h1>
        <p class="mt-2 text-stone-500">Wpisz hasło, którym będziesz się od teraz logować.</p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5" novalidate data-validate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="block text-sm font-medium text-stone-700">Adres e-mail</label>
                <input id="email" name="email" type="email" autocomplete="username" required
                    value="{{ old('email', $email) }}"
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('email')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-stone-700">Nowe hasło</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required autofocus
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                <p class="mt-1.5 text-xs text-stone-500">Co najmniej 8 znaków, mała i duża litera oraz cyfra.</p>
                @error('password')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-stone-700">Powtórz hasło</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
            </div>

            <button type="submit"
                class="w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                Zapisz nowe hasło
            </button>
        </form>
    </div>
</x-layouts.guest>
