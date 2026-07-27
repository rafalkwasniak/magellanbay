<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Throttling logowania
    |--------------------------------------------------------------------------
    |
    | Po przekroczeniu liczby nieudanych prób logowania (liczonych per
    | e-mail + IP) konto jest tymczasowo blokowane. `decay_seconds` to czas
    | blokady liczony od pierwszej nieudanej próby w oknie.
    |
    */

    'login' => [
        'max_attempts' => 5,
        'decay_seconds' => 300, // 5 minut
    ],

    /*
    |--------------------------------------------------------------------------
    | Nagłówki bezpieczeństwa odpowiedzi
    |--------------------------------------------------------------------------
    |
    | Wysyłane przy każdej odpowiedzi HTTP (middleware SecurityHeaders). Audyt
    | ursalogic z 16.07.2026 dał platformie ocenę E za bezpieczeństwo — 55/100
    | IDENTYCZNIE na wszystkich 100 podstronach. Powodem nie była dziura w
    | aplikacji, tylko brak tych nagłówków; to poprawka, która działa na
    | wszystkie sklepy naraz.
    |
    | Wartość `null` = nagłówka nie wysyłamy (wygodne przy dostrajaniu).
    |
    | `Permissions-Policy`: geolokalizacja zostaje dozwolona dla własnej domeny,
    | bo mapa paczkomatów InPost używa jej do znalezienia punktów w pobliżu
    | kupującego. Kamera, mikrofon i pozostałe czujniki — wyłączone.
    |
    | Content-Security-Policy ŚWIADOMIE tu nie ma: storefront niesie inline
    | <style> z tokenami motywu, inline <script> GTM/GA i skrypty Livewire, więc
    | sensowne CSP wymaga nonce'ów i jest osobnym zadaniem, nie jedną linijką.
    |
    */

    'headers' => [
        // Przeglądarka nie zgaduje typu treści — blokuje atak plikiem
        // podszywającym się pod inny typ („zdjęcie", które jest skryptem).
        'X-Content-Type-Options' => 'nosniff',

        // Clickjacking: naszych stron nie wolno osadzić w ramce obcej witryny.
        'X-Frame-Options' => 'SAMEORIGIN',

        // Do obcych witryn wysyłamy samą domenę, bez ścieżki — adresy z tokenem
        // (płatność, faktura) nie mogą wyciec w nagłówku Referer.
        'Referrer-Policy' => 'strict-origin-when-cross-origin',

        'Permissions-Policy' => 'geolocation=(self), camera=(), microphone=(), payment=(), usb=()',
    ],

    /*
    |--------------------------------------------------------------------------
    | HSTS (Strict-Transport-Security)
    |--------------------------------------------------------------------------
    |
    | Wysyłany WYŁĄCZNIE po HTTPS — na zwykłym HTTP nic nie znaczy, a lokalnie
    | tylko przeszkadza. `include_subdomains` jest bezpieczne, bo storefronty
    | siedzą pod wildcard SSL `*.kramio.pl` (CLAUDE.md sek. 3 pkt 5).
    |
    | `preload` ŚWIADOMIE wyłączony: wpis na listę preload przeglądarek jest
    | praktycznie nieodwracalny i wymusza HTTPS na wszystkich subdomenach na
    | zawsze. Do rozważenia, gdy platforma się ustabilizuje.
    |
    */

    'hsts' => [
        'enabled' => true,
        'max_age' => 31536000,          // rok
        'include_subdomains' => true,
        'preload' => false,
    ],

];
