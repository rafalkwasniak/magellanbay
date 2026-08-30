---
name: plan-catalog-mode-no-cart
description: "WDROŻONE 09.08 — sklep bez dostawy i płatności nie pokazuje „Do koszyka\"; stan liczony na żywo z ustawień, BEZ przełącznika i BEZ komunikatu."
metadata: 
  node_type: memory
  type: project
  originSessionId: 0be9566b-2dfc-4d59-9e93-da673b41b414
  modified: 2026-08-09T20:26:31.162Z
---

`Shop::acceptsOrders()` = jest czym dostarczyć **i** czym zapłacić. Fałsz → znika przycisk „Do koszyka" (karta produktu + kafel wykazu) i `CartService::add()` odrzuca dodanie. Sklep zostaje wykazem oferty — sprzedaż np. telefoniczna wg opisu produktu.

**Decyzje Rafała (09.08), świadome i wprost odrzucone alternatywy:**
- **BEZ przełącznika „tryb katalogu".** Proponowałem świadomą fiszkę w ustawieniach — Rafał odrzucił. Stan ma wynikać wprost z ustawień i być dynamiczny: „jak sprzedawca za 3 dni ustawi wysyłkę i opłatę, przyciski się pojawią; wyłączy — znikną".
- **BEZ komunikatu dla klienta.** „Bez żadnych deklaracji, bez gadania" — nieobecność przycisku mówi wszystko. Nie dokładać „Sklep chwilowo nie przyjmuje zamówień".
- **BEZ flagi w bazie** — flaga mogłaby się rozjechać z ustawieniami.
- **Koszyk i kasa BEZ ZMIAN.** `/koszyk` dalej pokazuje zawartość, kasa ma własną ścianę („Sklep nie przyjmuje jeszcze zamówień"). Rafał wybrał to świadomie spośród trzech wariantów — nie „poprawiać" tego później jako niedoróbki.

**Gotchy:**
- Fabryka sklepu domyślnie NIE MA żadnej metody → `acceptsOrders()` false. Test, który cokolwiek wkłada do koszyka, musi użyć `Shop::factory()->sellable()`. Bez tego pada bez sensownego komunikatu („Twój koszyk jest pusty"). Tak wywróciło się 39 istniejących testów.
- Para dostawa+płatność zawsze się złoży: płatność przy odbiorze wymaga dostępnego odbioru osobistego, więc nie ma stanu „są obie listy, ale nie da się kupić".
- Strona główna storefrontu NIGDY nie miała koszyka (tylko „Pokaż produkt") — nie szukać tam guzika.

**Zostało (nie robione, osobny temat):** sygnał dla SPRZEDAWCY w panelu, że jego sklep nie przyjmuje zamówień i czego brakuje. Realny przypadek: `balisong` ma zaznaczony odbiór osobisty, ale bez kompletnego adresu sklepu — system go nie liczy, a sprzedawca jest przekonany, że dostawę ma. Patrz [[plan-admin-panel-and-landing]].

Powiązane: [[plan-shipping]], [[bank-transfer-payment-method]], [[shop-visibility-auto-publish]].
