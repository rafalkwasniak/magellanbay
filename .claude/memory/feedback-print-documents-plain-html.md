---
name: feedback-print-documents-plain-html
description: "Dokumenty, które Rafał drukuje do PDF lub przekleja gdzie indziej, robić od razu jako PROSTY semantyczny HTML — bez kart, ramek i siatek. Ozdobny layout rozjeżdża się przy druku."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 85301e1d-9f9e-4502-a6f9-d1381d0aa86c
  modified: 2026-09-01T12:36:56.659Z
---

**Ustalone przez friction 2026-09-01 przy ofercie dla Magellan Bay.**

Zbudowałem ofertę w tej samej ozdobnej konwencji co dokumenty do czytania na ekranie: karty z ramkami, siatki CSS, `border-top` na blokach etapów. **Przy druku do PDF rozjechało się** — karta Etapu 2 była wyższa niż strona, tekst został ucięty w połowie zdania i powtórzony na następnej stronie.

Rafał zareagował ostro i słusznie: *„wywal mi te ramki, zrób normalne wypunktowanie, z którym sobie powinien parser poradzić. bo zaraz sie okaze, ze zrobienie dokumentu to bedzie 2h czekania"*.

## Zasada

Jeśli dokument ma być **drukowany, zapisany jako PDF albo przeklejony** do Worda / Dokumentów Google / edytora na stronie — pisać go od razu jako **zwykły semantyczny HTML**: `h1`, `h2`, `p`, `ul`, `li`, `strong`, `hr`. **Zero `<div>`, zero kart, zero `grid`.** Typografię (kroje, wielkości) można zostawić, bo nie psuje niczego; strukturę wizualną — nie.

Efekt na tym samym dokumencie: 15 KB → 8 KB, 47 linii CSS zamiast ~250, zero problemów z paginacją.

## GOTCHA techniczna, przez którą to pękło

`break-inside: avoid` na elemencie **wyższym niż strona** nie przenosi go w całości — silnik renderowania rozjeżdża wtedy tekst zamiast go przenieść. Zakaz łamania dawać wyłącznie na małe elementy (`li`, wiersz tabeli), nigdy na duży blok.

## Przy tekście płynącym problem znika sam

Zwykły tekst nie ma czego rozerwać — najwyżej rozłoży się inaczej, ale nigdy nie utnie zdania. Łamanie stron (`page-break-before`) jest wtedy miłym dodatkiem, a nie warunkiem poprawności dokumentu.

Powiązane: [[plan-magellan-bay-separate-project]].
