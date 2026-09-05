<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dane startowe wdrożenia — CZYTANE RAZ, przez DeploymentSeeder
    |--------------------------------------------------------------------------
    |
    | Z tych wartości powstaje przy wdrożeniu konto właściciela i jedyny rekord
    | `shops`. Potem nikt tego pliku już nie czyta.
    |
    | ==> ŹRÓDŁEM PRAWDY PO WDROŻENIU JEST BAZA, NIE TEN PLIK. <==
    |
    | Właściciel zmienia dane sklepu w panelu i od tej chwili wartości poniżej są
    | historią. Zmiana `DEPLOY_COMPANY_NIP` w `.env` pół roku później NIE zmieni
    | NIP-u na fakturach — trzeba wejść w ustawienia sklepu. To jedyna pułapka
    | tego pliku i dlatego jest opisana na samej górze.
    |
    | DLACZEGO WARTOŚCI SIEDZĄ W `.env`, A NIE WPROST TUTAJ: ten plik jest w
    | repozytorium, a repozytorium jest BAZĄ DLA KOLEJNYCH KLIENTÓW. Wpisane tu
    | na sztywno dane Magellan Bay pojechałyby do następnego wdrożenia i albo
    | trafiłyby na fakturę cudzej firmy, albo ktoś musiałby je wycinać ręcznie.
    | `.env` nie jest w gicie i jest z definicji per instalacja — to jego rola.
    |
    | Puste wartości są dopuszczalne wszędzie poza `owner.email` i `shop.name` —
    | seeder pilnuje tych dwóch i odmawia startu, gdy ich brak. Reszty właściciel
    | i tak może dopisać w panelu, a zgadywanie za niego byłoby gorsze niż puste
    | pole (patrz `Shop::addressLine()` — składa adres z tego, co jest).
    |
    */

    'owner' => [
        'name' => env('DEPLOY_OWNER_NAME', ''),
        'surname' => env('DEPLOY_OWNER_SURNAME', ''),
        'email' => env('DEPLOY_OWNER_EMAIL', ''),
        'phone' => env('DEPLOY_OWNER_PHONE', ''),
    ],

    'shop' => [
        // Domyślnie nazwa aplikacji — w sklepie dedykowanym to ta sama rzecz.
        'name' => env('DEPLOY_SHOP_NAME', env('APP_NAME')),

        /*
         * Slug jest kolumną UNIQUE odziedziczoną po Kramio, gdzie był etykietą
         * subdomeny. Tutaj sklep stoi na domenie głównej i slug nie jest już
         * adresem — ale kolumna zostaje (patrz zasada „konfiguracja, nie
         * wycinanie"), więc trzeba go czymś wypełnić. Puste = wyliczymy z nazwy.
         */
        'slug' => env('DEPLOY_SHOP_SLUG', ''),

        'contact_email' => env('DEPLOY_SHOP_EMAIL', env('MAIL_FROM_ADDRESS', '')),
        'contact_phone' => env('DEPLOY_SHOP_PHONE', ''),
    ],

    /*
     * Dane firmowe sprzedawcy — idą na faktury, do regulaminu i na strony
     * informacyjne. W sklepie dedykowanym operator i sprzedawca to TA SAMA
     * firma, więc te same dane powinny znaleźć się także w `config/company.php`
     * (stopki maili systemowych). Dwa źródła to spadek po Kramio, gdzie były to
     * dwa różne podmioty — przy wdrożeniu wyrównać ręcznie.
     */
    'company' => [
        'company_name' => env('DEPLOY_COMPANY_NAME', ''),
        'nip' => env('DEPLOY_COMPANY_NIP', ''),
        'country' => env('DEPLOY_COMPANY_COUNTRY', 'PL'),
        'province' => env('DEPLOY_COMPANY_PROVINCE', ''),
        'city' => env('DEPLOY_COMPANY_CITY', ''),
        'postal_code' => env('DEPLOY_COMPANY_POSTAL_CODE', ''),
        'street' => env('DEPLOY_COMPANY_STREET', ''),
        'building_number' => env('DEPLOY_COMPANY_BUILDING', ''),
        'apartment_number' => env('DEPLOY_COMPANY_APARTMENT', ''),
    ],

    /*
     * Ustawienia sprzedaży na start. Metod dostawy i płatności NIE włączamy
     * z automatu: każda z nich wymaga danych, których seeder nie ma (numer
     * konta, koszty wysyłki, klucze InPostu), a włączona metoda bez danych
     * to sklep, który przyjmuje zamówienia, których nie da się zrealizować.
     * Właściciel włącza je w panelu, gdy poda dane. Do tego czasu sklep działa
     * w trybie katalogu (`Shop::acceptsOrders()` zwraca false) — to świadomie
     * obsłużony stan, nie usterka.
     */
    'sales' => [
        'default_vat_rate' => env('DEPLOY_VAT_RATE', '23'),
    ],

    /*
     * Wygląd startowy: szablon i paleta z `config/themes.php`.
     *
     * Domyślny szablon aplikacji (`themes.default_template`) należy do rodziny
     * Kramio i jest sensowny dla sprzedawcy z ulicy, który dopiero wybiera sobie
     * skórę. Klient dedykowany płaci za sklep, który od pierwszego uruchomienia
     * wygląda na jego — nie za ekran wyboru. Właściciel może to potem zmienić
     * w panelu („Charakter sklepu"), ale START ma być gotowy.
     */
    'appearance' => [
        'template' => env('DEPLOY_TEMPLATE', 'white_harbour'),
        'palette' => env('DEPLOY_PALETTE', 'sunset'),
    ],

    /*
     * Pakiet wdrożeniowy: bez limitów, wszystkie funkcje otwarte, nigdy nie
     * wygasa (patrz config/shop.php → packages.dedicated). Wpisany na sztywno,
     * bo w sklepie dedykowanym nie ma innego wyboru — a `.env` z możliwością
     * wpisania „stall" byłoby zaproszeniem do wdrożenia klienta na darmowym
     * pakiecie z limitem produktów.
     */
    'package' => 'dedicated',

];
