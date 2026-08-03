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

    'og_image' => 'images/og-kramio-2026-08.jpg',

    /*
    |--------------------------------------------------------------------------
    | Grafika promująca SKLEP sprzedawcy
    |--------------------------------------------------------------------------
    |
    | Karta 1200×630 składana per sklep: scena (biurko z monitorem) na gradiencie
    | z palety sklepu, w monitorze zdjęcia jego produktów, po lewej logo albo
    | nazwa, zdanie o sklepie, zachęta i adres.
    |
    | `scene` to render z PRZEZROCZYSTYM tłem i ekranem wypełnionym zielenią —
    | zieleń jest wycinana automatycznie (App\Services\Og\SceneCutout), a w jej
    | miejsce wchodzi zawartość ekranu. Podmiana pliku wystarczy: wycięcie i
    | narożniki ekranu przeliczą się same przy pierwszym użyciu.
    |
    | `max_products` to sufit kafli w monitorze. Więcej niż sześć robi się w tej
    | skali nieczytelną mozaiką.
    |
    */

    'shop_card' => [
        'scene' => 'images/og-example.png',
        'max_products' => 6,
    ],

];
