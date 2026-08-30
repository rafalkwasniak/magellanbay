---
name: handoff-2026-08-09-koszyk
description: "Handoff 2026-08-09 (druga sesja) — sklep bez dostawy i płatności przestał pokazywać „Do koszyka\"; commit 8aeea14, 1465 testów."
metadata: 
  node_type: memory
  type: project
  originSessionId: 0be9566b-2dfc-4d59-9e93-da673b41b414
  modified: 2026-08-09T20:26:53.464Z
---

**Zrobione:** `Shop::acceptsOrders()` + zniknięcie przycisku koszyka w sklepie, w którym nie da się dokończyć zakupu. Komplet reguł i odrzuconych alternatyw: [[plan-catalog-mode-no-cart]]. Commit `8aeea14`, testy **1456 → 1465** (9 nowych w `OrderAvailabilityTest`).

**Jak zaczęło się zgłoszenie:** Rafał zauważył na `balisong.kramio.pl`, że da się dodać do koszyka mimo braku jakiejkolwiek wysyłki. Moja pierwsza diagnoza szła w stronę „niedokonfigurowany sklep = usterka" — Rafał przeciął to inną ramą: **to legalny sposób użycia** (oferówka, sprzedaż telefoniczna, rękodzieło z ilionem parametrów do uzgodnienia). Ta rama zmieniła całe rozwiązanie: nie „pogoń sprzedawcę", tylko „nie obiecuj zakupu, którego nie da się dokończyć".

**Wzorzec do zapamiętania:** zaproponowałem świadomy przełącznik trybu; Rafał wolał, by stan wynikał wprost z ustawień i był dynamiczny. Jego argument był prostszy niż mój i wystarczający — nie dokładać warstwy deklaracji tam, gdzie fakt (są metody / nie ma) sam wystarcza.

**Weryfikacja na żywo (produkcja):** `balisong` 0 przycisków, `lemoniady` 9, `ciuszki` 1 — dokładnie tam, gdzie da się kupić. Zmiana nie wymagała rebuildu frontu (same warunki Blade, zero nowych klas Tailwinda).

**Następne:** wg [[priorities-launch-first]] dalej **PANEL ADMINA**. Drobiazg wypadający z tej sesji: sygnał dla sprzedawcy „twój sklep nie przyjmuje zamówień i dlaczego" (opis w [[plan-catalog-mode-no-cart]]).
