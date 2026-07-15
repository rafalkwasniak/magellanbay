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
    | Długość zajawki w kafelku na stronie głównej
    |--------------------------------------------------------------------------
    |
    | Długość CZYSTEGO tekstu (bez HTML) w kafelku treści na głównej — tak samo
    | dla „O sklepie" i dla promowanych stron; kafelki mają być jednolite. Ta sama
    | liczba rządzi odnośnikiem „Czytaj więcej", bo to jedno pytanie: czy tekst
    | się nie zmieścił, czyli czy jest co doczytać. (Drugi powód do odnośnika —
    | link w treści zgubiony przez strip_tags — jest w App\Support\Excerpt.)
    |
    | UWAGA: to NIE jest to samo co `about.menu_threshold`, mimo równej wartości.
    | Tu chodzi o „ile pokazać w kafelku", tam o „czy zasługuje na pozycję w menu".
    | Dwa różne pytania — zmiana jednego nie ma ruszać drugiego.
    |
    */

    'excerpt_length' => 400,

    /*
    |--------------------------------------------------------------------------
    | Maksymalna liczba stron wyróżnionych na stronie głównej
    |--------------------------------------------------------------------------
    |
    | Ile stron sprzedawca może wyróżnić na głównej (`show_on_homepage`, jak przy
    | produktach — patrz `shop.homepage_promoted_limit`). Sufit 2 jest celowo niski
    | i domyka układ: 2 strony + ewentualne „O sklepie" = najwyżej 3 kafelki, więc
    | siatka nigdy się nie zawija (1 na szerokość / 2 obok siebie / 3 obok siebie).
    | Proza w czterech kolumnach robi się nieczytelna, a treść bez sufitu spycha
    | promowane produkty — czyli to, co sprzedaje — pod zgięcie ekranu.
    |
    | Limit liczy FLAGĘ, nie widoczność: strona wyróżniona, ale niepublikowana
    | zajmuje slot. Inaczej dałoby się obejść sufit, wyróżniając szkice i publikując
    | je później.
    |
    */

    'homepage_promoted_limit' => 2,

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
    | niepusty. `menu_threshold` to długość CZYSTEGO tekstu opisu (bez HTML), od
    | której „O sklepie" dostaje własną pozycję w menu „Informacje" — miękka
    | reguła „ta treść jest dość istotna, by zasłużyć na własny punkt menu".
    | Poniżej progu adres nadal działa, tylko nie ma pozycji w menu.
    |
    | Próg rządzi WYŁĄCZNIE menu. Prezentacją na stronie głównej rządzi
    | `excerpt_length` — „O sklepie" jest tam zwykłym kafelkiem, jak każda
    | promowana strona, i nie ma już rozgałęzienia „pełny opis vs wycinek".
    |
    */

    'about' => [
        'slug' => 'o-sklepie',
        'title' => 'O sklepie',
        'menu_threshold' => 400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Polityka prywatności (NASZA — Kramio)
    |--------------------------------------------------------------------------
    |
    | Treść należy do Kramio (administrator danych), ale renderujemy ją w motywie
    | sklepu i wpinamy jako OSTATNIĄ pozycję działu „Informacje" (menu + stopka).
    | Adres w rodzinie /informacje/{slug} — spójnie z resztą działu. Stary adres
    | /polityka-prywatnosci przekierowuje tu 301.
    |
    */

    'privacy' => [
        'slug' => 'polityka-prywatnosci',
        'title' => 'Polityka prywatności',
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
