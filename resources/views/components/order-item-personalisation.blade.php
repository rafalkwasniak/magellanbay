@props(['item'])

{{-- Personalizacja pozycji zamówienia — JEDEN komponent na cztery ekrany.

     Renderuje się w panelu sprzedawcy (kolory `stone-*`) i na storefroncie
     (kolory z palety sklepu), więc klasy są NEUTRALNE: sama przezroczystość
     i wielkość, bez własnego koloru. Dziedziczy ten, który już jest — dzięki
     temu jedna kopia obsługuje oba światy, zamiast dwóch, które rozjadą się
     przy pierwszej poprawce.

     Dlaczego to w ogóle musi być widoczne: bez tego zamówienie na „2 × Magnes"
     nie mówi, jakie imiona wygrawerować. Sklep przyjąłby pieniądze za rzecz,
     której nie umie wykonać. --}}
@if ($item->isPersonalised())
    <dl {{ $attributes->merge(['class' => 'mt-1 space-y-0.5 text-xs opacity-70']) }}>
        @foreach ($item->personalisationLines() as $wpis)
            <div class="flex flex-wrap gap-x-1.5">
                <dt class="opacity-80">{{ $wpis['label'] }}:</dt>
                <dd class="font-medium break-words">{{ $wpis['value'] }}</dd>
            </div>
        @endforeach
    </dl>
@endif
