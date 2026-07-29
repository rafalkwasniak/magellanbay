<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Korespondencja seryjna (newsletter sklepu)
    |--------------------------------------------------------------------------
    |
    | Wiadomość do własnych, zarejestrowanych klientów sklepu, którzy udzielili
    | osobnej zgody marketingowej. Uprawnienie `bulk_mail` (pakiet Pawilon).
    |
    | `per_minute` = ile wiadomości mailingu wypuszczamy na minutę. NIE jest to
    | osobny throttler: przy wysyłce nadajemy każdemu mailowi `scheduled_at`
    | w paczkach tej wielkości, a kolejkę i tak opróżnia cron co minutę. Dzięki
    | temu tempo jest znane z góry (widać, o której wysyłka się skończy), a
    | maile transakcyjne i tak wyprzedzają mailing — outbox sortuje po
    | priorytecie, a korespondencja seryjna idzie z najniższym (`Low`).
    | Ostrożna wartość, bo shared hosting bywa wrażliwy na maile/godzinę.
    |
    | `cooldown_days` = karencja między wysyłkami do klientów. Liczona
    | KALENDARZOWO, nie co do minuty: wysyłka we wtorek o 20:00 pozwala na
    | kolejną od wtorku o 00:00 (czyli 6 pełnych dni przerwy). Sprzedawca nie
    | musi pilnować godziny — mailingów prawie nigdy nie wysyła się co do
    | minuty o tej samej porze.
    |
    | Wysyłki testowe (do własnego adresu sprzedawcy) NIE zużywają karencji ani
    | limitu — idą do jednego adresu, jego własnego, więc nie ma czego chronić.
    |
    */

    /*
    | `body_max` = limit długości treści (HTML z edytora Trix, jak opisy stron
    | i produktów). Ten sam limit widzi licznik znaków pod edytorem, walidacja
    | zapisu i redakcja przez AI.
    */

    'body_max' => (int) env('BULK_MAIL_BODY_MAX', 8000),

    'per_minute' => (int) env('BULK_MAIL_PER_MINUTE', 10),

    'cooldown_days' => (int) env('BULK_MAIL_COOLDOWN_DAYS', 7),

];
