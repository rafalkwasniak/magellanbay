<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Szablony storefrontu (wygląd sklepu)
    |--------------------------------------------------------------------------
    |
    | Motyw sklepu ma DWIE niezależne osie (patrz notatka „storefront-theme-system"):
    |   1. SZABLON  = skóra + osobowość (kolory, typografia, styl układu). Wybierany
    |                 przez sprzedawcę z gotowej siatki. Definicja żyje TU, w kodzie —
    |                 dodanie szablonu = nowy wpis + widoki/CSS, nie funkcja panelu.
    |   2. PALETA   = konkretny zestaw kolorów w ramach szablonu (gotowy „preset").
    |                 Sprzedawca klika gotowca; nie dłubie pojedynczych kolorów.
    |
    | Model przechowywania jak przy pakietach (config/shop.php): sklep trzyma tylko
    | REFERENCJĘ — `shops.template` (slug szablonu) + `shops.theme` (JSON, m.in.
    | wybrana paleta). Definicje (nazwy, kolory) są tu i można je zmieniać bez
    | ruszania bazy. Slug (EN) stały i ukryty; `name` (PL) widoczny i zmienialny.
    |
    | KONTRAKT TOKENÓW — każda paleta dostarcza ten sam mały zestaw semantycznych
    | tokenów, które storefront wystawi jako zmienne CSS (:root, serwerowo — bez
    | kompilacji per-sklep, wymóg shared-hostingu). Reszta kolorów (obramowania,
    | wyszarzenia) będzie WYLICZANA z tych w CSS, nie wybierana przez sprzedawcę:
    |   brand      — kolor marki (przyciski, linki, cena, akcenty)
    |   brand_ink  — tekst NA kolorze marki (jawny, dla kontrastu na etykietach)
    |   surface    — tło strony (ton/„nastrój")
    |   ink        — główny kolor tekstu na tle
    |
    | Kolory poniżej to rozsądny start — dopieszczymy je wizualnie, gdy storefront
    | zacznie je renderować. `order` daje jawną kolejność w siatce.
    |
    */

    'templates' => [

        'velvet_cloud' => [
            'name' => 'Aksamitna chmurka',
            'description' => 'Jasny i powietrzny — biel z nutą błękitu. Lekki, czysty, wygodny do czytania.',
            'order' => 1,
            // Liczba produktów na stronę wykazu = właściwość UKŁADU, więc należy do
            // szablonu: większe kadry → mniej na stronie. Ten jest powietrzny → 9.
            'per_page' => 9,
            'default_palette' => 'sky',
            'palettes' => [
                'sky' => [
                    'name' => 'Błękit',
                    'tokens' => [
                        'brand' => '#3B82F6',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F8FAFC',
                        'ink' => '#1E293B',
                    ],
                ],
                'lavender' => [
                    'name' => 'Lawenda',
                    'tokens' => [
                        'brand' => '#7C6FD6',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FAFAFF',
                        'ink' => '#2A2540',
                    ],
                ],
                'mint' => [
                    'name' => 'Mięta',
                    'tokens' => [
                        'brand' => '#2FA98C',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F5FBF8',
                        'ink' => '#1E3A33',
                    ],
                ],
                'blush' => [
                    'name' => 'Pudrowy róż',
                    'tokens' => [
                        'brand' => '#E0699B',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FDF7FA',
                        'ink' => '#3A2530',
                    ],
                ],
                'coral' => [
                    'name' => 'Koral',
                    'tokens' => [
                        'brand' => '#F0765B',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FEF8F5',
                        'ink' => '#3A2822',
                    ],
                ],
            ],
        ],

        'green_nook' => [
            'name' => 'Zielony zakątek',
            'description' => 'Kolory natury — zieleń, brąz i len. Ciepły, ekologiczny klimat.',
            'order' => 2,
            'per_page' => 12,
            'default_palette' => 'forest',
            'palettes' => [
                'forest' => [
                    'name' => 'Las',
                    'tokens' => [
                        'brand' => '#3F7D4E',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F5F3EC',
                        'ink' => '#2E3A2E',
                    ],
                ],
                'moss' => [
                    'name' => 'Mech',
                    'tokens' => [
                        'brand' => '#6B8E4E',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F3F1E7',
                        'ink' => '#33372B',
                    ],
                ],
                'clay' => [
                    'name' => 'Glina',
                    'tokens' => [
                        'brand' => '#B4633E',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F6F1EA',
                        'ink' => '#3A2C24',
                    ],
                ],
                'olive' => [
                    'name' => 'Oliwka',
                    'tokens' => [
                        'brand' => '#7A7B3F',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F4F2E8',
                        'ink' => '#34331F',
                    ],
                ],
                'honey' => [
                    'name' => 'Miód',
                    'tokens' => [
                        'brand' => '#C79338',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F7F3E9',
                        'ink' => '#3A3018',
                    ],
                ],
            ],
        ],

        'graphite_dusk' => [
            'name' => 'Grafitowy wieczór',
            'description' => 'Ciemny i elegancki — grafit z ciepłym akcentem. Produkty wychodzą na pierwszy plan.',
            'order' => 3,
            'per_page' => 12,
            'default_palette' => 'ember',
            'palettes' => [
                'ember' => [
                    'name' => 'Bursztyn',
                    'tokens' => [
                        'brand' => '#E0A25E',
                        'brand_ink' => '#1A1A1A',
                        'surface' => '#1F2124',
                        'ink' => '#E8E6E1',
                    ],
                ],
                'rose' => [
                    'name' => 'Róża',
                    'tokens' => [
                        'brand' => '#D98A8A',
                        'brand_ink' => '#1A1A1A',
                        'surface' => '#222024',
                        'ink' => '#ECE8E6',
                    ],
                ],
                'gold' => [
                    'name' => 'Złoto',
                    'tokens' => [
                        'brand' => '#D4B15F',
                        'brand_ink' => '#1A1A1A',
                        'surface' => '#202225',
                        'ink' => '#E9E6DE',
                    ],
                ],
                'teal' => [
                    'name' => 'Turkus',
                    'tokens' => [
                        'brand' => '#5FB3AE',
                        'brand_ink' => '#1A1A1A',
                        'surface' => '#1E2325',
                        'ink' => '#E4E9E8',
                    ],
                ],
                'orchid' => [
                    'name' => 'Storczyk',
                    'tokens' => [
                        'brand' => '#B394D6',
                        'brand_ink' => '#1A1A1A',
                        'surface' => '#232027',
                        'ink' => '#E9E5EC',
                    ],
                ],
            ],
        ],

    ],

    // Slug szablonu domyślnego — przypisywany nowym sklepom (spójne z kolumną
    // `shops.template` default). Musi istnieć w `templates` powyżej.
    'default_template' => 'velvet_cloud',

];
