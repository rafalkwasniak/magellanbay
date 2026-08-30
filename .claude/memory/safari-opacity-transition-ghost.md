---
name: safari-opacity-transition-ghost
description: "Safari zostawia „ducha\" elementu, gdy animacja opacity startuje razem ze zmianą szerokości — nie łączyć tych dwóch."
metadata: 
  node_type: memory
  type: project
  originSessionId: 7dc5af80-8620-4c4f-94f3-9ada7e310b73
---

W Safari, gdy element **jednocześnie** animuje `opacity` (klasa `transition` w Tailwind **zawiera** opacity) i **zmienia szerokość**, przeglądarka wypycha go na osobną warstwę kompozycji i trzyma ją w **starym rozmiarze** — zostaje półprzezroczysty duch starego elementu. Chrome/Firefox przerysowują warstwę w locie, więc tam problem nie istnieje i łatwo go przeoczyć.

Ugryzło 2026-07-17 przy „Popraw przez AI" (`c34b86d`): `disabled:opacity-50` startowało w tej samej chwili, co podmiana etykiety na „Poprawiam…" (przycisk się zwężał); przy `justify-between` prawa krawędź stała, więc duch wystawał z lewej.

**Why:** wygląda jak losowy babol layoutu, a przyczyną jest kompozycja warstw — bez tej wiedzy szuka się w złym miejscu (marginesy, flex, z-index).

**How to apply:** przy elemencie, który zmienia rozmiar w trakcie zmiany stanu (podmiana etykiety, spinner, `disabled`), używać `transition-colors` zamiast `transition` — hover tła zostaje płynny, krycia nie animujemy, warstwa nie powstaje. Alternatywa: pinowanie szerokości (`minWidth = offsetWidth`) przed podmianą. Uwaga: `transition-colors` musi być w buildzie — patrz [[tailwind-classes-must-exist-in-build]].
