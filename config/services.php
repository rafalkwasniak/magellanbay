<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discord' => [
        // Adres webhooka kanału, na który lecą alerty o błędach.
        'webhook' => env('DISCORD_WEBHOOK_URL'),
    ],

    // AI (dostawcy, modele, zadania) mieszka w osobnym pliku: config/ai.php.

    'mf' => [
        // Biała lista podatników VAT (Ministerstwo Finansów) — auto-fill firmy z NIP. Bez klucza.
        'base_url' => env('MF_WHITELIST_URL', 'https://wl-api.mf.gov.pl'),
    ],

    'gus' => [
        // GUS REGON BIR1.1 — dokładny auto-fill firmy z NIP (pełna nazwa + adres + województwo).
        // Wymaga darmowego klucza użytkownika BIR. Pusty klucz = pomijamy GUS (fallback: Biała lista).
        'key' => env('GUS_REGON_KEY'),
        'base_url' => env('GUS_REGON_URL', 'https://wyszukiwarkaregon.stat.gov.pl'),
    ],

    'inpost' => [
        // Geowidget v5 — mapa wyboru paczkomatu. Token jest PUBLICZNY z natury
        // (ląduje w HTML strony), chroni go dowiązanie do domeny: wygenerowany
        // na `*.kramio.pl`, więc działa na wszystkich storefrontach i tylko tam.
        // To token PLATFORMY (Poziom 1 — sprzedawca nie musi mieć konta InPost).
        // Sprzedawca z własnym kontem wkleja swój do shop_integrations i ten ma
        // pierwszeństwo; ten wpis jest zapasowy. Patrz pamięć „plan-shipping”.
        'geowidget_token' => env('INPOST_GEOWIDGET_TOKEN'),
    ],

    'fakturownia' => [
        // Faktury VAT. `url` = adres konta (np. https://twojadomena.fakturownia.pl).
        // Ten wpis to globalna/platformowa integracja; token per-sklep i tak trafi
        // do shop_integrations (zaszyfrowany). Na razie nieużywane — miejsce na token.
        'url' => env('FAKTUROWNIA_URL'),
        'token' => env('FAKTUROWNIA_TOKEN'),
    ],

    'paynow' => [
        // Płatności online (bramka mBanku). Model PER-SKLEP: klucze sprzedawcy
        // (dostęp do API + obliczanie podpisu) żyją w shop_integrations, zaszyfrowane
        // — TU NIE MA żadnych sekretów ani wpisów w `.env`. Trzymamy wyłącznie stałe,
        // publiczne adresy API operatora; wybór sandbox/produkcja robi środowisko
        // zapisane przy integracji sklepu. (Płatność za SaaS-a, gdyby kiedyś powstała,
        // byłaby osobną integracją platformy i dopiero ona sięgnęłaby po `.env`.)
        'base_url' => [
            'sandbox' => 'https://api.sandbox.paynow.pl',
            'production' => 'https://api.paynow.pl',
        ],

        // Konto PLATFORMY — opłaty za pakiety Kramio (pieniądze idą do nas,
        // nie do sprzedawcy). Jedyna konfiguracja Paynow w `.env`; kluczy
        // sklepów tu nie ma i nie będzie.
        'platform' => [
            'api_key' => env('PAYNOW_PLATFORM_API_KEY'),
            'signature_key' => env('PAYNOW_PLATFORM_SIGNATURE_KEY'),
            'environment' => env('PAYNOW_PLATFORM_ENVIRONMENT', 'sandbox'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Analytics — statystyki CENTRALI
    |--------------------------------------------------------------------------
    |
    | Dotyczy wyłącznie stron platformy (landing, rejestracja, logowanie,
    | dokumenty prawne). Sklepy sprzedawców mają WŁASNE identyfikatory, wpisywane
    | w Integracjach i trzymane per sklep — te dwa pomiary nigdy się nie mieszają.
    |
    | Identyfikator siedzi w `.env`, choć nie jest sekretem (i tak widać go w
    | źródle strony). Chodzi o to, żeby kopia serwisu postawiona lokalnie albo
    | na testowej domenie NIE dosypywała ruchu do statystyk produkcji — brak
    | wpisu po prostu wyłącza pomiar.
    |
    */

    'google' => [
        'analytics_id' => env('GOOGLE_ANALYTICS_ID'),
    ],

];
