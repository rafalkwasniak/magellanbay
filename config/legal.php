<?php

use App\Enums\LegalDocumentType;

return [

    /*
    |--------------------------------------------------------------------------
    | Dokumenty wymagające zgody
    |--------------------------------------------------------------------------
    |
    | Typy dokumentów prawnych, których aktualną wersję każdy użytkownik musi
    | zaakceptować — przy rejestracji oraz ponownie po zmianie (middleware
    | EnsureConsentsAreCurrent). Stała biznesowa: rozszerzenie listy = jeden
    | wpis tutaj, bez ruszania kodu.
    |
    */

    'required_types' => [
        LegalDocumentType::Terms,
        LegalDocumentType::Privacy,
    ],

    /*
    |--------------------------------------------------------------------------
    | Zgoda marketingowa klienta
    |--------------------------------------------------------------------------
    |
    | Treść i wersja zgody na korespondencję seryjną. NIE jest to dokument
    | prawny (`legal_documents`) — to jedno zdanie przy checkboksie, więc żyje
    | w configu, a nie w bazie.
    |
    | `version` zapisujemy przy każdej zgodzie (`customer_consents.version`).
    | Reguła: **zmieniasz `text` → podbij `version`**. Inaczej po roku nie da się
    | odtworzyć, na co dokładnie klient klikał, a stara zgoda cicho „obejmowałaby"
    | nową treść — czego nie obejmuje (RODO art. 7: zgoda jest na konkretny cel).
    |
    | Zgoda jest UPRZEDNIA i DOBROWOLNA (art. 10 uśude): checkbox niezaznaczony
    | domyślnie, osobny od akceptacji regulaminu. Zbieramy ją na aktywacji konta
    | (adres potwierdzony kliknięciem w link) i edytujemy w profilu klienta.
    | Kasa gościa zgody nie zbiera — mailing idzie tylko do zarejestrowanych.
    |
    */

    'marketing_consent' => [
        'version' => 'v1',
        'text' => 'Chcę otrzymywać e-maile o nowościach, promocjach i ofertach tego sklepu.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Zgoda marketingowa SPRZEDAWCY (informacje handlowe od Kramio)
    |--------------------------------------------------------------------------
    |
    | Osobna od zgody klienta sklepu: tu nadawcą jesteśmy MY, a odbiorcą
    | sprzedawca. Zbierana przy rejestracji (osobny checkbox, niezaznaczony
    | domyślnie) i edytowalna w profilu.
    |
    | Ta sama reguła co wyżej: **zmieniasz `text` → podbij `version`**, bo
    | zapisujemy wersję przy każdej zgodzie i po roku musi dać się odtworzyć,
    | na co dokładnie ktoś klikał.
    |
    | NIE dotyczy maili niezbędnych do wykonania umowy — faktura za pakiet,
    | wygaśnięcie abonamentu, awaria, zmiana regulaminu idą BEZ tej zgody
    | i nigdy nie wolno ich nią blokować.
    |
    */

    'seller_marketing_consent' => [
        'version' => 'v1',
        'text' => 'Chcę otrzymywać e-maile o nowościach, ofertach i kodach rabatowych Kramio.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Prawo odstąpienia od umowy (zwroty)
    |--------------------------------------------------------------------------
    |
    | Ustawa z 30 maja 2014 o prawach konsumenta (wdrożenie dyrektywy
    | 2011/83/UE): konsument kupujący na odległość może odstąpić od umowy w
    | 14 dni bez podania przyczyny, licząc od DORĘCZENIA towaru.
    |
    | Doręczenia nie znamy — kurier nam o nim nie mówi. Dlatego termin liczymy
    | od przejścia zamówienia w „Zrealizowane" (a gdy takiego zdarzenia nie ma,
    | od złożenia zamówienia) i dokładamy `delivery_buffer_days` jako zapas na
    | czas dostawy. Zapas działa na korzyść konsumenta — wolno dać więcej czasu
    | niż wymaga ustawa, nie wolno mniej.
    |
    | Zapas 6 DNI KALENDARZOWYCH odpowiada założeniu „sprzedawca nadaje w ciągu
    | 4 dni roboczych": cztery dni robocze przeciętego weekendem to sześć dni
    | kalendarzowych. Liczymy kalendarzowo, bo dni robocze wymagałyby kalendarza
    | świąt — złożoność bez zysku, skoro i tak zgadujemy datę doręczenia.
    |
    | UWAGA: to jest OSZACOWANIE, nie ustawowy termin. Gdy sprzedawca nie
    | oznaczy zamówienia jako „Zrealizowane", liczymy od jego złożenia i możemy
    | zamknąć formularz WCZEŚNIEJ, niż wygasa prawo klienta. Dlatego strona
    | zwrotu po terminie nie twierdzi, że prawo wygasło — kieruje do sprzedawcy.
    |
    | Wyjątki (art. 38) ustawia sprzedawca per produkt: `products.withdrawal_excluded`.
    |
    */

    'withdrawal' => [
        'days' => 14,
        'delivery_buffer_days' => 6,
    ],

];
