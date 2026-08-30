---
name: plan-discount-codes
description: "Kody rabatowe (Pawilon) — PANEL WDROŻONY 2026-07-25 (KR-A…KR-E, 821 testów); ZOSTAŁ FRONT: koszyk/kasa/zamówienie/faktura + rozbicie rabatu na stawki VAT."
metadata: 
  node_type: memory
  type: project
  originSessionId: 37f72f3b-963d-48a4-ba3e-06ca036f5d60
  modified: 2026-07-27T07:38:03.188Z
---

**Ustalenia i stan modułu kodów rabatowych. Panel GOTOWY 2026-07-25 (sesja z Michałem), front NIE ZACZĘTY.**

## Zakres ustalony przez Rafała (2026-07-25)
Kody działają **tylko na produkty, nie na wysyłkę**. Cztery wymagania: (1) kod na cały koszyk, % albo kwota, opcjonalny próg min. wartości produktów; (2) kod na jeden produkt, % albo kwota, ten sam próg; (3) jednorazowy / wielorazowy (bez limitu lub N razy) / okno czasowe od–do; (4) imienny albo ogólnodostępny. Rafał przyjął DWA moje dodatki jako część tej samej funkcji: **typ „darmowa wysyłka"** (świadomy wyjątek od reguły „tylko produkty" — nie tyka produktów, zeruje dostawę) i **kod jako rekompensata** wystawiany wprost z zamówienia. Cel nadrzędny: dodawanie kodów ma być **intuicyjne i proste**, nie silnik reguł.

