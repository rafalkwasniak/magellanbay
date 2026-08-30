---
name: priorities-launch-first
description: "OBOWIĄZUJĄCA kolejność prac (Rafał, 2026-07-27): cel = wypchnąć działający produkt do ludzi. Najpierw drobne domknięcia, potem Zwroty B/C, korespondencja seryjna, płatności za pakiety. Panel admina i wygaśnięcie — później."
metadata: 
  node_type: memory
  type: project
  originSessionId: 37f72f3b-963d-48a4-ba3e-06ca036f5d60
  modified: 2026-08-15T17:16:39.329Z
---

**Cel nadrzędny (Rafał, 2026-07-27): „najważniejsze jest wypchnąć DZIAŁAJĄCY produkt do ludzi".** Wszystko inne ustawiamy względem tego. Przy planowaniu kolejnych sesji trzymać tę kolejność, chyba że Rafał powie inaczej.

## ✅ ZROBIONE 15.08: OBOWIĄZKI DSA I DOKUMENTY v3

Moduł zgłaszania treści bezprawnych (art. 16 i 17) wdrożony, regulamin i polityka opublikowane w wersji 3, kwalifikacja HOSTING przyjęta decyzją Rafała — [[handoff-2026-08-15]], [[legal-dsa-hosting-classification]]. Przy okazji zamknięta realna dziura w zakupie pakietu (brak wyraźnego żądania = zwrot 100% przy odstąpieniu).

**Kolejka niżej bez zmian** — nadal trzy rzeczy bez właściciela: Etap 2 backupu, przeprowadzka na nowy serwer, test produkcyjny InPost. Doszło jedno pytanie do prawnika (kwalifikacja hosting vs platforma) — nie blokuje kodu.

## ✅ ZROBIONE 12.08: BACKUP (Etap 1+3) — i co dalej

Kopie zapasowe działają, odtworzenie przećwiczone ([[open-no-file-backups]]). Wcześniej tego samego dnia: strona wolnej subdomeny ([[plan-unclaimed-subdomain-landing]]) pod post na Facebooka.

**Zostały trzy rzeczy bez właściciela w kolejce** — przy następnej sesji zapytać Rafała, którą bierzemy:
1. **Etap 2 backupu** (kopia poza serwerem) — świadomie czeka na przeprowadzkę ([[plan-dev-environment]]).
2. **Przeprowadzka na nowy serwer** — Rafał planuje, termin nieustalony.
3. **Test produkcyjny InPost** (~20 zł) — czeka na pierwszego sprzedawcę z własnym kontem.

## Historyczne — W TOKU 10.08: PANEL ADMINA (dziś KOMPLETNY, zero stubów)

**Sprzedawcy** (`8f58e3a`) i **Wiadomości do sprzedawców** (`bfa23e7`) wdrożone, 1521 testów — [[handoff-2026-08-10]], [[plan-platform-mailing]]. Rafał odebrał oba na froncie.

**Panel admina ma dziś pięć żywych działów: Pulpit, Sklepy, Sprzedawcy, Wiadomości. Zostają dwa stuby: Zamówienia, Pakiety, Ustawienia.**

Żaden z nich nie jest oczywistym następnym krokiem — **przy kolejnej sesji zapytać Rafała**, czy ciągniemy panel dalej, czy wracamy do rzeczy z kolejki niżej (np. płatności za pakiety, [[plan-package-payments]]). Zamówienia przekrojowe są głównie ciekawostką, dopóki sprzedawców jest kilku.

## ✅ ZROBIONE 09.08: KURIER INPOST ZAMKNIĘTY → NASTĘPNY: PANEL ADMINA

**Wysyłki są skończone w całości** — paczkomat i kurier pod adres, nadawanie, etykiety, odbiór kuriera, maile ([[plan-inpost-courier]]). Rafał przetestował i potwierdził. **Zaczynać od panelu administratora** (pozycja 2 w kolejce niżej): Sprzedawcy / Zamówienia / Pakiety / Ustawienia to nadal martwe stuby, a Rafał zaczyna realnie potrzebować m.in. narzędzia do wysyłki wiadomości do sprzedawców ([[seller-marketing-consent]]).

