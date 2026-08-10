{{-- Potwierdzenie wypisu z wiadomości Kramio. Wypis nastąpił już przy wejściu
     na stronę — to ekran potwierdzenia, nie formularz. --}}
<x-layouts.guest :title="($restored ?? false) ? 'Zgoda przywrócona' : 'Wypisano z wiadomości'">
    @if ($restored ?? false)
        <div class="rounded-3xl border border-white/60 bg-white/70 p-8 backdrop-blur">
            <p class="font-semibold text-emerald-700">✓ Zgoda przywrócona</p>
            <p class="mt-2 text-sm text-stone-600">
                Będziesz znów dostawać od nas wiadomości o nowościach i ofertach Kramio.
            </p>
        </div>
    @else
        <div class="rounded-3xl border border-white/60 bg-white/70 p-8 backdrop-blur">
            <p class="font-semibold text-stone-900">Gotowe — to była ostatnia taka wiadomość</p>
            <p class="mt-2 text-sm text-stone-600">
                Nie wyślemy Ci już wiadomości o nowościach ani ofertach Kramio.
            </p>
            {{-- Bez tego zdania wypis czytałby się jak „odcinam sobie wszystko od
                 platformy" — a faktura i wygaśnięcie pakietu idą niezależnie od
                 tej zgody i wyłączyć się nie dadzą. --}}
            <p class="mt-3 text-sm text-stone-500">
                Nadal będziesz dostawać e-maile potrzebne do prowadzenia sklepu — faktury, informacje
                o pakiecie i zmianach w regulaminie. Tych nie da się wyłączyć.
            </p>
        </div>

        {{-- Kliknięcie przez pomyłkę (albo przez skaner poczty) naprawia jeden
             przycisk — dlatego wolno nam wypisywać od razu. --}}
        <form method="POST" action="{{ $restoreUrl }}" class="mt-6 text-center">
            @csrf
            <p class="text-sm text-stone-500">Kliknięcie było pomyłką?</p>
            <button type="submit"
                class="mt-3 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-8 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                Przywróć wiadomości
            </button>
        </form>
    @endif

    <p class="mt-8 text-center text-sm text-stone-500">
        Ustawienia zmienisz też w <a href="{{ route('profile.edit') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">swoim profilu</a>.
    </p>
</x-layouts.guest>
