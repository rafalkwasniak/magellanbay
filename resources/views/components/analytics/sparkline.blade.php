@props(['points' => [], 'color' => '#f59e0b'])

{{-- Sparkline renderowany SERWEROWO jako SVG — zero JS, zero bundla, działa też bez
     JavaScriptu. Punkty (garść gotowych liczb) normalizujemy do viewBoxu; kolor
     wprost hexem, żeby nie zależeć od klas Tailwind w buildzie. `preserveAspectRatio
     none` + `vector-effect non-scaling-stroke` rozciąga wykres na szerokość karty,
     nie pogrubiając linii. Płaska seria (same zera / brak zmian) → linia w połowie. --}}
@php
    $values = array_values(array_map('floatval', $points));
    $count = count($values);

    $width = 100.0;
    $height = 28.0;
    $pad = 3.0; // pionowy margines, żeby linia nie kleiła się do krawędzi
    $usable = $height - 2 * $pad;

    $max = $count > 0 ? max($values) : 0.0;
    $min = $count > 0 ? min($values) : 0.0;
    $range = $max - $min;

    $coords = [];
    if ($count === 1) {
        $coords[] = '0,'.round($height / 2, 2);
        $coords[] = round($width, 2).','.round($height / 2, 2);
    } elseif ($count > 1) {
        foreach ($values as $i => $value) {
            $x = ($i / ($count - 1)) * $width;
            $y = $range > 0.0
                ? $pad + (1 - (($value - $min) / $range)) * $usable
                : $height / 2;
            $coords[] = round($x, 2).','.round($y, 2);
        }
    }

    $polyline = implode(' ', $coords);
@endphp

@if ($polyline !== '')
    <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none"
         class="w-full" style="height:1.75rem" fill="none"
         xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <polyline points="{{ $polyline }}" stroke="{{ $color }}" stroke-width="1.5"
                  stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
    </svg>
@endif
