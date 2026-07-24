@props([
    'series' => [],      // list<array{label:string, full:string, revenue:float, orders:int}>
    'color' => '#f59e0b', // akcent słupka (amber panelu); jedna seria → bez legendy
])

{{-- Wykres „sprzedaż w czasie" renderowany SERWEROWO jako SVG — zero JS, zero
     bundla. Jedna seria (obrót), więc bez legendy: tytuł karty ją nazywa. Słupki
     zakotwiczone w bazie z zaokrąglonym wierzchołkiem; skalowanie jednorodne
     (preserveAspectRatio meet), żeby zaokrąglenia nie robiły się elipsami. Tooltip
     natywny: <title> na przezroczystej kolumnie (większy cel najechania niż słupek).
     Kwoty i etykiety w kolorze tekstu (stone), nie w kolorze serii. --}}
@php
    $data = array_values($series);
    $count = count($data);
    $max = $count > 0 ? max(array_map(fn ($p) => (float) $p['revenue'], $data)) : 0.0;

    // Układ w jednostkach viewBoxu (skalowany jednorodnie do szerokości karty).
    $w = 640; $h = 240;
    $mTop = 18; $mBottom = 28; $mSide = 10;
    $plotW = $w - 2 * $mSide;
    $plotH = $h - $mTop - $mBottom;
    $baseline = $mTop + $plotH;

    $slot = $count > 0 ? $plotW / $count : $plotW;
    $barW = min($slot * 0.62, 48);

    // Rzadkie etykiety osi X: przy wielu słupkach co n-ty + zawsze ostatni.
    $labelStep = $count <= 8 ? 1 : (int) ceil($count / 8);
@endphp

@if ($count === 0 || $max <= 0.0)
    <div class="flex h-40 items-center justify-center rounded-2xl border border-dashed border-stone-300 text-sm text-stone-400">
        Brak sprzedaży w tym okresie.
    </div>
@else
    <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="xMidYMid meet"
         class="w-full" style="height:auto" fill="none"
         xmlns="http://www.w3.org/2000/svg" role="img"
         aria-label="Wykres obrotu w czasie">
        {{-- Linia maksimum (recesywna) + baza --}}
        <line x1="{{ $mSide }}" y1="{{ $mTop }}" x2="{{ $w - $mSide }}" y2="{{ $mTop }}" stroke="#e7e5e4" stroke-width="1" stroke-dasharray="3 4" />
        <line x1="{{ $mSide }}" y1="{{ $baseline }}" x2="{{ $w - $mSide }}" y2="{{ $baseline }}" stroke="#d6d3d1" stroke-width="1" />

        {{-- Etykieta maksimum (kwota) w kolorze tekstu --}}
        <text x="{{ $mSide }}" y="{{ $mTop - 6 }}" font-size="12" fill="#a8a29e">{{ \App\Support\Money::pln($max) }}</text>

        @foreach ($data as $i => $point)
            @php
                $value = (float) $point['revenue'];
                $barH = $max > 0 ? ($value / $max) * $plotH : 0.0;
                $cx = $mSide + $slot * ($i + 0.5);
                $x = $cx - $barW / 2;
                $y = $baseline - $barH;
                $tooltip = $point['full'].': '.\App\Support\Money::pln($value).' · '.$point['orders'].' zam.';
                $showLabel = $i % $labelStep === 0 || $i === $count - 1;
            @endphp

            @if ($barH > 0.5)
                <rect x="{{ round($x, 2) }}" y="{{ round($y, 2) }}" width="{{ round($barW, 2) }}" height="{{ round($barH, 2) }}" rx="3" fill="{{ $color }}" />
            @endif

            {{-- Przezroczysta kolumna = duży cel najechania z natywnym tooltipem --}}
            <rect x="{{ round($cx - $slot / 2, 2) }}" y="{{ $mTop }}" width="{{ round($slot, 2) }}" height="{{ $plotH }}" fill="transparent" pointer-events="all">
                <title>{{ $tooltip }}</title>
            </rect>

            @if ($showLabel)
                <text x="{{ round($cx, 2) }}" y="{{ $h - 9 }}" font-size="12" fill="#a8a29e" text-anchor="middle">{{ $point['label'] }}</text>
            @endif
        @endforeach
    </svg>
@endif
