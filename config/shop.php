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
    | `max_upload_kb` = górny limit ORYGINAŁU na wejściu (walidacja uploadu). Wysoki
    | (20 MB), bo zdjęcia z telefonu bywają duże, a i tak zmniejszamy je do WebP i
    | oryginał wyrzucamy — limit chroni tylko przed skrajnościami/pamięcią GD.
    |
    */

    'product_images' => [
        'max_side' => (int) env('PRODUCT_IMAGE_MAX_SIDE', 1600),
        'quality' => (int) env('PRODUCT_IMAGE_QUALITY', 82),
        'max_upload_kb' => 20480, // 20 MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Wyróżnienie na stronie głównej sklepu
    |--------------------------------------------------------------------------
    |
    | Ile produktów sprzedawca może wyróżnić na stronie głównej (`show_on_homepage`).
    | Limit z premedytacją: strona główna ma być witryną „wow", a nie kopią listingu
    | — bez sufitu ktoś wrzuciłby na główną cały katalog i zniknąłby efekt. Sufit
    | domyka też liczbę sensownych układów głównej (hero/witryna dla ≤6). Dostępne
    | dla wszystkich pakietów (wygląd/kolory nie są uprawnieniem).
    |
    */

    'homepage_promoted_limit' => 6,

    /*
    | Gdy sprzedawca NIE wyróżnił żadnego produktu — ile LOSOWYCH aktywnych
    | pokazać na głównej (żeby nie była pusta; główna ma tak mało treści, że aż
    | prosi się o produkty). Losowe, nie „najnowsze", by za każdym wejściem
    | eksponować inne pozycje.
    */
    'homepage_fallback_count' => 3,

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
    | `entitlements` = kanoniczna lista uprawnień (ZATWIERDZONA 2026-07-19, 8 kluczy).
    | Wygląd/kolory i NASZA analityka są dla wszystkich, więc NIE są uprawnieniem
    | (GA/GTM to osobny klucz `ga_analytics`, płatny od Straganu). `max_products`
    | 24/48/96 dzieli się na pełne rzędy siatki.
    |
    | `price_yearly` = cena roczna BRUTTO (VAT 23%). Reguła: rok = 10× miesiąc, czyli
    | 2 miesiące gratis (75/mc→750/rok, 150/mc→1500/rok). To cena „na wystawie";
    | realny billing (pobieranie pieniędzy) jest osobnym, późniejszym tematem.
    |
    | Uprawnienia płatne (`online_payments`, `courier_shipping`, `invoices`,
    | `ga_analytics`) startują od Straganu; `order_editing`, `discount_codes`,
    | `bulk_mail` są WYŁĄCZNIE w Pawilonie (uzasadniają 2× cenę). `bulk_mail` to
    | na razie flaga „na wyrost" — sama funkcja dojdzie później.
    |
    | `ai_weekly_limit` = liczba ZADAŃ AI na tydzień (okno wg numeru ISO). Jedno
    | zadanie to jedno kliknięcie sprzedawcy, nawet gdy długi tekst dzieli się na
    | kilkanaście wywołań modelu. Liczby są hojne z premedytacją: pomiar z
    | 2026-07-28 pokazał, że wywołanie kosztuje 0,05–0,7 GROSZA, więc limit nie
    | chroni budżetu przed sprzedawcą, tylko przed pętlą i skryptem (throttle
    | 30/min pozwala na ~43 tys. wywołań dziennie ≈ 60 zł). Szczyt użycia wypada
    | przy zakładaniu sklepu, potem ruch spada — stąd okno tygodniowe, nie dzienne.
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
                'ai_weekly_limit' => 100,
                'online_payments' => false,
                'courier_shipping' => false,
                'invoices' => false,
                'ga_analytics' => false,
                'order_editing' => false,
                'discount_codes' => false,
                'bulk_mail' => false,
            ],
        ],
        'booth' => [
            'name' => 'Stragan',
            'order' => 2,
            'price_yearly' => 750,
            'available' => true,
            'entitlements' => [
                'max_products' => 48,
                'ai_weekly_limit' => 400,
                'online_payments' => true,
                'courier_shipping' => true,
                'invoices' => true,
                'ga_analytics' => true,
                'order_editing' => false,
                'discount_codes' => false,
                'bulk_mail' => false,
            ],
        ],
        'pavilion' => [
            'name' => 'Pawilon',
            'order' => 3,
            'price_yearly' => 1500,
            'available' => true,
            'entitlements' => [
                'max_products' => 96,
                'ai_weekly_limit' => 800,
                'online_payments' => true,
                'courier_shipping' => true,
                'invoices' => true,
                'ga_analytics' => true,
                'order_editing' => true,
                'discount_codes' => true,
                'bulk_mail' => true,
            ],
        ],
    ],

    // Slug pakietu domyślnego (darmowego) — przypisywany nowym sklepom.
    'default_package' => 'stall',

];