Jedyne, co z wysyłek zostaje, to **test produkcyjny — czeka na pierwszego sprzedawcę z własnym kontem InPost**, bo sklep `lemoniady` to demo i nigdy nie pójdzie na produkcję. To nie jest zadanie do zaplanowania, tylko rzecz do przypomnienia w odpowiednim momencie.

## Historyczne — USTALONE 08.08: NAJPIERW KURIER PRZEZ INPOST, POTEM PANEL ADMINA

Wysyłki wróciły na pierwsze miejsce **na jedną rzecz** i za zgodą Rafała („no to mamy plan, może jutro się z tym uporamy"). Sonda API udowodniła, że **InPost nadaje kurierem pod adres i przyjeżdża po paczki** — na koncie, które sprzedawca już ma ([[plan-inpost-courier]], [[handoff-2026-08-08-inpost-kurier]]). Kodu mało, szkielet z 07.08 metodo-agnostyczny; największa praca to ekran nadawania z wagą i wymiarami.

**Furgonetka WYPADA z kolejki na stałe** — broker przestał rozwiązywać jakikolwiek problem. Wszystkie wzmianki o niej niżej czytać jako nieaktualne.

**Po kurierze wracamy do panelu admina** (pozycja 2 poniżej) — bez zmian w uzasadnieniu.

## ✅ ZROBIONE 07.08: WYSYŁKI ZAMKNIĘTE → NASTĘPNY: PANEL ADMINA

**InPost ShipX wdrożony w całości** (sandbox, przejechany przez Rafała od A do Z — [[handoff-2026-08-07-inpost]]): nadawanie z karty zamówienia, etykieta PDF, data odbioru napędzająca ustawowe 14 dni, dwa maile do klienta. Do domknięcia tylko **finalny test produkcyjny za ~20 zł** (decyzja Rafała, kiedy). Furgonetka i zbiorcze etykiety świadomie odłożone — brak ruchu.

**Pozycja 1 kolejki poniżej jest już nieaktualna — zaczynać od pozycji 2 (panel admina).**

## ✅ ZROBIONE 04.08: USUWANIE SKLEPU

Zamknięte od 02.08: cookies (02.08), logo + mapa strony + Search Console (03.08), **usunięcie sklepu przez sprzedawcę i admina (04.08, commit `61ea516` — [[plan-shop-self-deletion]])**.

**KOLEJKA — bez zmian:**
1. **Wysyłki** — etykiety ShipX, Furgonetka, token geowidgetu per sklep ([[plan-shipping]]).
2. **Panel administratora** — Sprzedawcy / Zamówienia / Pakiety / Ustawienia to nadal martwe stuby ([[plan-admin-panel-and-landing]]); tam też narzędzie do wysyłki wiadomości do sprzedawców ([[seller-marketing-consent]]).

## STAN 2026-07-30 (koniec sesji): PANEL ADMINA — nadal aktualne, ale PO cookies (patrz wyżej)

Rafał na zamknięcie: *„wychodzi na to, że kolejna sesja to będzie zabawa z panelem Admina. Wszystko inne, co jest ważne dla Klientów i Sprzedawców, jest zrobione"*. **To przestawia kolejność:** punkt 6 (panel admina) wchodzi PRZED punktem 5 (etykiety ShipX / Furgonetka). Uzasadnienie wynika z celu nadrzędnego — kurier i paczkomat DZIAŁAJĄ, więc etykiety są usprawnieniem pracy sprzedawcy, a nie warunkiem wyjścia do ludzi; panel admina jest narzędziem Rafała, którego zaczyna realnie potrzebować (m.in. wysyłka wiadomości do sprzedawców, na którą zgody są już zbierane — [[seller-marketing-consent]]).

Zamknięte od czasu ustalenia kolejności: pkt 1, 2, 3, 4 oraz **7 (wygaśnięcie, 30.07)**. Z pkt 5 zostały same etykiety i token geowidgetu.

## Kolejność

1. **Drobne domknięcia — TERAZ, przed dużymi tematami.** Uzasadnienie Rafała: „mała praca, a temat z głowy" — im dłużej wiszą, tym drożej wracać do kontekstu.
2. **Zwroty, Fazy B i C** — pierwszy z DUŻYCH tematów ([[legal-consumer-returns-withdrawal]]). Faza A (obowiązek informacyjny) zrobiona 2026-07-25.
3. **Korespondencja seryjna** ([[plan-bulk-mail]]) — drugi duży, świadomie po zwrotach, bo „bardziej skomplikowana". Uwaga: link wypisu ([[next-marketing-consent]]) jest w punkcie 1, NIE tutaj.
4. **Płatności za pakiety + zakup z głównej** — po zwrotach i korespondencji. To jeden temat, nie dwa: strona główna jest „niemal zrobiona", brakuje jej WYŁĄCZNIE faktycznego zakupu pakietu, więc wdrażamy ją w całości razem z płatnością. **NOWY WYMÓG (2026-07-27): zmiana pakietu w trakcie okresu** — kto ma Stragan i po miesiącu chce Pawilon, płaci LOGICZNĄ DOPŁATĘ (proporcjonalną do pozostałego okresu), nie pełną cenę. Do zaprojektowania, gdy do tego siądziemy.
5. **Wysyłki: etykiety ShipX / automatyzacja** ([[plan-shipping]]) — ważne, ale świadomie później.
6. **Panel administratora** (Sprzedawcy / Zamówienia / Pakiety / Ustawienia to dziś martwe stuby, [[plan-admin-panel-and-landing]]) — Rafał: „mogę to zrobić, jak ludzie będą się już bawić sklepem". Narzędzie dla NIEGO, nie dla klienta, więc nie blokuje wyjścia do ludzi.
7. **Wygaśnięcie / degradacja pakietu** ([[plan-subscription-expiry]]) — „mamy 11 miesięcy, zanim ktokolwiek dojdzie do końca rocznej opłaty". Plan gotowy, czeka.

## Dlaczego brak billingu NIE blokuje wyjścia do ludzi
Sprzedaż pakietu da się dziś przeprowadzić RĘCZNIE: konsola sklepów w panelu admina ([[plan-packages]] Faza 2) ustawia pakiet, uprawnienia, cenę i datę. Pieniądze z ręki, faktura z ręki. Dlatego automatyczny billing może stać na 4. miejscu, mimo że jest ważny — pierwsi klienci są obsługiwalni bez niego.

## Lista „drobnych domknięć" (punkt 1) — ROZSTRZYGNIĘTA 2026-07-27
- ✅ **`Product::hasBeenOrdered()`** — ZROBIONE 2026-07-27 (commit poniżej). Było zaślepką `false`, więc KAŻDY produkt kasował się trwale, a `order_items.product_id` (`nullOnDelete`) tracił powiązanie z katalogiem → bestsellery i flaga zwrotów art. 38 przestawały rozpoznawać produkt. Na produkcji zero szkód (0 osieroconych pozycji przy 48 powiązanych). Doszła relacja `Product::orderItems()`, potrzebna Zwrotom Fazie B.
- ✅ **SEO + bezpieczeństwo + dostępność storefrontu — ZROBIONE 2026-07-28** (4 commity, szczegóły i to, czego świadomie NIE zrobiliśmy, w [[plan-seo-audit]]). Rafał po odbiorze: „SUPER DZIAŁA".
- ❌ **Token geowidgetu per-sklep** — ODRZUCONE jako osobny krok (Rafał 2026-07-27): „póki sprzedawca nie zintegruje konta InPost, nie ma ryzyka; jak nie musi, to nic nie zrobi — on ma sprzedawać". Pole na token ma sens DOPIERO przy pełnej integracji InPost po stronie sprzedawcy → wraca razem z punktem 5 (wysyłki).
- ❌ **Rebuild assetów** — NIEPOTRZEBNY (sprawdzone 2026-07-27): build z 26.07 23:13 zawiera klasy widoków admina; zostały dwa `style=` z jednorazowymi wymiarami spoza skali Tailwinda, to nie obejścia.
- ➡️ **Link wypisu z mailingu** — PRZENIESIONY do punktu 3 (korespondencja seryjna). Pilne było ZBIERANIE zgód (zrobione 2026-07-15); wypis ma sens dopiero, gdy wychodzi pierwszy mailing.

Powiązane: [[plan-packages]], [[pricing-packages]], [[plan-discount-codes]] (moduł zamknięty 2026-07-27), [[handoff-2026-07-25]].
