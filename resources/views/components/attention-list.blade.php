@props(['groups'])

{{--
    Lista „Wymaga uwagi" — wspólny wygląd dla Pakietów i Zamówień.

    Wspólny komponent, a nie dwie kopie, bo obie listy odpowiadają na to samo
    pytanie („co się zacięło") i muszą wyglądać identycznie. Dwie kopie Blade
    rozjechałyby się przy pierwszej poprawce jednej z nich.

    Kształt grupy ustalają klasy `App\Support\PackageAttention` i `OrderAttention`:
    key, label, hint, tone (rose|amber|stone) oraz items (title, subtitle, note, url).

    Nagłówek i stan pusty zostają po stronie ekranu — każdy tłumaczy pustkę
    własnymi słowami, a tu jest tylko sama lista.
--}}
<div class="space-y-5">
    @foreach ($groups as $group)
        <div>
            <h3 @class([
                'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                'bg-rose-50 text-rose-700' => $group['tone'] === 'rose',
                'bg-amber-50 text-amber-700' => $group['tone'] === 'amber',
                'bg-stone-100 text-stone-600' => $group['tone'] === 'stone',
            ])>{{ $group['label'] }} · {{ count($group['items']) }}</h3>
            <p class="mt-1.5 text-xs text-stone-400">{{ $group['hint'] }}</p>

            {{-- W wąskiej kolumnie nazwa i szczegół idą JEDNO POD DRUGIM.
                 Rozstrzelone w poprzek zawijałyby się w schodki. --}}
            <ul class="mt-2 space-y-1">
                @foreach ($group['items'] as $item)
                    <li>
                        <a href="{{ $item['url'] }}" class="block rounded-2xl px-3 py-2 transition hover:bg-white">
                            <span class="block truncate text-sm font-medium text-stone-900">{{ $item['title'] }}</span>
                            <span class="block truncate text-xs text-stone-400">{{ $item['subtitle'] }}</span>
                            <span class="mt-0.5 block text-xs text-stone-500">{{ $item['note'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
