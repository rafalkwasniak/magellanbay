@props(['items'])

{{-- Pozycje menu panelu. Pozycja z trasą = klikalny link (podświetla aktywną
     stronę); bez trasy = funkcja jeszcze niegotowa — nie-klikalna, wyszarzona,
     z plakietką „wkrótce" (zamiast ślepego linku #). Używane w sidebarze i w
     wysuwanym menu mobilnym, żeby oba miały identyczne zachowanie.
     Podświetlenie: domyślnie dokładna trasa (`route`); dla pozycji z podstronami
     (lista + szczegół/edycja) można podać wzorzec `active` z wildcardem
     (np. `seller.orders.*`), żeby zakładka świeciła też na szczególe. --}}
@foreach ($items as $item)
    @if ($item['route'])
        @php($active = request()->routeIs($item['active'] ?? $item['route']))
        <a href="{{ route($item['route']) }}"
           @class([
               'flex items-center gap-3 rounded-2xl px-4 py-2.5 transition',
               'bg-white font-medium text-stone-900 shadow-sm' => $active,
               'text-stone-500 hover:bg-white/60' => ! $active,
           ])>
            <span class="text-base leading-none">{{ $item['icon'] }}</span>
            <span class="flex-1">{{ $item['label'] }}</span>
            @if (($item['badge'] ?? 0) > 0)
                {{-- Powiadomienie „nowe od Twojej ostatniej wizyty"; dwucyfrowe pokazujemy w całości (16 niesie więcej niż 9+ przy tej samej szerokości), skracamy dopiero „99+". --}}
                <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-emerald-500 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
            @endif
        </a>
    @else
        <span class="flex cursor-default select-none items-center gap-3 rounded-2xl px-4 py-2.5 text-stone-400"
              title="Wkrótce dostępne">
            <span class="text-base leading-none opacity-60">{{ $item['icon'] }}</span>
            <span class="flex-1">{{ $item['label'] }}</span>
            <span class="rounded-full bg-stone-200/70 px-2 py-0.5 text-[10px] font-medium text-stone-500">wkrótce</span>
        </span>
    @endif
@endforeach

{{-- Ciasteczka — ZAWSZE ostatnia pozycja, poza tablicą `$nav`. Nie jest linkiem
     do podstrony, tylko formularzem czyszczącym decyzję (przywraca baner), więc
     nie mieści się w strukturze pozostałych wpisów. Stoi tutaj, a nie w obu
     menu osobno, żeby sidebar i wysuwane menu mobilne nie mogły się rozjechać. --}}
<form method="POST" action="{{ route('cookies.store') }}">
    @csrf
    <button type="submit" name="decision" value="reset"
        class="flex w-full items-center gap-3 rounded-2xl px-4 py-2.5 text-left text-stone-500 transition hover:bg-white/60">
        <span class="text-base leading-none">🍪</span>
        <span class="flex-1">Ciasteczka</span>
    </button>
</form>
