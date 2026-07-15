<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dane firmowe OPERATORA platformy (Kramio)
    |--------------------------------------------------------------------------
    |
    | NIE mylić z danymi firmowymi sklepu — te siedzą na `shops` (`company_name`,
    | `nip`, adres) i sprzedawca zarządza nimi sam w panelu. Tutaj są dane NASZE:
    | firmy, która wysyła maile platformy (aktywacja konta, reset hasła), gdzie
    | nadawcą jest Kramio, a nie żaden sklep.
    |
    | Stała biznesowa, nie sekret — dlatego w `config/`, a nie w `.env`
    | (FOUNDATION sek. 5). Zmienia się raz na kilka lat.
    |
    | Puste wartości są DOPUSZCZALNE: stopka maila składa się z tego, co jest,
    | i chowa w całości, gdy nie ma nic. Ta sama reguła obowiązuje sklep bez
    | uzupełnionych danych firmowych — patrz Shop::addressLine().
    |
    | Wartości trzymamy w postaci GOTOWEJ DO WYŚWIETLENIA (adres jako jedna linia,
    | telefon ze spacjami) — to plik pisany ręcznie przez człowieka, a nie wejście
    | z formularza, więc nie ma czego normalizować ani składać z osobnych kolumn.
    | NIP bez myślników, tak jak w kolumnie `shops.nip` — oba warianty zapisu są
    | poprawne, a spójność w obrębie jednego szablonu maila jest ważniejsza.
    |
    */

    'name' => 'Red Paprika Rafał Kwaśniak',
    'address' => 'Okrzei 73, 42-582 Rogoźnik',
    'nip' => '6252118589',
    'email' => 'rafal@kwasniak.org',
    'phone' => '+48 668 196 229',

];
