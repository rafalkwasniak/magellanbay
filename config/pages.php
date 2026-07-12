<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maksymalna długość treści strony
    |--------------------------------------------------------------------------
    |
    | Górny limit HTML z edytora (z formatowaniem). Strony tekstowe bywają dłuższe
    | niż opis produktu (regulaminy, polityki), stąd hojniejszy limit.
    |
    */

    'content_max' => 30000,

    /*
    |--------------------------------------------------------------------------
    | Maksymalna liczba pozycji w kolumnie „Informacje" w STOPCE
    |--------------------------------------------------------------------------
    |
    | Górny limit linków w kolumnie „Informacje" w stopce storefrontu, łącznie
    | z zawsze doklejaną „Polityką prywatności". Chroni stopkę przed rozjechaniem,
    | gdy sprzedawca opublikuje dużo stron. Nadmiarowe strony sprzedawcy są
    | obcinane; menu w NAGŁÓWKU pokazuje pełną listę (limit dotyczy tylko stopki).
    |
    */

    'footer_menu_max' => 5,

    /*
    |--------------------------------------------------------------------------
    | Wirtualna strona „O sklepie"
    |--------------------------------------------------------------------------
    |
    | „O sklepie" NIE jest wierszem w tabeli `pages` — jej treść pochodzi z
    | `shop.description`. Strona istnieje (renderuje) zawsze, gdy opis jest
    | niepusty. `menu_threshold` to długość CZYSTEGO tekstu opisu (bez HTML),
    | od której „O sklepie" dostaje własną pozycję w menu „Informacje" i wycinek
    | + „czytaj więcej" na stronie głównej. Poniżej progu: pełny opis pokazujemy
    | na głównej, bez osobnej pozycji w menu (ale adres nadal działa).
    |
    */

    'about' => [
        'slug' => 'o-sklepie',
        'title' => 'O sklepie',
        'menu_threshold' => 400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Strona systemowa: Regulamin
    |--------------------------------------------------------------------------
    |
    | Nieusuwalna strona zakładana automatycznie przy tworzeniu każdego sklepu
    | (ShopObserver). Sprzedawca może ją tylko edytować i przestawić w kolejności
    | — nie da się jej wyłączyć ani skasować. `content` to szkielet do wypełnienia
    | (HTML z edytora Trix); sprzedawca uzupełnia treść pod swój sklep.
    |
    */

    'regulamin' => [
        'title' => 'Regulamin',
        'slug' => 'regulamin',
        'content' => '<div>Regulamin naszego sklepu jest właśnie w przygotowaniu. '
            .'Pracujemy nad tym, aby udostępnić Ci go w pełnej i przejrzystej formie — '
            .'tak, byś dokładnie wiedział, na jakich zasadach dokonujesz zakupów. '
            .'Dziękujemy za cierpliwość i zaufanie.<br><br></div>'
            .'<div>Regulamin określa zasady relacji zakupowej między Tobą a sklepem: '
            .'sposób składania i realizacji zamówień, formy płatności i dostawy, '
            .'a także Twoje prawa jako kupującego — w tym prawo do odstąpienia od umowy '
            .'oraz tryb składania reklamacji. Zależy nam, aby każdy zakup był dla Ciebie '
            .'jasny, wygodny i bezpieczny.</div>',
    ],

];
