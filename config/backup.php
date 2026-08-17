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
    | `daily_at` — LISTA godzin przebiegu (po przecinku w `.env`). Od 17.08 dwa
    | razy na dobę: 04:00 i 16:00. Cel jest jeden — skrócić okno utraty danych z
    | ~24 h do ~12 h w najgorszym przypadku.
    |
    | Retencji to NIE dotyczy: kasujemy po WIEKU pliku, nie po ich liczbie, więc
    | druga kopia dziennie wygasa tak samo po 14 dniach. Świadomie NIE nadpisujemy
    | kopii porannej popołudniową, choć trzymałoby to liczbę plików na 14. Dwa
    | powody: nieudane nadpisanie skasowałoby jedyną dzisiejszą dobrą kopię, a
    | szkoda zauważona po południu (zepsuty import) zabrałaby ze sobą czystą kopię
    | sprzed niej. Cena tej ostrożności to ~35 MB.
    |
    | Godziny z dala od pozostałych cronów: `subscriptions:check` (06:10) i
    | `shops:purge` (06:20) — dlatego 04:00, a nie 06:00.
    |
    */

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    'daily_at' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('BACKUP_DAILY_AT', '04:00,16:00'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Próg strażnika
    |--------------------------------------------------------------------------
    |
    | Po ilu godzinach bez UDANEJ kopii `backup:check` bije na alarm.
    |
    | Próg MUSI iść za częstotliwością, inaczej cicho przestaje pilnować. Przy
    | jednej kopii na dobę 36 h znaczyło „dwie pominięte doby". Od dwóch kopii
    | dziennie te same 36 h przepuściłyby już TRZY nieudane przebiegi, więc próg
    | schodzi do 24 h.
    |
    | Rachunek na strażniku o 09:00: wszystko działa → 5 h od kopii z 04:00;
    | jeden pominięty przebieg → 17 h (cisza, bo pojedyncza czkawka nie ma nikogo
    | budzić); dwa pod rząd → 29 h, czyli alarm.
    |
    */

    'stale_after_hours' => 24,

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
