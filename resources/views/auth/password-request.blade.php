<x-layouts.guest title="Odzyskiwanie hasła">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl">
        <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Nie pamiętasz hasła?</h1>
        <p class="mt-2 text-stone-500">Podaj adres e-mail, którym się logujesz. Wyślemy na niego link do ustawienia nowego hasła.</p>

        @if (session('status'))
            <div class="mt-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-stone-700">Adres e-mail</label>
                <input id="email" name="email" type="email" autocomplete="username" required autofocus
                    value="{{ old('email') }}"
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('email')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                Wyślij link
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-stone-500">
            Hasło jednak się przypomniało?
            <a href="{{ route('login') }}" class="font-semibold text-amber-700 hover:text-amber-800">Wróć do logowania</a>
        </p>
    </div>
</x-layouts.guest>
