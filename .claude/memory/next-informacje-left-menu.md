---
name: next-informacje-left-menu
description: "ZROBIONE 2026-07-14 (commit 8d4e9f5): storefrontowe „Informacje\" na układ 2-kolumnowy z lewym dynamicznym menu (skorupa information-shell) + landing /informacje; Polityka prywatności dołączona jako ostatnia pozycja pod /informacje/…; offset sticky menu naprawiony w obu skorupach."
metadata: 
  node_type: memory
  type: project
  originSessionId: 795c8903-0a94-4110-b413-01a0d5bc4774
---

**ZROBIONE 2026-07-14 (commit `8d4e9f5`).** Dział „Informacje" storefrontu przerobiony na 2 kolumny (lewe submenu + treść), wzorzec = [[plan-customer-accounts]] account-shell.

**Co powstało:**
- **Skorupa** `resources/views/components/storefront/information-shell.blade.php` (bliźniak `account-shell`): breadcrumbs → H1 → linia → lewe sticky submenu + `$slot`. Menu DYNAMICZNE z `Shop::informationMenu()`; aktywna pozycja po URL (`'/'.request()->path()` vs item url), podświetlenie `st-btn`. Gdy tylko 1 pozycja → submenu pomijane (treść pełną szerokością). Mobile: menu składa się nad treść.
- `about.blade.php`, `page.blade.php`, `privacy.blade.php` przełożone na skorupę.
- **Landing `/informacje`** → `PageController::index` → **302** na pierwszą pozycję menu (302, nie 301 — cel zależy od kolejności stron). Trasa `storefront.information`.
- **Nagłówek**: rozwijana lista `<details>` „Informacje" → **zwykły link** na `$infoMenu[0]['url']` (pierwsza podstrona). Nawigację między stronami przejmuje lewe menu.

**Polityka prywatności dołączona do działu (prośba Rafała w tej samej sesji):**
- `Shop::informationMenu()` dokleja Politykę ZAWSZE jako OSTATNIĄ pozycję. Nowy helper `Shop::privacyPath()`. Config `pages.privacy` (slug `polityka-prywatnosci`, title).
- Przeniesiona pod `/informacje/polityka-prywatnosci` (trasa `storefront.privacy` przed wildcardem `{page}`); stary `/polityka-prywatnosci` → **301**. `Checkout.php` privacyUrl → `$shop->privacyPath()`.
- Stopka: Politykę pobiera z końca menu (bez dublowania), limit `footer_menu_max` obcina tylko strony sprzedawcy przed nią.
- UWAGA: centralowy `route('legal.privacy')` (`/polityka-prywatnosci` na głównej domenie) BEZ zmian — osobna platformowa strona.

**Fix sticky (obie skorupy):** nagłówek-winieta jest `sticky top-0`, więc menu na `lg:top-24` (96px) wjeżdżało pod belkę (winieta z logo ~190px). Offset warunkowy inline: `filled($shop->logo_path) ? '13rem' : '8.5rem'` (jedyna zmienna wysokości = logo h-28 vs nazwa serif). Inline `top` (bez nowej klasy Tailwind → bez przebudowy CSS), działa tylko przy `lg:sticky`. Zastosowane w `information-shell` I `account-shell`.

481 testów zielonych. Powiązane: [[plan-storefront-editorial-and-pages]] (punkt #2 menu+stopka domknięty), [[plan-customer-accounts]].

**Zostało z mapy editorial ([[plan-storefront-editorial-and-pages]]):** wycinek „O sklepie" + „czytaj więcej" na stronie głównej (#3), dopieszczenie karty/siatki produktów (#4).
