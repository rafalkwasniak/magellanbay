---
name: handoff-2026-07-15-promoted-pages
description: "Handoff 2026-07-15 (2. sesja): wyróżnianie stron tekstowych kafelkami na głównej sklepu — od pomysłu Rafała do wdrożenia; 542 testy, commit 7d53b89."
metadata: 
  node_type: memory
  type: project
  originSessionId: d597683e-ad06-4dba-a499-2ecb401092fa
---

Druga sesja 2026-07-15 (po [[handoff-2026-07-15-messenger]]). Commit `7d53b89`, 542 testy zielone, wdrożone i sprawdzone na `ilikemybike.kramio.pl`.

**Skąd pomysł:** rozmówczyni Rafała (księgarnia) powiedziała, że niemal tak ważne jak produkty jest dla niej pokazanie TREŚCI — kim jest, linki do wywiadów, spotkanie autorskie. Strony tekstowe żyły dotąd tylko w menu i stopce.

**Co jest:** kolumna `show_on_homepage` na `pages` (ta sama nazwa co na `products`), sufit 2 (`pages.homepage_promoted_limit`), kafelki treści pod ofertą na głównej. „O sklepie" to kafelek nr 1, nie wyjątek układu — różni się wyłącznie źródłem treści (`shop.description`). Układ: 1 na szerokość / 2 obok siebie / 3 obok siebie, koniec drabinki. `App\Support\Excerpt` trzyma zajawkę i decyzję o „Czytaj więcej" w jednym miejscu dla opisu sklepu i treści strony.

**Reguła kafelka (dwa stany, nie dwa warianty):** treść, która się MIEŚCI w `pages.excerpt_length`, jedzie w całości z formatowaniem i BEZ odnośnika (cel miałby to samo, a linki w treści są klikalne na miejscu). Treść UCIĘTA jedzie jako czysty wycinek + „Czytaj więcej".

**Świadome decyzje (nie ruszaj bez powodu):**
- Sufit 2, nie drabinka bez końca — 2 strony + „O sklepie" = najwyżej 3 kafelki, więc siatka nigdy się nie zawija.
- Limit liczy FLAGĘ, nie widoczność: szkic z wyróżnieniem zajmuje slot (inaczej dało się obejść sufit, publikując go później).
- Pustej strony NIE blokujemy przy zapisie — chronimy przed bałaganem, nie przed gustem. Kafelka i tak nie dostanie (`Page::hasContent`).
- Regulaminu (`is_system`) nie da się wyróżnić: bez checkboxa, bez wskazówki, bez przyjęcia pola w kontrolerze.
- `pages.excerpt_length` odcięte od `about.menu_threshold` — dwa różne pytania, dziś przypadkiem ta sama wartość (400). Nie sklejać z powrotem.

**Dwie lekcje z tej sesji (obie: pomyliłem się i złapała to dopiero rzeczywistość):**
1. Wariant „kafelek zawsze czystym tekstem" gubił formatowanie krótkich opisów — regresja na istniejących sklepach, złapana przez `AboutPageTest`. Stary kod miał rację: „jest gdzie zobaczyć formatowanie — nie ma dokąd wejść". Przy okazji rozpuściło to zbędne wykrywanie linków w `Excerpt`.
2. `sm:text-2xl` nie było w buildzie → nagłówki utknęły na 20 px. Patrz [[tailwind-classes-must-exist-in-build]].

**Przy okazji:** nazwy produktów w siatce głównej podniesione z `text-xl` do `text-2xl sm:text-3xl` — siatka była jedynym miejscem mówiącym półgłosem. Cała główna mówi teraz jednym stopniem.

**Hierarchia nagłówków — ZROBIONE w tej sesji (`93dd449`, 548 testów):** wszystkie `h3` w storefroncie na `h2`; przeskoki `h1 → h3` były na głównej (siatka produktów) i w stopce (ujawniało się na koszyku/kasie/stronach info, czyli tam, gdzie poza `h1` nie ma nic własnego). Kafel wykazu poprawiony z innego powodu — to nie był przeskok, tylko zła semantyka („produkty jako podsekcja Sortowania"). Pilnuje tego `HeadingHierarchyTest`. Zmiana czysto semantyczna: preflight Tailwinda resetuje `h1–h6` do `font-size: inherit`, więc poziom nagłówka jest u nas NIEWIDOCZNY, a stopień pisma niosą klasy — dlatego bez testu ten błąd nie ma jak się ujawnić. Test łapie przeskoki, NIE złą semantykę przy poprawnej arytmetyce.

**Spójność językowa repo — ZROBIONE w tej sesji (`dfc4d90`):** `FOUNDATION` sek. 1 kłamała („komentarze i commity po angielsku"). Inwentaryzacja pokazała, że repo jest spójne, tylko inaczej: nazwy 100% EN, proza ~98% PL. Poprawione + przetłumaczone 3 pliki z czerwca + `README` (stockowa ulotka Laravela!) zastąpiony prawdziwym. Szczegóły i zasady sprzątania w [[naming-and-locale-convention]].

**Maile — ZROBIONE w tej sesji** (`820c92a`, `45c2d3a`): stopka z danymi firmowymi nadawcy + logo 64px, potem usunięty NIP nadawcy. Szczegóły i świadome decyzje w [[mail-footer-company-data]].

**Typografia storefrontu — ZROBIONE w tej sesji** (`0b844a2`, `4692995`, `226aa00`): logo w stopce 36→64px (+ `max-w` 10→14rem, bo sam wzrost wysokości byłby pozorny dla logo poziomych); kafel wykazu przeniesiony na typografię reszty serwisu (był JEDYNYM miejscem z tytułem bezszeryfowym), cena straciła `st-brand` — jeden akcent na kafel; nazwa produktu w kaflach siatki ograniczona do 2 linii (`line-clamp-2`). Reguła limitu: **tam, gdzie nazwa jest etykietą w siatce rodzeństwa; brak, gdy tytuł jest treścią sam w sobie** — więc kafelek solo, boxy treści i „Zobacz produkty" BEZ limitu. Dwie linie, nie jedna: przy jednej („~22 znaki na 3 kolumnach) ucięcie zjadałoby końcówki wariantów („Shima OpenAir Brown" vs „…Black").

**Otwarte / dalej:**
- **Wołacz w mailach**: „Cześć Anna" → „Anno" (patrz [[open-mail-footer-contradiction]]). Rafał odkładał to trzy razy. UWAGA: to nie jest prosta podmiana — polska odmiana imion ma dużo wyjątków, a przy imionach obcych/nietypowych automat potrafi zrobić klientowi wstyd w mailu, którego nie da się cofnąć. Najpierw rozpoznanie i warianty (łącznie z wyjściem bez odmiany), potem kod.
- Ceny w rzędzie kafli nie stoją w równej linii (kafel z 1-linijkową nazwą ma cenę wyżej). Wysokości kafli się zrównują przez `mt-auto`, ceny nie. Zgłoszone Rafałowi, nietknięte — wyrównanie wymagałoby stałej wysokości bloku tytułu.
- Wykaz przy `lg:grid-cols-4` (duży katalog): tytuł 30px będzie się łamał na 2 linie. Zostawione do zobaczenia na realnym katalogu, bez wyprzedzającego warunku.
