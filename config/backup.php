<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Włącznik
    |--------------------------------------------------------------------------
    |
    | `false` całkowicie wyłącza kopie: komenda `backup:run` kończy się od razu,
    | a harmonogram jej nie odpala. Zostawione jako przełącznik na wypadek
    | przenosin serwera albo prac, przy których nocny zrzut tylko przeszkadza.
    |
    | UWAGA: wyłączenie jest CICHE z założenia — dobowy strażnik „ostatni backup
    | starszy niż 36 h" też wtedy milczy. Wyłączasz świadomie i na chwilę.
    |
    */

    'enabled' => env('BACKUP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Katalog docelowy
    |--------------------------------------------------------------------------
    |
    | Bezwzględna ścieżka POZA katalogiem domeny. Trzy powody, każdy osobno
    | wystarczający: zły deploy albo `git clean` nie zabiera kopii ze sobą;
    | katalog jest poza docrootem, więc archiwum (a w nim `.env` z kluczami
    | Paynow i hasłem do bazy) nie da się pobrać po HTTP; prawa 700 zasłaniają
    | je innym kontom na współdzielonym hoście.
    |
    | Celowo BEZ wartości domyślnej: ścieżka jest właściwością maszyny, nie
    | aplikacji. Brak `BACKUP_PATH` przy włączonych kopiach to błąd głośny
    | (komenda kończy się porażką), bo backup, który po cichu nie działa, jest
    | gorszy od żadnego — usypia czujność.
    |
    */

    'path' => env('BACKUP_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Retencja i harmonogram
    |--------------------------------------------------------------------------
    |
    | `retention_days` — po ilu dniach archiwum jest kasowane. 14 dni przy
    | dzisiejszych ~4,5 MB na dobę to ~65 MB; okno musi przeżyć urlop, bo część
    | szkód (skasowana kategoria, zepsuty import) widać dopiero po tygodniu.
    |
    | `daily_at` — godzina nocnego przebiegu, z dala od `subscriptions:check`
    | (06:10) i `shops:purge` (06:20).
    |
    */

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    'daily_at' => env('BACKUP_DAILY_AT', '03:00'),

    /*
    |--------------------------------------------------------------------------
    | Próg strażnika
    |--------------------------------------------------------------------------
    |
    | Po ilu godzinach bez UDANEJ kopii `backup:check` bije na alarm. 36 h, nie
    | 24: jeden spóźniony albo ręcznie przesunięty przebieg nie ma budzić
    | nikogo, dwie pominięte doby już tak.
    |
    */

    'stale_after_hours' => 36,

    /*
    |--------------------------------------------------------------------------
    | Binarka mysqldump
    |--------------------------------------------------------------------------
    |
    | Osobny klucz, bo na innym hostingu potrafi leżeć poza PATH-em (np.
    | /opt/alt/mysql/bin/mysqldump). Zmiana serwera = zmiana jednej linii.
    |
    */

    'mysqldump_binary' => env('BACKUP_MYSQLDUMP', 'mysqldump'),

];
