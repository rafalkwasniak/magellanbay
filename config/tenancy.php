<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domena centrali
    |--------------------------------------------------------------------------
    |
    | Główna domena platformy. Tu leży zarządzanie: logowanie, rejestracja,
    | panel sprzedawcy i administratora. Storefronty sprzedawców siedzą na
    | subdomenach {shop}.{central_domain} (np. bukiety.shop.kwasniak.org),
    | gdzie {shop} = slug sklepu = etykieta subdomeny.
    |
    | Routing per-domena (Route::domain) włączymy razem z budową storefrontu;
    | na razie cała aplikacja działa na domenie centrali. Patrz routes/web.php.
    |
    */

    'central_domain' => env('APP_DOMAIN', 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | Subdomena sklepu (slug)
    |--------------------------------------------------------------------------
    |
    | Slug sklepu jest etykietą subdomeny ({slug}.{central_domain}), więc musi
    | mieścić się w regułach DNS. Długości to stała biznesowa walidacji slugu
    | przy rejestracji/aktywacji.
    |
    */

    'subdomain' => [
        'min' => 3,
        'max' => 63, // limit etykiety DNS
    ],

    /*
    |--------------------------------------------------------------------------
    | Zarezerwowane subdomeny
    |--------------------------------------------------------------------------
    |
    | Etykiety, których sprzedawca nie może wziąć jako slugu sklepu — kolidują
    | z infrastrukturą platformy (poczta, panel, sama centrala) albo są mylące.
    | Walidacja traktuje je jak zajęte.
    |
    */

    'reserved_subdomains' => [
        'www', 'mail', 'smtp', 'imap', 'pop', 'ftp', 'ns', 'ns1', 'ns2', 'mx',
        'admin', 'administrator', 'panel', 'app', 'api', 'cdn', 'static', 'assets',
        'login', 'logowanie', 'register', 'rejestracja', 'sprzedawca', 'seller',
        'shop', 'sklep', 'store', 'storefront', 'help', 'support', 'pomoc',
        'status', 'blog', 'dev', 'test', 'staging', 'demo', 'mailer', 'webmail',
    ],

];
