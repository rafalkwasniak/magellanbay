<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Materiały marki tej instalacji
    |--------------------------------------------------------------------------
    |
    | Ścieżki (względem `public/`) do logotypu i znaku kwadratowego. Wyprowadzone
    | z widoków do configu z jednego konkretnego powodu: przy poprzednim
    | wdrożeniu nazwa pliku brzmiała `kramio-logo.png` i siedziała na sztywno
    | w SZEŚCIU miejscach naraz — panel, ekran logowania, landing i stopka maila.
    | Kolejny klient znaczyłby albo szóstą podmianę tekstu, albo wgranie jego
    | logotypu pod cudzą nazwą.
    |
    | Teraz zmiana marki to podmiana dwóch plików i dwóch linii tutaj.
    |
    | LOGO ma przezroczyste tło (panel i maile mają różne podkłady). ICON jest
    | kwadratowy i NIEPRZEZROCZYSTY — iOS zamienia przezroczystość na czarne tło,
    | więc ikona z alfą wygląda na ekranie startowym jak plama.
    |
    | Formaty pochodne (`public/favicon.ico`, `public/apple-touch-icon.png`) są
    | plikami o nazwach narzuconych przez przeglądarki i nie mają czego szukać
    | w configu — podmienia się je pod tymi samymi nazwami.
    |
    */

    'logo' => 'images/magellan-logo.png',

    'icon' => 'images/magellan-icon.png',

];
