---
name: gate-order-edit-behind-paid
description: "USTALONE 2026-07-19: edycja zamówienia = TYLKO Pawilon (najwyższy tier), by uzasadnić 2× cenę względem Straganu."
metadata: 
  node_type: memory
  type: project
  originSessionId: 14ae4ae1-0db2-4b73-ba37-07538785c5e9
  modified: 2026-07-19T16:19:43.438Z
---

USTALONE 2026-07-19: **edycja zamówienia w panelu sprzedawcy** (ilość/cena/dodanie/usunięcie — wdrożona 2026-07-11, patrz [[handoff-2026-07-11]]) = **TYLKO Pawilon** (najwyższy tier). Ewolucja decyzji: 2026-07-15 „płatne pakiety" (Stragan+Pawilon) → 2026-07-19 zawężone do samego Pawilonu. Powód Rafała: Pawilon kosztuje 2× Stragana (150 vs 75/mc, [[pricing-packages]]) i potrzebuje wyraźnego „super ficzera" — edycja zamówienia jest jego trzecim wyróżnikiem obok kodów rabatowych i korespondencji seryjnej. Pełny kontekst w macierzy [[plan-packages]].

**Why:** monetyzacja przez wartościowe, opcjonalne funkcje; podstawa ma być użyteczna, ale zostawiać powód do upgrade'u. Kluczowe uzasadnienie (Rafał, 2026-07-19): w normalnym e-commerce edycji zamówienia NIE MA — standard to „zamówiłeś = masz; chcesz zmienić → anuluj i złóż od nowa". Praca na już przyjętym zamówieniu to net-new zdolność, nie odebrana higiena — dlatego legalnie premium (i akurat topowy Pawilon).

**How to apply:** gdy dojdzie do resolvera uprawnień per pakiet (patrz [[plan-packages]] — nadpisania per sklep, zejście = miękki zamek/ukrycie), przełącznik „Edytuj zamówienie" i akcje `OrderEditor` chować/blokować dla Kramu I Straganu — dostępne wyłącznie w Pawilonie. Potrzebny nowy klucz uprawnienia (np. `order_editing`) — dziś go nie ma w kanonicznej liście z [[plan-packages]]. Dźwignia na później, gdyby brak edycji w Straganie irytował: podstawowa poprawka w Straganie, zaawansowane zarządzanie w Pawilonie.
