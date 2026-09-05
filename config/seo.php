<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Grafika centrali do social mediów
    |--------------------------------------------------------------------------
    |
    | Karta 1200×630, którą Facebook i Messenger pokazują przy linku do stron
    | PLATFORMY — landingu, logowania, dokumentów. Sam storefront ma własną,
    | generowaną z marki sklepu (App\Services\OgImageGenerator) i ta wartość
    | jej nie dotyczy.
    |
    | Ścieżka względem katalogu `public/`.
    |
    */

    /*
     * PUSTE = brak karty, i to jest poprawny stan wdrożenia.
     *
     * Dopóki klient nie dostarczy własnej grafiki, nie podstawiamy tu żadnej.
     * Cudza karta znaczyłaby, że link do jego sklepu wrzucony na Facebooka
     * wyświetla się z NASZĄ marką — a to gorsze niż brak obrazka, bo wygląda
     * na pomyłkę właściciela, nie na brak materiału. Bez wartości widoki nie
     * wypisują `og:image` w ogóle (pusty atrybut serwisy czytają jak zepsuty
     * plik) i pokazują sam adres.
     *
     * Grafikę wgrywa się do `public/images/` POD NOWĄ NAZWĄ i wpisuje ją do
     * `SEO_OG_IMAGE` — Facebook trzyma pobraną wersję tygodniami, więc ta sama
     * nazwa nie doczeka się odświeżenia. Wymiar: 1200 × 630.
     */
    'og_image' => env('SEO_OG_IMAGE') ?: null,

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
