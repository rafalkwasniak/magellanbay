---
name: ""
metadata: 
  node_type: memory
  originSessionId: 5c46a5c1-a53d-4ce1-a244-eab104032fcb
---

Druga sesja 2026-07-11 — start dużego kroku **storefront** (front sklepu dla klientów). Duży progres. Commity: `b2cd846` (moduł stron + „O sklepie"), `4e34727` (self-host fonty), `a78ce47` (nagłówek+stopka). **443 testy zielone.** Kontekst: [[plan-storefront-editorial-and-pages]] (główny plan, zaktualizowany), [[handoff-2026-07-11]] (pierwsza sesja tego dnia — edycja zamówienia).

## Zrobione w tej sesji (kolejność planu #1 + #2)
1. **Moduł stron „Informacje" (CMS)** — tabela/model `pages`, Regulamin systemowy (ShopObserver, nieusuwalny), panel „Informacje" (drag&drop `page-order.js`, edytor Trix + AI), render `/informacje/{id}-slug` (301, bramka widoczności). Klasa `.st-prose` (dziedziczy kolor motywu).
2. **Wirtualna „O sklepie"** — `/informacje/o-sklepie` z `shop.description` (NIE tabela pages). Decyzja: **istnienie ≠ obecność w menu** (`Shop::hasAbout/aboutInMenu/aboutPath`, próg `config('pages.about.menu_threshold')`=200 czystego tekstu).
3. **Nagłówek-winieta + stopka** — winieta wyśrodkowana (duże logo `h-28`/serif, nav w `--brand` `text-base`, koszyk pojawia się dopiero z zawartością), tło odróżnione od strony (tint `--ink`). Stopka 3-kol.: dane firmowe + kontakt + Informacje (`Shop::informationMenu`) + **lokalna PP w motywie** (`/polityka-prywatnosci`, treść Kramio LegalDocument). Breadcrumbs: opcjonalny „← Powrót" doklejony w prawo. Serif na tytułach.
4. **Self-host fonty** — patrz [[vite-build-rayon-threads]]: usunięty `bunny()`, oba fonty w `public/fonts`, build offline.

## NASTĘPNY KROK: #3 strona główna
Wg [[plan-storefront-editorial-and-pages]] sekcja „KOLEJNOŚĆ BUDOWY" pkt 3: **przebudowa `storefront/home.blade.php`** — sekcje wg mapy (hero/brand, produkty ⭐, „O sklepie" jako **wycinek + „czytaj więcej →"** gdy długie, tagi, CTA), pełny serif display, editorial. Potem #4: karty produktów z **aplą hover** (najgrubszy temat) + kg/szt + omnibus.

## Uwagi techniczne (na start następnej sesji)
- **Build bywa flaky** (LVE): jak wisi/pada — ubij WSZYSTKIE node/vite/rolldown (pkill + `kill -9` po PID, `ps` = 0), potem JEDEN build na czystym slocie: `RAYON_NUM_THREADS=1 TOKIO_WORKER_THREADS=1 UV_THREADPOOL_SIZE=1 ./node_modules/.bin/vite build`. Nowe klasy Tailwinda wymagają udanego builda; sprawdzaj `grep` w `public/build/assets/app-*.css` czy klasa jest, zanim uznasz zmianę za live. `public/build` w gitignore (nie commitujemy).
- Zmiany Blade (bez nowych klas) działają live od razu (server-render), bez builda.
- Storefront jest **JS-light** (ładuje tylko app.css, NIE app.js) — rozwijane menu na natywnym `<details>`, zero JS.
