<div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold text-stone-900">Odbiorcy</h2>
        <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-600">
            {{ $selectedCount }} z {{ $eligibleCount }}
        </span>
    </div>

    @if ($mailing->isSent())
        {{-- Po wysyłce lista jest zamknięta: wiadomość poszła do konkretnych
             ludzi i zapis ma to odzwierciedlać. --}}
        <p class="mt-3 text-sm text-stone-500">
            Wiadomość poszła do {{ $mailing->recipients_count }}
            {{ $mailing->recipients_count === 1 ? 'sprzedawcy' : 'sprzedawców' }}. Listy nie da się już zmienić.
        </p>
    @elseif ($eligibleCount === 0)
        <p class="mt-3 rounded-2xl border border-stone-200 bg-stone-50 p-4 text-sm text-stone-600">
            Żaden sprzedawca nie zgodził się jeszcze na wiadomości handlowe od Kramio.
            Zgodę zaznaczają przy aktywacji konta albo w swoim profilu — nie da się jej dodać za nich.
        </p>
    @else
        <p class="mt-1 text-sm text-stone-500">
            Zaznacz, do kogo ma pójść ta wiadomość. Na liście są wyłącznie sprzedawcy ze zgodą na treści handlowe.
        </p>

        <div class="mt-4">
            <label for="recipient-search" class="sr-only">Szukaj sprzedawcy</label>
            <input type="search" id="recipient-search" wire:model.live.debounce.300ms="search"
                placeholder="Szukaj po nazwisku lub adresie"
                class="block w-full rounded-2xl border border-stone-200 bg-white px-4 py-2.5 text-sm text-stone-800 transition focus:border-amber-400 focus:outline-none">
        </div>

        {{-- Przyciski działają na to, CO WIDAĆ. Przy wpisanej frazie mówią
             „znalezionych", żeby nikt nie skasował wyboru spoza wyników. --}}
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="button" wire:click="selectVisible"
                class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:bg-stone-100">
                {{ $searching ? 'Zaznacz znalezionych ('.$people->count().')' : 'Zaznacz wszystkich' }}
            </button>
            <button type="button" wire:click="deselectVisible"
                class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:bg-stone-100">
                {{ $searching ? 'Odznacz znalezionych' : 'Odznacz wszystkich' }}
            </button>
        </div>

        @if ($people->isEmpty())
            <p class="mt-4 rounded-2xl border border-dashed border-stone-300 px-4 py-6 text-center text-sm text-stone-500">
                Nikt nie pasuje do frazy „{{ $search }}”.
            </p>
        @else
            {{-- Wysokość i przewijanie inline: to jednorazowy wymiar spoza skali
                 Tailwinda, jak `min-width` w liście sklepów. --}}
            <ul class="mt-4 divide-y divide-stone-100 rounded-2xl border border-stone-200 bg-white"
                style="max-height: 22rem; overflow-y: auto">
                @foreach ($people as $person)
                    <li class="flex items-center gap-3 px-4 py-3">
                        <input type="checkbox" id="recipient-{{ $person->id }}"
                            value="{{ $person->id }}" wire:model.live="selected"
                            class="h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20">
                        <label for="recipient-{{ $person->id }}" class="min-w-0 flex-1 cursor-pointer">
                            <span class="block truncate text-sm font-medium text-stone-800">{{ trim($person->name.' '.$person->surname) }}</span>
                            {{-- Nazwa sklepu przed adresem: administrator kojarzy
                                 sprzedawcę po tym, co sprzedaje, szybciej niż po
                                 nazwisku. Szukajka też ją obejmuje. --}}
                            <span class="block truncate text-xs text-stone-400">
                                @if ($person->shop)
                                    <span class="text-stone-500">{{ $person->shop->name }}</span> · {{ $person->email }}
                                @else
                                    {{ $person->email }}
                                @endif
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($selectedCount === 0)
            <p class="mt-3 text-xs text-amber-700">Nikt nie jest zaznaczony — wysyłka nie ruszy, dopóki kogoś nie wybierzesz.</p>
        @else
            <p class="mt-3 text-xs text-stone-400">Wybór zapisuje się od razu, nie musisz nic zatwierdzać.</p>
        @endif
    @endif
</div>