## Decyzje projektowe (moje, zaakceptowane w biegu)
- **Rabat kwotowy większy niż koszyk obcina się do koszyka** (nie odmawia) — zamówienie nigdy nie schodzi poniżej zera ani nie zjada wysyłki.
- **Anulowane zamówienie ODDAJE użycie kodu** (licznik po `countedAsSale`) — kod jednorazowy znów działa.
- **Kod imienny nie działa dla gościa** — wymaga zalogowania na własne konto. Zamówienie bez konta dostaje jednorazowy kod OGÓLNY.
- **Stan kodu jest WYLICZANY, nigdy zapisywany** (Aktywny/Nieaktywny/Zaplanowany/Wygasł/Wyczerpany) — kolumna natychmiast rozjechałaby się z datami.
- **Brak tabeli wykorzystań** — użycia to policzone zamówienia (`withCount`), mniej synchronizacji.
- Data „ważny do" obejmuje CAŁY dzień (23:59:59). Limit użyć wybiera się TRYBEM (bez limitu / tylko raz / maksymalnie N), nie gołą liczbą. Przecinek działa jak kropka.
- Kod nie zmienia ceny katalogowej → **nie uruchamia Omnibusa** ([[omnibus-lowest-price-30d]], „cena to cena" zostaje nienaruszone).

## Co jest w kodzie (panel, 5 checkpointów)
- **KR-A** — `App\Enums\{DiscountType,DiscountScope,DiscountStatus}`, model `DiscountCode` (+ `discountOn()`, `meetsMinimum()`, `status()`, `usedCount()`, `isUsableBy()`, `randomCode()`), migracje `discount_codes` + `discount_code_id`/`discount_code`/`discount_amount` na `orders` (relacja + MIGAWKA, wzorzec z faktur), `DiscountCodeFactory`.
- **KR-B** — menu 🎟️ Kody rabatowe, `/sprzedawca/kody-rabatowe`, `Seller\DiscountCodeController`, lista + zachęta „Pawilon" bez uprawnienia.
- **KR-C** — `Livewire\Seller\DiscountCodeForm` + `App\Support\DiscountSummary` (kod opowiedziany po polsku, składany NA ŻYWO z niezapisanego modelu — to jest cała „intuicyjność" modułu). Bramka realna: `/nowy` bez uprawnienia = 403, cudzy kod = 404.
- **KR-D** — akcje wiersza: Kopiuj (inline JS, bez budowania paczki), Edytuj, Włącz/Wyłącz, Usuń. PRAWA KOLUMNA (życzenie Rafała 2026-07-25, wzorzec z Analityki): karta „Szukaj" (jedno pole: kod / nazwa produktu / imię, nazwisko, e-mail klienta) + karta „Widok" (Wszystkie/Aktywne/Nieaktywne, linki GET z ✓ jak selektor okresu). Lista stronicowana po 20; **paginacja i filtr stanu w PHP**, bo stan jest wyliczany (SQL nie zna „wyczerpanego") — jedno źródło logiki zamiast dublowania w zapytaniu.
- **KR-E** — karta „Kod dla klienta" w szczegółach zamówienia → ten sam formularz z prefillem (`?klient=ID` / `?jednorazowy=1`), zero nowej ścieżki zapisu.

Testy: `tests/Feature/Discount/DiscountCodeTest.php` + `tests/Feature/Seller/DiscountCode{List,Form,Actions,FromOrder}Test.php`. Suite 821 zielonych.

## FRONT — WDROŻONY 2026-07-27 (commit `2a93679`, 896 testów)
- **Silnik:** `App\Services\DiscountResolver` (kod ↔ konkretny koszyk, powód odmowy po polsku), `App\Support\DiscountResult`, `App\Support\DiscountAllocation` (podział rabatu na pozycje metodą największych reszt; przy równych resztach grosz idzie do PIERWSZEJ linii). `OrderTotals` liczy netto/VAT per pozycja PO rabacie i przycina rabat do wartości produktów.
- **Koszyk:** osobna karta „Kod rabatowy" nad podsumowaniem. W sesji WYŁĄCZNIE kod (`CartService::setDiscountCode`), nigdy kwota — przelicza się przy każdym renderze. Kod, który przestał działać, ZOSTAJE z powodem (może wrócić po dołożeniu produktu); opróżnienie koszyka go kasuje. Komunikat odmowy i potwierdzenia w tej samej neutralnej ramce (bez czerwieni).
- **Kasa/zamówienie:** ponowne sprawdzenie kodu na finalnych pozycjach w transakcji składania; odmowa PRZERYWA składanie (`CartNeedsReviewException`) zamiast cicho podnieść kwotę. „Darmowa wysyłka" zeruje `delivery_cost`.
- **Dokumenty:** komponent `components/storefront/order-totals.blade.php` (potwierdzenie + konto klienta + strona płatności), `OrderMailer::amountLines()` w 5 mailach (przy okazji naprawione: dostawa NIGDY nie była w mailu wyszczególniona), wiersz rabatu w panelu sprzedawcy, faktura z rabatem rozłożonym na pozycje + adnotacja (ujemna pozycja „Rabat" zafałszowałaby VAT przy dwóch stawkach).
- Testy: `tests/Feature/Discount/{DiscountEngine,DiscountOnDocuments}Test.php`, `tests/Feature/Storefront/{CartDiscount,CheckoutDiscount,OrderDiscountVisibility}Test.php`.

## BRAMA PAKIETU (`discount_codes`, Pawilon) — obie strony, 2026-07-27 (commit `14a4268`)
- **Panel** (od KR-B/KR-C): lista → zachęta zamiast narzędzia i kodów NIE ładujemy; `/nowy`, edycja, włącz/wyłącz, usuwanie → **403** przez `allowedShop()`; cudzy kod → 404; karta „Kod dla klienta" przy zamówieniu nie pojawia się.
- **Front** (domknięte 2026-07-27, wcześniej BRAK bramki — Rafał wyłapał pytaniem): bramka w `DiscountResolver`, bo to jedyna droga do naliczenia rabatu (kryje koszyk, kasę i składanie naraz). Karta „Kod rabatowy" znika z koszyka; kod wbity wcześniej do sesji nie przejdzie — zamówienie nie powstaje. Kupujący dostaje neutralne „Nie znamy takiego kodu" (stan pakietu sprzedawcy to nie jego sprawa). Kody zostają w bazie i wracają razem z pakietem. Testy: `tests/Feature/Discount/DiscountEntitlementGateTest.php`.
- **Świadomy wyjątek:** pozycja 🎟️ w menu zostaje dla WSZYSTKICH pakietów i prowadzi do ekranu z zachętą (wzorzec z pustych Integracji w Kramie) — ukryte menu nie sprzedaje. Rafał zatwierdził 2026-07-27.

## ZOSTAŁO
Nic w zakresie modułu. Później: wysyłka kodów mailem razem z korespondencją seryjną (generowanie partii kodów jednorazowych) i ewentualny zwrot rabatu przy zwrocie towaru (Fazy B/C [[legal-consumer-returns-withdrawal]]).

Później: wysyłka kodów mailem razem z korespondencją seryjną ([[plan-bulk-mail]], [[next-marketing-consent]]) — wtedy dochodzi generowanie partii kodów jednorazowych.

Powiązane: [[plan-packages]] (uprawnienie `discount_codes`, tylko Pawilon), [[gate-order-edit-behind-paid]], [[ui-design-direction]], [[tailwind-classes-must-exist-in-build]] (klasy `max-w-xs`, `pr-16`, `gap-x-4`, `tracking-widest` NIE istnieją w buildzie — podmieniane na obecne).
