<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Osie katalogu
    |--------------------------------------------------------------------------
    |
    | Sklep dzieli katalog na kilka NIEZALEŻNYCH osi. Ten sam magnes jest
    | jednocześnie „Kamieniem" (rodzaj), „Biegiem" i „UNESCO" (tematyka) oraz
    | „Włochami → Rzymem" (geografia) — i każdy z tych podziałów prowadzi do
    | niego osobną drogą.
    |
    | OSIE SĄ KONFIGURACJĄ, NIE KODEM. Wszystkie trzymają się w jednej tabeli
    | `categories`, rozróżniane kolumną `axis`; różnią się wyłącznie tym, co
    | stoi niżej. Kolejny klient dostaje własne osie („Kolor", „Okazja") przez
    | zmianę tego pliku — panel, filtry i adresy działają dla nich tak samo.
    |
    | Klucz osi jest po angielsku (jedzie do bazy i do kodu), `segment` po
    | polsku (jedzie do adresu) — zgodnie z konwencją projektu.
    |
    | `multiple`     — czy produkt może należeć do wielu węzłów tej osi
    | `hierarchical` — czy węzły mogą mieć rodziców (Włochy → Rzym)
    | `segment`      — segment adresu: /rodzaj/kamien, /geografia/wlochy/rzym
    | `suspendable`  — czy da się wstrzymać sprzedaż całego węzła („serii")
    |
    | WSTRZYMANIE MA SENS TYLKO NA OSI JEDNOKROTNEJ. Produkt należy wtedy do
    | dokładnie jednego węzła, więc „ta seria" znaczy jedno. Na osi wielokrotnej
    | magnes stojący w „Biegach" i w „UNESCO" byłby jednocześnie wstrzymany
    | i dostępny — pytanie bez dobrej odpowiedzi.
    |
    */

    'axes' => [

        'kind' => [
            'label' => 'Rodzaj',
            'label_plural' => 'Rodzaje',
            'segment' => 'rodzaj',
            'multiple' => false,
            'hierarchical' => false,
            'suspendable' => true,
            'hint' => 'Z czego produkt jest zrobiony — Metalowy Token, Wklejka z ramką, Kamień. Produkt należy do jednego rodzaju, bo to jego linia produkcyjna, a nie cecha opisowa.',
        ],

        'theme' => [
            'label' => 'Tematyka',
            'label_plural' => 'Tematyki',
            'segment' => 'tematyka',
            'multiple' => true,
            'hierarchical' => false,
            'hint' => 'O czym jest produkt — Biegi, Szczyty Górskie, UNESCO, Flagi. Jeden produkt trafia do kilku tematyk naraz.',
        ],

        'geo' => [
            'label' => 'Geografia',
            'label_plural' => 'Geografia',
            'segment' => 'geografia',
            'multiple' => true,
            'hierarchical' => true,
            'hint' => 'Gdzie — od kontynentu po miejscowość. Produkt przypięty do Rzymu pokazuje się także we Włoszech: podział zagłębia się, a wyższy poziom obejmuje wszystko, co pod nim.',
        ],

    ],

    /*
     * Ile poziomów zagnieżdżenia wolno zbudować na osi hierarchicznej.
     *
     * Afryka → Włochy → Rzym to trzy i tyle wystarcza sklepowi z magnesami.
     * Sufit jest po to, żeby pomyłka w panelu nie urodziła drzewa, którego nie
     * da się przejść ani wyświetlić — a nie po to, żeby kogoś ograniczać.
     */
    'max_depth' => 3,

    /*
     * Ile węzłów jednej osi pokazujemy w panelu i w filtrach, zanim lista
     * zacznie się zwijać. Geografia potrafi urosnąć do setek pozycji.
     */
    'listing_limit' => 200,

];
