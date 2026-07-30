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
    |
    | Bez NIP-u — świadomie. Klient w mailu o zamówieniu nie ma co z nim zrobić:
    | nazwa i adres mówią mu, KTO wysłał, a kontakt — JAK odpisać. NIP nadawcy nie
    | odpowiada na żadne pytanie, które klient w tej chwili ma, a mylił się w tym
    | samym mailu z `Order::company_nip`, czyli NIP-em KUPUJĄCEGO (ten zostaje —
    | klient chce zweryfikować własne dane do faktury). Dane identyfikacyjne firmy
    | mają być dostępne na SERWISIE, nie w każdym mailu. Gdy dojdą faktury, NIP
    | wróci tutaj razem z nimi.
    |
    */

    'name' => 'Red Paprika Rafał Kwaśniak',
    'address' => 'Okrzei 73, 42-582 Rogoźnik',
    // Oficjalny adres kontaktowy platformy: stopki maili + ekran „Mój pakiet".
    'email' => 'kontakt@kramio.pl',
    'phone' => '+48 668 196 229',

];
