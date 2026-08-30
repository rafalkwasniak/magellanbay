---
name: plan-per-shop-custom-pricing
description: "Do zaprojektowania OD RAZU: per-sklep własna cena + własny zestaw funkcji (custom deal), zamrożona na stałe dla tego sklepu"
metadata: 
  node_type: memory
  type: project
  originSessionId: 2e8fa019-7e9e-4cb3-ab6b-a93b0b13411b
  modified: 2026-07-19T16:33:36.530Z
---

POMYSŁ 2026-07-19 (Rafał). Do uwzględnienia w architekturze egzekwowania [[plan-packages]] ZANIM zabetonujemy pakiety jako sztywne 3 — inaczej trzeba będzie przerabiać.

**Wymaganie:** móc ustawić DLA KONKRETNEGO SKLEPU indywidualną cenę i indywidualny zestaw uprawnień, niezależnie od 3 standardowych pakietów [[pricing-packages]]. Przykład: kogoś nie stać na Stragan 75 — dajemy mu 50 zł/mc z WYCINKIEM funkcji (nie wszystkie), i ta cena+zakres zostają dla niego NA STAŁE („grandfather per-shop"). Lepiej mieć 50 zamiast 0.

**Konsekwencja architektoniczna (ważne):** model uprawnień NIE może być „pakiet → funkcje" na sztywno. Musi być per-sklep zestaw flag/limitów, gdzie pakiet to tylko PRESET wypełniający te flagi, a admin może je nadpisać + wpisać własną cenę. To ta sama maszyneria co edytor uprawnień w panelu admina (dziś stub) z [[plan-admin-panel-and-landing]] — projektujemy raz, elastycznie: cena i uprawnienia jako dane sklepu, pakiet jako szablon.

**Odnowienie NIE kasuje ręcznych nadań (ustalone 2026-07-19):** snapshot uprawnień sklepu jest LEPKI — przedłużenie abonamentu zostawia `entitlements` nietknięte, nigdy nie zaciąga ich na nowo z configu pakietu. Inaczej ręcznie włączony moduł spoza pakietu (sedno tej funkcji) znikałby co rok. Cena natomiast domyślnie idzie za aktualnym cennikiem przy odnowieniu (polityka zamrażania ceny = otwarta). Pełny zapis rozdzielenia cena/uprawnienia w sekcji „Odnowienie" [[plan-packages]].
