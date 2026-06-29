<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Województwa (PL)
    |--------------------------------------------------------------------------
    |
    | Stały zbiór 16 województw. Używany jako opcje selecta w profilu sklepu i
    | jako reguła walidacji (Rule::in). Sklep jest jednokrajowy (PL) — patrz
    | decyzja o locale pl-first.
    |
    */

    'provinces' => [
        'dolnośląskie',
        'kujawsko-pomorskie',
        'lubelskie',
        'lubuskie',
        'łódzkie',
        'małopolskie',
        'mazowieckie',
        'opolskie',
        'podkarpackie',
        'podlaskie',
        'pomorskie',
        'śląskie',
        'świętokrzyskie',
        'warmińsko-mazurskie',
        'wielkopolskie',
        'zachodniopomorskie',
    ],

    /*
    |--------------------------------------------------------------------------
    | Opis sklepu
    |--------------------------------------------------------------------------
    |
    | Maksymalna długość opisu sklepu (HTML z edytora Trix). Jedno źródło prawdy —
    | używane w walidacji (ShopProfileRequest) i przy redakcji AI (limit długości
    | wyniku). Wyższy niż widoczny tekst, bo wlicza znaczniki formatowania.
    |
    */

    'description_max' => 4000,

    /*
    |--------------------------------------------------------------------------
    | Opis produktu
    |--------------------------------------------------------------------------
    |
    | Maksymalna długość opisu produktu (HTML z edytora Trix). Wyższy niż opis
    | sklepu, bo opisy produktów bywają dłuższe.
    |
    */

    'product_description_max' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Asystent AI („Popraw przez AI")
    |--------------------------------------------------------------------------
    |
    | Limit redakcji AI na pojedyncze pole w ramach jednego załadowania strony
    | (pilnowany po stronie frontu). Chroni przed nadużyciem płatnego API.
    |
    */

    'ai' => [
        'max_uses_per_field' => (int) env('AI_MAX_USES_PER_FIELD', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zdjęcia produktu (optymalizacja)
    |--------------------------------------------------------------------------
    |
    | Każde wgrane zdjęcie produktu jest skalowane (dłuższy bok do `max_side` px)
    | i ponownie kodowane jako WebP — niezależnie od formatu wejściowego. WebP daje
    | wyraźnie mniejsze pliki niż JPEG/PNG przy tej samej jakości i obsługuje
    | przezroczystość, więc trzymamy jeden format. Ponowne kodowanie usuwa metadane
    | (EXIF). `quality` 0–100: wyżej = lepsza jakość i większy plik.
    |
    */

    'product_images' => [
        'max_side' => (int) env('PRODUCT_IMAGE_MAX_SIDE', 1600),
        'quality' => (int) env('PRODUCT_IMAGE_QUALITY', 82),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pakiety i limity
    |--------------------------------------------------------------------------
    |
    | Limity ilościowe w konfiguracji (nie w kodzie). W MVP dostępny jest tylko
    | pakiet Free. Mechanizm gotowy na kolejne warianty abonamentowe.
    |
    */

    'packages' => [
        'free' => [
            'max_products' => 25,
        ],
    ],

];
