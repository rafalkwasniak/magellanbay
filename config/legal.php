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

];
