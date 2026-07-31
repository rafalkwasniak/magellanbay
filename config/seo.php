<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Grafika centrali do social mediów
    |--------------------------------------------------------------------------
    |
    | Karta 1200×630, którą Facebook i Messenger pokazują przy linku do
    | kramio.pl. Storefronty sprzedawców mają własne, generowane grafiki
    | (App\Services\OgImageGenerator) — ta dotyczy WYŁĄCZNIE stron platformy.
    |
    | Ścieżka względem katalogu `public/`. Nazwa pliku zawiera losowy ciąg
    | celowo: Facebook trzyma pobraną grafikę w swoim cache'u nawet kilka
    | tygodni, więc nowa wersja MUSI mieć nową nazwę, żeby serwisy zobaczyły
    | zmianę. Podmieniając grafikę, wgraj plik pod nową nazwą i zmień tę linię.
    |
    */

    'og_image' => 'images/4163f74a55c4ae5f3580ef0212884c21.jpg',

];
