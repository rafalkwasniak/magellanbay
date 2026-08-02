<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zgoda na ciasteczka
    |--------------------------------------------------------------------------
    |
    | Decyzja użytkownika żyje WYŁĄCZNIE w ciasteczku — nie zapisujemy jej w
    | bazie. Ruch jest w większości anonimowy, więc wiersz na każdego gościa
    | byłby kosztem bez pożytku, a samo ciasteczko jest tu jednocześnie zapisem
    | decyzji i mechanizmem jej egzekwowania.
    |
    | WAŻNE, że `SESSION_DOMAIN` jest pusty: ciasteczka przypisują się wtedy do
    | konkretnego hosta, więc zgoda z `sklep-a.kramio.pl` NIE przenosi się na
    | `sklep-b.kramio.pl` ani na centralę. Tak musi być — na każdym storefroncie
    | administratorem danych jest inny sprzedawca. Gdyby ktoś kiedyś ustawił
    | `SESSION_DOMAIN=.kramio.pl`, zgody zaczęłyby wyciekać między sklepami.
    |
    */

    'consent' => [
        'name' => 'cookie_consent',

        // Zgoda na rok — po tym czasie pytamy ponownie.
        'accepted_days' => 365,

        // Odmowa na krócej: ktoś, kto zmieni zdanie, nie musi czyścić
        // przeglądarki, a jednocześnie nie pytamy go przy każdej wizycie.
        'declined_days' => 30,

        'granted' => 'granted',
        'declined' => 'declined',
    ],

];
