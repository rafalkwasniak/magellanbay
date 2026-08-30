---
name: safari-anchor-scrolls-overflow-hidden-parent
description: "overflow-hidden na kontenerze treści + kula tła wystająca poza kadr = Safari przy skoku do kotwicy przewija ten kontener, nagłówek znika na zawsze"
metadata: 
  node_type: memory
  type: project
  originSessionId: 61af5479-00f4-4400-bb7e-2369e73a8e9b
  modified: 2026-08-03T19:04:43.232Z
---

Nasz wzorzec tła („miękkie kształty marki") ma kule pozycjonowane z ujemnym
`-bottom-*` / `-right-*`. Jeśli `overflow-hidden` siedzi na kontenerze, w którym
jest też **treść**, ten kontener staje się technicznie przewijalny (zawartość
wystaje poza kadr) — mimo że palcem/myszką go nie przewiniesz.

Skutek zgłoszony 2026-08-03 (Safari): klik w `kramio.pl/#jak-to-dziala` →
przeglądarka przewija nie tylko okno, ale i ten wewnętrzny kontener do maksimum
(~128 px). Logo i menu wyjeżdżają poza kadr **i nie wracają** — scroll na górę
przewija okno, nie kontener, więc jego `scrollTop` zostaje na zawsze.

**Zasada: kadrowanie kul NIGDY na kontenerze z treścią.** Osobna warstwa:

```html
<div class="relative min-h-full">
    <div class="pointer-events-none absolute inset-0 overflow-hidden">…kule…</div>
    …treść…
```

Wygląd identyczny (`inset-0` pokrywa się z rodzicem), a rodzic przestaje być
kontenerem przewijania. Poprawione w `welcome.blade.php` oraz układach
`guest` / `panel` / `public` (commit 2ab7a64). Dotyczy też każdego
`scrollIntoView` w panelu, nie tylko kotwic na landingu.

Powiązane: [[ui-design-direction]], [[tailwind-classes-must-exist-in-build]]
(ta poprawka rebuildu nie wymagała — użyte klasy już były w buildzie).
