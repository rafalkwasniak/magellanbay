---
name: handoff-2026-08-07-szablony-i-dokumenty
description: "Cztery nowe białe szablony storefrontu — osie chrome/tekstura/card_mix, wszystko dostrajane liczbami w configu; testy 1336 → 1339"
metadata: 
  node_type: memory
  type: project
  originSessionId: f3a08704-6120-421a-b155-84268c45b158
  modified: 2026-08-06T17:31:07.664Z
---

# Handoff 2026-08-07 (cz. 1) — nowe szablony i „odszarzenie" storefrontu

Sesja z Rafałem (iteracyjnie, na oko — kilka rund „za mocno/za słabo"). Testy 1336 → 1339. Commity `9b79462` + `14849bd`.

## Co powstało

**4 nowe szablony** w `config/themes.php` (rodzina „czysta biel + nasycony kolor", celowo inna niż stare kremowe): **Bursztynowy kram** (`kramio_light`, klimat panelu Kramio), **Wiśniowy sad** (`white_red`), **Błękitna porcelana** (`white_blue`), **Zimowy ogród** (`white_green`). Po 5 palet; wszystkie `brand` mają kontrast ≥ 4.5:1 z bielą (dlatego bursztyn to odcień 700, nie 500 jak w panelu — amber-500 na bieli jest nieczytelny).

**Trzy NOWE osie szablonu** (wzorzec jak `chrome`; resolvery w `Shop`: `templateChrome()`, `templateChromeTexture()`, `templateCardMix()` — każdy z bezpiecznym fallbackiem):
1. `chrome` — czym malowane pasek+stopka: `neutral` (stare szablony, bez zmian) / `brand_tint` (12%) / `brand` (pastel marki).
2. `chrome_texture` — faktura na chrome, czysty CSS z gradientów w kolorze tła (idzie za paletą, zero grafik): `awning` (markiza kramu) / `dots` (wisienki) / `pinpoint` (fajans) / `stripes` (skos 45°, celowo przeciwny do awning 135°).
3. `card_mix` — o ile % boxy (st-card) ciemniejsze od tła; domieszka z `ink`, więc odcień idzie za paletą. Globalny domyślny 4 (stare szablony), nowa rodzina 6.

## Liczby dostrajane na oko (Rafał rzuca liczbę → jedna linia)

- `themes.chrome_brand_mix` = **30** (droga: 100 → 50 → 30; „co najmniej 50% przezroczystości", potem „koło 70%").
- `card_mix` nowej rodziny = **6** (droga: 20 → 10 → 6).
- Tekstury: kolor wzoru = `color-mix(surface 55%, transparent)`; wymiary px w 3 miejscach.

**GOTCHA — te same wzory żyją w TRZECH miejscach i muszą być zsynchronizowane:** layout storefrontu (`components/layouts/storefront.blade.php`), podgląd kafli PHP i podgląd kafli JS (oba w `seller/appearance/edit.blade.php`). Zmieniasz wzór/procent → wszystkie trzy.

## Gotchy techniczne z sesji

- **Tekst na pastelu marki = `ink`, NIE `brand_ink`** — biały na 30-50% pastelu jest nieczytelny; linki w chrome idą na `ink` (`.st-chrome .st-brand`), a w rozwijanym menu mobilnym (tło z surface) wracają na `brand`.
- **JS: skrót `style.background` ZDEJMUJE `background-image`** — po przemalowaniu paska trzeba nałożyć fakturę na nowo (jest w handlerze palet).
- Blade-gotcha potwierdzona w praniu: layout storefrontu ma blok `@php`, więc nowe zmienne dopisywać DO NIEGO (inline `@php(...)` rozwaliłby widok).
- Test `templateCardMix` asertuje wartość **z configu**, nie na sztywno — dostrajanie liczb nie psuje suity.
- Wrażenie „szarego tła" na białych szablonach robiły boxy i pasek, nie tło — `--surface` renderował czystą biel (zweryfikowane curl-em na `lemoniady.kramio.pl`). `wkhtmltoimage` jest na serwerze, ale stary WebKit nie zna zmiennych CSS/`color-mix` — do oceny kolorów storefrontu bezużyteczny; lepiej policzyć hexy skryptem.

## Drobne z tej sesji

- Podgląd kafli na `/sprzedawca/wyglad` pokazuje realny sklep: nazwa na pasku, losowy produkt z nazwą i ceną (`Money::pln`); placeholder tylko gdy brak zdjęć.
- Koszyk: nagłówek boxu „Masz kod rabatowy?" (sr-only label został „Kod rabatowy" — testy bramki na nim polegają).
- Mały serif dostał powietrze: `.st-box-title` letter-spacing `-0.025em` → `0.01em`, kafle produktów bez `tracking-tight`. Duże h1 celowo zostały ciasne.

## Dokumenty prawne v2 — WDROŻONE na produkcję (06.08)

Nowa Polityka Prywatności i nowy Regulamin napisane od zera i opublikowane jako **wersja 2 w `legal_documents`** (middleware `EnsureConsentsAreCurrent` sam wymusza ponowną akceptację). Stare v1 to były generyczne szablony z błędami (fragment z ursalogic o „audytach stron", odesłanie do platformy ODR zamkniętej 20.07.2025, „Sprzedawca" = Red Paprika kolidujące z językiem produktu).

**Źródło treści = pliki `docs/prawne/*.html` w repo** — zmiana treści to edycja pliku + nowy wiersz w bazie (nigdy edycja istniejącego wiersza; historia zgód wiąże się z wersją).

Kluczowe decyzje Rafała:
- Sprzedawcy = firmy + **działalność nierejestrowana**; polityka = **jeden dokument, dwie części** (I: Kramio jako administrator; II: sklepy — administrator = sprzedawca, Kramio = procesor).
- Regulamin §17 = umowa powierzenia z art. 28 RODO; §4 = „SaaS, nie marketplace" (spójne z argumentacją dla Paynow).
- Pakiety w regulaminie BEZ cen (odesłanie do Cennika) — zmiana cen nie wymusza nowej wersji i ponownych akceptacji.
- `prywatnosc@kramio.pl` działa przez catch-all na skrzynkę Rafała.
- CZEKA: przegląd przez prawnika (Rafał organizuje) — poprawki pójdą jako v3. Zwrócić prawnikowi uwagę na transfer treści AI poza EOG (DeepSeek).

DeepSeek zapowiedział znaczną podwyżkę (sierpień 2026) — uznane za niegroźne: architektura zadań (`config/ai.php`) pozwala zmienić dostawcę dwiema liniami w `.env`, awaria API degraduje się do komunikatu „chwilowo niedostępna" bez wpływu na sklepy. Przy podwyżce rozważyć dostawcę z EOG (Mistral) — załatwi też wątek transferu z polityki.

**UWAGA: pliki `docs/prawne/` niezacommitowane** — wrzucić przy najbliższym CP.

Powiązane: [[storefront-theme-system]], [[blade-php-block-breaks-inline-php]], [[tailwind-classes-must-exist-in-build]], [[priorities-launch-first]], [[plan-package-payments]], [[ai-task-profiles-architecture]].
