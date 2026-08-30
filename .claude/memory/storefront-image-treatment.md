---
name: storefront-image-treatment
description: "Zdjęcia storefrontu — listing/miniatury kwadrat-cover, karta produktu naturalne proporcje bez wypełniania."
metadata: 
  node_type: memory
  type: project
  originSessionId: 8d909387-227f-40d8-a4eb-ba4bbbe8aaf8
---

Ustalone kadrowanie zdjęć w storefroncie (2026-07-03, **REWIZJA 2026-07-11**):

- **REWIZJA 2026-07-11 (Rafał, nadrzędna):** na **kartach editorial strony głównej NIE przycinamy** zdjęć do kwadratu — cover może obciąć ważne elementy. Karta editorial (z aplą hover) niesie zdjęcie w **naturalnych proporcjach** (`w-full h-auto`), apla wysuwa się nad dolną częścią. Kwadrat-cover zostaje ewentualnie tylko tam, gdzie równa siatka jest krytyczna (koszyk/mini-listy) — ale domyślnie: naturalne proporcje. Patrz [[plan-storefront-editorial-and-pages]] #4.
- **Miniatury na karcie produktu:** stały **kwadrat** (aspect 1/1) z `object-cover`.
- **Główne zdjęcie na karcie produktu:** pokazujemy **takie, jak dodał sprzedawca** — naturalne proporcje (`w-full` + `h-auto`, `object` nie ruszamy), **bez przycinania i bez wypełniania**.

**Why:** próbowaliśmy „ambient blur" (rozmyte tło z tej samej fotki wypełniające puste pole przy stałej ramce) — Rafałowi się nie podobało („obrazek na obrazku", tło wyglądało tylko na jaśniejsze). Porzucone. Prosto i uczciwie > efekciarsko.

**How to apply:** przy koszyku, koncie, zamówieniach itd. trzymaj ten sam podział — miniatury/listy = kwadrat cover, prezentacja pojedynczego produktu = naturalne proporcje. Nie wracaj do wypełniania/rozmywania tła. Komponent `framed-image` (blur) został usunięty — nie wskrzeszać.

Powiązane: [[ui-design-direction]], [[storefront-theme-system]].
