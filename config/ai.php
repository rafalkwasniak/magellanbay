<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dostawcy AI
    |--------------------------------------------------------------------------
    |
    | Konto u dostawcy: adres API + klucz. Klucze wyłącznie w .env, nigdy tutaj.
    | Wszyscy liczący się dostawcy mówią dialektem OpenAI (POST /chat/completions),
    | więc dopisanie kolejnego to wpis w tej tablicy — bez zmian w kodzie.
    |
    */

    'providers' => [

        'deepseek' => [
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
            'key' => env('DEEPSEEK_API_KEY'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Ustawienia domyślne
    |--------------------------------------------------------------------------
    |
    | Podstawa dziedziczona przez każde zadanie. Zadanie nadpisuje tylko to,
    | co je odróżnia — reszta spada tutaj.
    |
    */

    'defaults' => [
        'provider' => env('AI_PROVIDER', 'deepseek'),
        'model' => env('AI_MODEL', 'deepseek-v4-flash'),

        // Modele rozumujące myślą przed odpowiedzią — to kosztuje i trwa.
        // Dozwolone: low, medium, high, max, xhigh (UWAGA: „minimal" nie istnieje,
        // DeepSeek odbija je błędem 400). Wartość pusta = nie wysyłamy parametru,
        // co jest potrzebne dla modeli, które go nie znają.
        'reasoning_effort' => env('AI_REASONING_EFFORT', 'low'),

        // Niska temperatura = przewidywalnie, blisko oryginału. Do zadań
        // twórczych (pisanie od zera) podnieś ją w profilu zadania.
        'temperature' => 0.3,

        // Sekundy. Rozumowanie potrafi wydłużyć odpowiedź kilkukrotnie,
        // a najdłuższe pole (strona CMS) ma limit 30 tys. znaków.
        'timeout' => (int) env('AI_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dzielenie tekstu na fragmenty
    |--------------------------------------------------------------------------
    |
    | Korekta trwa tyle, ile zajmuje PRZEPISANIE tekstu na wyjściu — czas rośnie
    | liniowo z długością pola. Całego regulaminu nie da się poprawić jednym
    | wywołaniem: przekroczyłby timeout. Dlatego przeglądarka tnie treść na
    | fragmenty (po blokach: akapit, nagłówek, lista) i wysyła je po kolei.
    |
    | Ta sama ścieżka obowiązuje wszędzie — krótki tekst po prostu wychodzi
    | jako jeden fragment. Bez podziału na „krótkie tak, długie inaczej", bo
    | dwa tryby prędzej czy później by się rozjechały.
    |
    | Blok większy niż ta wartość jest cięty dodatkowo w środku, po <br> —
    | wklejony regulamin trafia do edytora jako JEDEN <div> z setkami <br>,
    | więc bez tego szedłby w całości i nigdy by się nie zmieścił w timeoucie.
    | Granicą pozostaje pojedyncza linia: jej nie tniemy, bo cięcie w środku
    | zdania psułoby sens.
    |
    | 1200 znaków, a nie więcej: fragmenty lecą po kilka naraz, więc drobniejszy
    | podział skraca czas oczekiwania również przy zwykłym opisie produktu.
    |
    */

    'chunk_chars' => (int) env('AI_CHUNK_CHARS', 1200),

    /*
    |--------------------------------------------------------------------------
    | Zadania
    |--------------------------------------------------------------------------
    |
    | Kod nigdy nie wskazuje modelu — wskazuje ZADANIE („popraw tekst",
    | „napisz opis"), a tutaj zapada decyzja, czym je obsłużyć. Dzięki temu
    | podmiana modelu albo dostawcy dla jednej funkcji to jedna linia w tym
    | pliku, bez dotykania logiki i bez ryzyka, że zmiana rozleje się na resztę.
    |
    | Każdy klucz to nazwa zadania; wartość nadpisuje „defaults" wybiórczo.
    |
    */

    'tasks' => [

        // Redakcja tego, co sprzedawca już napisał: ortografia, interpunkcja,
        // styl. Zadanie proste i częste — ma być tanie i szybkie.
        //
        // Rozumowanie NA „low" JAWNIE — historia zatoczyła koło. Puste ''
        // (nie wysyłaj parametru) było zmierzone na deepseek-chat, który nie
        // rozumował, więc brak parametru znaczył „bez myślenia". Na
        // deepseek-v4-flash jest ODWROTNIE: brak parametru = domyślne, ciężkie
        // rozumowanie (pomiar 31.07 na opisie 1,1 tys. znaków: bez parametru
        // 1,8–6,6 tys. tokenów myślenia i 20–67 s; z „low" 1,0–3,5 tys. i
        // 14–37 s; „medium" — 47 s lub timeout). Rozrzut czasów jest po
        // stronie dostawcy i zostaje; „low" ścina jego górną połowę.
        'proofread' => [
            'reasoning_effort' => 'low',
        ],

        // Tworzenie opisu produktu od zera. Jeszcze nieużywane — profil czeka
        // gotowy na moment, gdy funkcja powstanie. Mocniejszy model, więcej
        // swobody twórczej i dłuższy timeout, bo pisanie trwa dłużej niż
        // poprawianie. To wywołanie jest wielokrotnie droższe od redakcji.
        'product_copy' => [
            'model' => 'deepseek-v4-pro',
            'reasoning_effort' => 'medium',
            'temperature' => 0.8,
            'timeout' => 180,
        ],

        // Opis do wyników wyszukiwania (meta description). Zadanie WYJĄTKOWO
        // małe: model dostaje gotową treść i ma z niej wycisnąć 1–2 zdania.
        // Bez rozumowania i z niską temperaturą, bo to streszczenie faktów, nie
        // twórczość — a wynik i tak przycinamy w kodzie. Krótki timeout: jeśli
        // model marudzi nad dwoma zdaniami, lepiej zostawić opis automatyczny.
        'seo_description' => [
            'reasoning_effort' => '',
            'temperature' => 0.4,
            'timeout' => 45,
        ],

    ],

];
