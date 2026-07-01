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
    | Pakiety (abonament roczny)
    |--------------------------------------------------------------------------
    |
    | Definicja pakietów = źródło prawdy TYLKO w momencie zakupu/przypisania.
    | Model „snapshot": po przypisaniu pakietu sklep KOPIUJE `entitlements` do
    | siebie (kolumna `entitlements` na `shops`) i od tej pory żyje własnym
    | zestawem — zmiana tych wartości później nie dotyka już opłaconych sklepów
    | („kupiłeś, masz"). Patrz Shop::assignPackage() i Shop::entitlement().
    |
    | Slug (klucz, EN) jest stały i ukryty; `name` (PL) to naklejka widoczna dla
    | klienta i można ją zmieniać bez ruszania bazy. `order` daje jawną hierarchię
    | (kod nie zgaduje po slugu, co jest awansem, a co zejściem). `available`
    | decyduje, czy pakiet da się kupić — wycofanego NIE usuwamy (stare sklepy go
    | trzymają), tylko ustawiamy `false`.
    |
    | `entitlements` = kanoniczna lista uprawnień. Wygląd/kolory i Analytics są dla
    | wszystkich, więc NIE są uprawnieniem. `price_yearly` to placeholder (kwoty
    | nieustalone). `max_products` 24/48/96 dzieli się na pełne rzędy siatki.
    |
    */

    'packages' => [
        'stall' => [
            'name' => 'Kram',
            'order' => 1,
            'price_yearly' => 0,
            'available' => true,
            'entitlements' => [
                'max_products' => 24,
                'online_payments' => false,
                'courier_shipping' => false,
                'discount_codes' => false,
                'custom_domain' => false,
            ],
        ],
        'booth' => [
            'name' => 'Stragan',
            'order' => 2,
            'price_yearly' => 500,
            'available' => true,
            'entitlements' => [
                'max_products' => 48,
                'online_payments' => true,
                'courier_shipping' => true,
                'discount_codes' => true,
                'custom_domain' => false,
            ],
        ],
        'pavilion' => [
            'name' => 'Pawilon',
            'order' => 3,
            'price_yearly' => 900,
            'available' => true,
            'entitlements' => [
                'max_products' => 96,
                'online_payments' => true,
                'courier_shipping' => true,
                'discount_codes' => true,
                'custom_domain' => true,
            ],
        ],
    ],

    // Slug pakietu domyślnego (darmowego) — przypisywany nowym sklepom.
    'default_package' => 'stall',

];
