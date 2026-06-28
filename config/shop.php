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

];
