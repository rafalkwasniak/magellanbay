---
name: plan-platform-mailing
description: "Wiadomości Kramio do SPRZEDAWCÓW — MODUŁ KOMPLETNY 2026-08-10 (commit bfa23e7). Osobna tabela platform_mailings, wybór odbiorców checkboxami z szukajką, bez karencji i bez produktu. Domyka [[seller-marketing-consent]]."
metadata: 
  node_type: memory
  type: project
  originSessionId: 44c6a637-7dbd-4da5-ba49-0f65951d2764
  modified: 2026-08-10T19:50:39.348Z
---

**WDROŻONE 2026-08-10, commit `bfa23e7`, 1521 testów.** Rafał: *„super wyszło"*. Dział „Wiadomości" (📣) w panelu admina, `/administrator/wiadomosci`.

To domknięcie zgód zbieranych od 30.07 ([[seller-marketing-consent]]) — od tego dnia było do kogo pisać, ale nie było czym.

## Zamówienie Rafała (dosłownie)
„Mamy wzór fajny u Sprzedawcy, tutaj trzeba zrobić 1:1, ale bez dodawania produktu. Tylko tutaj trzeba by dać możliwość zaznaczenia, do kogo wysłać… zaznacz wszystkich, odznacz wszystkich, do tego wyszukanie po nazwie. No i tutaj żadnych ograniczeń czasowych, wysyłam ile chcę."

## Decyzje
- **Osobna tabela `platform_mailings`, osobny model i serwis** — NIE rozbudowa `bulk_mailings`. Tamten ma `shop_id` NOT NULL, bramkę pakietu, karencję i promowany produkt w każdym miejscu; dorabianie wyjątków przenosiłoby ryzyko na moduł, który już działa u klientów. Zgodne z linią z [[plan-przelewy24-payments]]: osobne ścieżki zamiast wspólnej abstrakcji.
- **Brak karencji** (`config/platform_mail.php` świadomie NIE ma `cooldown_days`), ale **jednorazowość ZOSTAJE** — Rafał wybrał to z dwóch wariantów. Uzasadnienie: zapis historyczny ma pokazywać treść, którą naprawdę dostali adresaci.
- **Domyślnie zaznaczeni wszyscy.** `recipient_ids = null` znaczy „nie wybierano" i serwis czyta z tego całą pulę; `[]` znaczy „świadomie nikogo". Ta różnica jest w kolumnie, nie w kodzie czytającym.
- **Wybór zapisuje się natychmiast**, bez „Zapisz" — inaczej ginąłby przy zapisie treści.
- **Nazwa sklepu w wierszu odbiorcy I w szukajce** (dołożone przez Rafała po odbiorze): admin kojarzy sprzedawcę po tym, co sprzedaje, szybciej niż po nazwisku. Sam display by nie wystarczył — chodziło o znajdowanie.

## Dwie reguły, które trzymają to w ryzach
1. **Zaznaczenie ZAWĘŻA pulę uprawnionych, nigdy jej nie poszerza.** Lista adresatów zawsze przechodzi przez `User::activeMarketingConsent()` — ten sam warunek, którym filtruje lista sprzedawców. Bez tego jedno kliknięcie w panelu omijałoby czyjś wypis. Osobne testy na zaznaczonego bez zgody i na wypisanego.
2. **Przyciski „zaznacz/odznacz" działają na WYNIKI SZUKAJKI** i zmieniają wtedy napis na „znalezionych". Bez tego „Odznacz wszystkich" przy wpisanej frazie kasowałby wybór osób spoza wyników. Najłatwiejsza pułapka w module, pokryta testem.

## Link wypisu — dołożony, choć Rafał o niego nie prosił
Bez niego mailing handlowy jest nielegalny. Trasa `platform.unsubscribe` na centrali (`/wypisz-sie/{user}`), podpisana i BEZTERMINOWA, z przyciskiem „przywróć". Ekran mówi wprost, że **faktury i informacje o pakiecie idą dalej** — inaczej wypis czytałby się jak odcięcie wszystkiego od platformy.

## Pliki
`config/platform_mail.php`, `PlatformMailService`, `PlatformMailing` (+ factory), `Administrator\MailingController` + `PlatformMailingRequest`, `Livewire\Administrator\PlatformMailingSender` i `PlatformMailingRecipients`, `PlatformUnsubscribeController`, `administrator/mailings/*`, `platform/unsubscribe.blade.php`. Testy: `Administrator/PlatformMailingPanelTest` (17), `Mail/PlatformMailSendingTest` (12), `Mail/PlatformUnsubscribeTest` (6).

## Stan na produkcji (koniec sesji 10.08)
Migracje odpalone, **moduł sprawdzony end-to-end na żywo**: kampania zlecona 21:26, mail w skrzynce 21:27 — cron outboxu drenuje w minutę. Rafał testował wysyłką na `demo@kramio.pl` (konto sklepu `lemoniady`).

**Panel wyczyszczony na jego prośbę** — testowe wiadomości skasowane, w tym WYSŁANA. Panel na to nie pozwala (przycisk „Usuń" tylko przy szkicach); zrobione bezpośrednio w bazie, bo to był jego test do własnego konta. Zasada w kodzie bez zmian.

**Ze zgodą jest 1 sprzedawca z 3** — zgoda jest nieobowiązkowa i pozostali jej nie zaznaczyli. Licznik „1" to prawda, nie usterka. Pierwszy prawdziwy mailing pójdzie więc do jednej osoby.

**GOTCHA przy testowaniu:** link „wypisz się" w mailu jest prawdziwy. Kliknięcie zabiera zgodę `demo@` i licznik spada na 0, a wysyłka odmawia. Wraca przyciskiem „Przywróć wiadomości" albo z profilu konta.

Powiązane: [[plan-bulk-mail]] (wzorzec u sprzedawcy), [[seller-marketing-consent]], [[handoff-2026-08-10]], [[email-outbox-cron-pattern]].
