<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Wiadomości platformy do sprzedawców
    |--------------------------------------------------------------------------
    |
    | Odpowiednik `config/bulk_mail.php`, ale dla wiadomości, które Kramio pisze
    | do WŁASNYCH sprzedawców. Osobny plik, bo osobna ścieżka: tu nie ma sklepu,
    | pakietu ani karencji.
    |
    | ŚWIADOMY BRAK `cooldown_days`. U sprzedawcy karencja chroni jego klientów
    | przed zasypaniem i pilnuje dostarczalności. Tutaj adresatami są ludzie,
    | z którymi platforma ma umowę, a nadawcą jedna osoba, która wie, co robi —
    | więc żadnej blokady czasowej nie ma i nie ma jej być.
    |
    | `per_minute` zostaje, ale z innego powodu niż karencja: rozkłada wysyłkę
    | w czasie, żeby nie uderzyć jednym ciosem w limity shared hostingu. Tempo
    | wyższe niż u sprzedawcy, bo sprzedawców są dziesiątki, a nie tysiące.
    |
    | Wartości bez `env()` celowo — to stałe produktu, nie ustawienia wdrożenia,
    | a każdy nowy klucz w `.env` to kolejny do pilnowania w `.env.example`.
    |
    */

    'per_minute' => 30,

    /*
    | Limit długości treści (HTML z edytora Trix). Ten sam limit widzi licznik
    | znaków pod edytorem, walidacja zapisu i redakcja przez AI.
    */

    'body_max' => 8000,

];
