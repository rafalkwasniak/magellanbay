---
name: naming-and-locale-convention
description: "Adres i interfejs po polsku, NAZWY w kodzie po angielsku, PROZA (komentarze/commity/dokumentacja) po polsku; sklep pl-first (APP_LOCALE=pl)."
metadata: 
  node_type: memory
  type: project
  originSessionId: eb5e9cc9-94cf-4010-b386-8ca993d18870
---

Ustalone 2026-06-25. Reguła: **„Adres i interfejs — po polsku. Kod — po angielsku."**

- **Po polsku (widoczne):** segmenty URL i slugi (`/produkt/132-kwiatki-komunijne`, `/koszyk`, `/sprzedawca/...`, `/administrator/...`), teksty UI. URL produktu: styl PrestaShop `id-slug` — lookup po `id`, zły slug → 301 na kanoniczny.
- **Po angielsku (kod):** modele (`Product`), tabele (`products`), kolumny, wartości enumów (rola `seller`), **nazwy tras** (`products.show`, `seller.dashboard`), kontrolery, zmienne. URL jest odpięty od nazwy trasy: `Route::get('/produkt/{product}', …)->name('products.show')`.
- W testach/linkach odwołuj się do tras przez `route('nazwa')`, nie sztywne ścieżki.

**Trzecia warstwa — PROZA — dopisana 2026-07-15 (`dfc4d90`), bo reguła miała lukę:** komentarze, docblocki, commity (subject i ciało), PR-y i dokumentacja idą **po polsku**. Angielskie zostają wyłącznie NAZWY. Inwentaryzacja z tego dnia: nazwy 100% EN (zero wyjątków), proza 113/120 plików `app/` po polsku, commity po polsku nieprzerwanie od 2026-07-10. `FOUNDATION.md` sek. 1 mówiła „komentarze i commity po angielsku" i była nieprawdą — przegłosowana przez własną sek. 3 i przez repo; poprawiona. Wyjątki z końca czerwca (wtedy konwencją faktycznie było EN) przetłumaczone.

Uwaga przy sprzątaniu: stockowe docblocki Laravela („Get the attributes that should be cast" nad `casts()`) **kasuj, nie tłumacz** — przepisują sygnaturę, czego `FOUNDATION` sek. 1 zabrania; tłumaczenie zamienia angielski szum na polski. Adnotacje typów (`@return`) zostają — to kontrakt, nie proza. Trzech stockowych migracji Laravela (`0001_01_01_*`) nie ruszamy.

**Locale:** pl-first, jednojęzyczny. `APP_LOCALE=pl`, `APP_FAKER_LOCALE=pl_PL`, `APP_FALLBACK_LOCALE=en` tylko jako techniczna siatka. Polskie tłumaczenia frameworka leżą w `lang/pl/` (validation, auth, passwords, pagination) — dokładać kolejne klucze po polsku, gdy się pojawią.

**Why:** sklep jest polski; mieszanka „polskie produkty + angielskie /products/seller" to misz-masz, który najtaniej naprawić na starcie. Rozdzielenie warstw daje spójność tam, gdzie widać, i konwencję frameworka tam, gdzie się programuje.

**How to apply:** każdą nową trasę/model/widok rób wg tej reguły. Już zastosowane: panel sprzedawcy `/sprzedawca/...` (nazwa trasy `seller.dashboard`). Powiązane: [[frontend-stack-decision]], [[ui-design-direction]].
