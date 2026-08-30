---
name: plan-bulk-mail
description: "Korespondencja seryjna („Wiadomości do klientów", Pawilon) — MODUŁ KOMPLETNY 2026-07-29 (ce02487, 136d1fb, 8d69d82), nic nie zostało w v1: byt roboczy + próbki bez limitu, karencja kalendarzowa, wypis, edytor z AI, karta promowanego produktu, postęp wysyłki."
metadata:
  node_type: memory
  type: project
---

**MODUŁ WDROŻONY 2026-07-29.** Trzy commity: `ce02487` (wysyłka + wypis), `136d1fb` (karta produktu), `8d69d82` (postęp + stronicowanie). Nazwa w UI: **„Wiadomości do klientów"** (menu: „Wiadomości", ikona 📣) — NIE „newsletter", żeby landing, panel i stopka maila mówiły jednym słowem.

## Ustalenia Rafała, które ukształtowały moduł

- **Wiadomość to BYT ROBOCZY** (2026-07-29): piszesz treść → wysyłasz próbki na własny adres ile razy trzeba → dopiero potem jednorazowo do klientów. `sent_at` zamyka ją na stałe; wysłanej nie da się edytować ani skasować (klienci mają ją w skrzynkach). Próbki BEZ limitu i nie zużywają karencji.
- **Karencja KALENDARZOWA, nie 168 h**: wysyłka we wtorek 20:00 pozwala na kolejną od wtorku 00:00 (6 pełnych dni przerwy). Uzasadnienie Rafała: „nie zawsze mailingi wysyła się o minutę później". `startOfDay(ostatnia) + cooldown_days`.
- **Tempo 10 maili/min**, wartość w configu. Priorytet `Low`.
- **Tylko własni klienci z aktywną zgodą**, bez importu, zgoda per sklep.
- **Treść w tym samym edytorze co opisy produktów i stron** (Trix + „Popraw przez AI", zadanie `mailing_body`) — żądanie Rafała, bo spójność panelu jest ważniejsza niż prostota pola.
- **Nagłówek maila = powitanie po imieniu** („Cześć Rafale"), NIE powtórzony temat — ten widać już w skrzynce. Stąd `Vocative::headline()` (wariant bez przecinka).
- **Karta promowanego produktu** (2026-07-29): 1 produkt, zdjęcie na całą szerokość, POD treścią — wybory Rafała z trzech wariantów.

## Rozwiązania techniczne warte zapamiętania

**Tempo bez throttlera.** Nie pisaliśmy nowego mechanizmu: przy wysyłce nadajemy każdemu mailowi `scheduled_at` w paczkach po `per_minute`, a wypuszcza je istniejący cron outboxu. Priorytet `Low` + `orderByDesc(priority)` w `dueForSending()` sprawia, że maile transakcyjne ZAWSZE wyprzedzają mailing.

**Migawki zamiast odczytów.** Karta produktu (`email_messages.product_card`) i liczba odbiorców (`recipients_count`) zamrażają się przy wysyłce. Powód: mail jest statyczny, a cena to informacja handlowa — wiadomość sprzed miesiąca ma pokazywać cenę z dnia wysyłki. Ta sama zasada co przy pozycjach zamówienia.

**HTML z edytora osobnym polem.** `email_messages.body_html` zamiast wpychania HTML-u w `intro_lines` — maile systemowe dalej idą escapowanymi blokami, więc jedna wiadomość z edytora nie osłabia escapowania wszystkich pozostałych. Sanitizer na zapisie, `Prose` na wyjściu.

**Postęp wysyłki wymagał powiązania** `email_messages.bulk_mailing_id`. Licznik pokazuje numer wiadomości W TOKU (`delivered + 1`), nie liczbę wysłanych — „Wysyłam 0 z 1" czytało się jak awaria (uwaga Rafała). Kampanie sprzed powiązania czytają `recipients_count`, żeby nie pokazywać „Wysłano 0".

**Wypis:** podpisany, BEZTERMINOWY link (`URL::signedRoute`, bez wygaśnięcia — mail sprzed roku musi dać się odsubskrybować), stopka TYLKO w mailingu (`unsubscribe_url` puste w transakcyjnych), wypis natychmiast przy wejściu + przycisk cofnięcia na wypadek pomyłki lub skanera poczty. GOTCHA: dopisanie parametru do podpisanego URL-a unieważnia podpis — „przywróć" ma własny podpisany adres.

## Pliki
`config/bulk_mail.php` (per_minute, cooldown_days, body_max), `BulkMailService`, `BulkMailing`, `BulkMailingController` + `BulkMailingRequest`, `Livewire\Seller\BulkMailingSender`, `UnsubscribeController`, `seller/mailings/*`, `storefront/unsubscribe.blade.php`. Testy: `Mail/BulkMailSendingTest`, `Mail/BulkMailUnsubscribeTest`, `Seller/BulkMailingPanelTest` (37).

## MODUŁ ZAMKNIĘTY — nic nie zostało w zakresie v1

„Dość mocne narzędzie", które Rafał zapowiadał jako kolejny krok, okazało się **kartą promowanego produktu** — i ono jest zrobione (`136d1fb`). Rafał 2026-07-29: *„kodowo to nie było WOW, ale już jako wartość sprzedaży to jest WOW"*.

**Warta zapamiętania proporcja:** karta to migawka danych + tabela HTML w mailu, robota na godzinę. Sprzedażowo to różnica między „sprzedawca napisał, że ma nową książkę" a „klient widzi okładkę, cenę i przycisk". Przy planowaniu kolejnych funkcji szukać takich właśnie — tanich w kodzie, dużych w skutku.

Format treści celowo pozostaje zwykłym HTML-em bez struktury bloków, więc ewentualne rozbudowy (więcej produktów, sekcje, szablony) nie wymagają przebudowy.

Powiązane: [[next-marketing-consent]] (zgody + wypis), [[plan-packages]], [[email-outbox-cron-pattern]], [[handoff-2026-07-29]].
