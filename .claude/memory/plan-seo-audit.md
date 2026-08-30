---
name: plan-seo-audit
description: "Audyt ursalogic (16.07.2026, docs/ursalogic-kramio.pdf): SEO 63/D, Development 55/E, Accessibility 100/A, WCAG 88/B. Co zweryfikowane w kodzie, co fałszywe, kolejność napraw. START: rzeczy oczywiste."
metadata: 
  node_type: memory
  type: project
  originSessionId: 37f72f3b-963d-48a4-ba3e-06ca036f5d60
  modified: 2026-07-31T18:57:37.802Z
---

**Źródło:** `docs/ursalogic-kramio.pdf` (25 stron, dodane przez Rafała 2026-07-27). Audyt z **16.07.2026** na `ilikemybike.kramio.pl`, próbka 100 podstron. Tekst wyciągalny: `pdftotext -layout` (poppler JEST na serwerze; `pdftoppm` NIE MA, więc Read na PDF nie zadziała).

**Oceny:** SEO **63,17/100 (D)** · Development **55/100 (E)** · Accessibility **100/100 (A)** · WCAG **88/100 (B)**. 100% podstron poniżej 70 pkt w SEO i w bezpieczeństwie.

## KLUCZOWA OBSERWACJA
To audyt SKLEPU sprzedawcy, ale niemal każde znalezisko jest **PLATFORMOWE** — siedzi w naszych szablonach, nie w danych `ilikemybike`. Jedna naprawa podnosi wynik KAŻDEMU sklepowi na Kramio. To zmienia charakter pracy z „poprawka sklepu" na „funkcja platformy" i daje argument sprzedażowy: *SEO i dostępność w standardzie, sprzedawca nic nie musi*.

## Zweryfikowane w kodzie 2026-07-27 — PRAWDA, nasze do naprawy
- **Brak `meta description` na 100% stron** — `<head>` layoutu storefrontu ma WYŁĄCZNIE `<title>`.
- **Brak `canonical` na 100% stron.**
- **Brak Open Graph** (raport tego nie mierzy, ale wynika z tego samego braku) — link do sklepu wklejony na Facebooka/Messengera pokaże się bez opisu i obrazka.
- **Brak schematu produktu** — mamy `BreadcrumbList` (JSON-LD w `components/storefront/breadcrumbs.blade.php`), ale ZERO `Product`/`Offer` (cena, dostępność) → brak rich snippets.
- **Brak linku „Przejdź do treści"** (skip link) w layoucie.
- **Bezpieczeństwo 55/100 IDENTYCZNIE na wszystkich stronach** — sprawdzone `curl -I` na produkcji: **nie ma ŻADNEGO** z nagłówków CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy. To wyjaśnia „systemowość" wyniku: audyt nie znalazł dziury w aplikacji, tylko brak nagłówków. **Jedna klasa middleware podnosi całą platformę z oceny E.**

## Po stronie SPRZEDAWCY, nie naszej
- **56% stron to „cienkie treści" (<300 słów)**, średnia 286 słów/stronę — to opisy produktów pisane przez sprzedawcę. Rafał: użyć do tego NASZEGO AI. Mamy zadanie `product_copy` w `config/ai.php` ([[ai-task-profiles-architecture]]) — pomagamy pisać dłuższy opis zamiast besztać za krótki. To funkcja produktowa, nie naprawa buga.

## NIE BRAĆ NA WIARĘ (sprawdzone, wygląda na fałszywy alarm)
- **„Puste linki na 100% stron"** — koszyk ma `aria-label` (`cart-counter`), linki menu i stopki mają tekst, logo ma `alt`. Podejrzenie: narzędzie liczy jako „pusty" każdy `<a>`, którego jedynym dzieckiem jest `<img>`. Zweryfikować na żywej stronie PRZED przeróbkami.
- **„Obrazy bez alt na 8 stronach", „przyciski bez tekstu na 8 stronach", „pola bez etykiet na 2 stronach"** — drobne i punktowe; 8 na 100 to podejrzanie równo, pewnie jeden powtarzalny element. Najpierw ustalić KTÓRE strony.
- **LCP 2996 ms** (próg 2500) — problem realny, ale przyczyny raport nie podaje. ZMIERZYĆ przed „optymalizacją"; pierwszy podejrzany: zdjęcia produktów bez `width`/`height` i bez `loading="lazy"`. CLS = 0 (dobrze), czas odpowiedzi serwera mediana 155 ms (dobrze), najwolniejsza podstrona 608 ms.

## Rzeczy, które audyt POCHWALIŁ (nie ruszać)
Struktura nagłówków H1–H3: 100/100. Linkowanie wewnętrzne: 84,9/100, śr. 11,4 linku/stronę. Indeksacja: wszystkie 100 stron zwraca 200, nic nie jest zablokowane. Dostępność ogólna: ocena A. Serwer LiteSpeed + HTTP/3.

## USTALENIA WYKONAWCZE (Rafał, 2026-07-27) — przed pisaniem kodu
**Meta description pisze AI, wynik ZAPISANY W BAZIE**, regenerowany tylko przy zmianie treści — nigdy w locie (Googlebot nie może palić tokenów ani czasu shared hosta). Dotyczy STRONY GŁÓWNEJ SKLEPU i PRODUKTÓW; strony informacyjne i wykaz — deterministycznie, bez AI. Dla WSZYSTKICH pakietów (SEO w standardzie = argument sprzedażowy, spójne z „własna analityka dla wszystkich"), ale w ramach dziennego limitu AI → [[plan-ai-usage-limits]].
Warunki wykonania:
- generowanie w KOLEJCE, nie przy zapisie (padnięty DeepSeek nie może blokować zapisu produktu);
- **ręczna edycja wygrywa na zawsze** — regeneracja pomija tekst napisany ręcznie;
- deterministyczny fallback zostaje na wypadek braku treści źródłowej lub awarii AI; próg wejścia AI ~120 znaków opisu (poniżej AI i tak wyprodukuje ogólnik);
- twardy limit ~155 znaków po pełnym słowie + czyszczenie (cudzysłowy, emoji, nowe linie);
- regenerować tylko gdy skrót tekstu źródłowego REALNIE się zmienił.

**Osobny box „SEO" w formularzu** (produkt i sklep) — nazwa wprost, bo (a) sprzedawca ma wiedzieć, że to pod Google, (b) box będzie rósł o kolejne pola. Zawiera: pole opisu z licznikiem znaków, zdanie o tym, że własny tekst zostaje na stałe, oraz **przycisk „Wygeneruj z AI"**. Przycisk jest ważny architektonicznie: skoro ręczna edycja blokuje automat, sprzedawca musi mieć jak POPROSIĆ o świeżą wersję. Wygenerowany tekst ląduje w polu, ale zapisuje się dopiero przy „Zapisz" — sprzedawca widzi, co dostaje.

**Grafika OG generowana per sklep** (1200×630, PNG): białe tło + logo z **sensownymi marginesami** (Rafał: „by nie wyszło, że logo jest na całe tło i wygląda obrzydliwie") + subtelny akcent w kolorze przewodnim sklepu. Brak logo → nazwa sklepu na tle koloru przewodniego (`brand` + auto-kontrast `brand_ink`), żeby wyglądało celowo, a nie awaryjnie. Zapis jako domyślna grafika sklepu; nazwa pliku ze skrótem treści, bo Facebook cache'uje po adresie. Regeneracja przy zmianie logo lub nazwy. **Czcionkę TTF wrzucamy do repo** (na serwerze są DejaVu/Liberation, ale poleganie na fontach shared hostingu to proszenie się o „napis zniknął po migracji"). Produkty: zdjęcie produktu bez przeróbek, a gdy go nie ma — domyślna grafika sklepu. Możliwość wgrania własnej grafiki = przyszły dodatek pakietowy, TERAZ tylko przygotowujemy grunt.
Serwer sprawdzony 2026-07-27: GD 2.3.3 z FreeType + WebP + PNG + JPEG, Imagick też jest.

**`noindex`** na stronach transakcyjnych (koszyk, kasa, dziękujemy, moje konto, płatność) — zatwierdzone.

## STAN WYKONANIA — TEMAT ZAMKNIĘTY 2026-07-28
Wszystkie cztery punkty „oczywiste" wdrożone i sprawdzone NA PRODUKCJI (947 testów). Commity: `9194d89` (nagłówki + meta/canonical/OG), `1dbc3b8` (dostępność + grafika OG), `769acd5` (box SEO), `59bc39b` (opisy od AI).
- ✅ Nagłówki bezpieczeństwa — `SecurityHeaders` + `config/security.php`. CSP nadal ŚWIADOMIE poza zakresem (inline `<style>`/`<script>` → wymaga nonce'ów; osobny temat).
- ✅ Meta description + canonical + Open Graph + `noindex` na 7 widokach transakcyjnych.
- ✅ Dostępność: skip link, `aria-label` na miniaturach galerii, `for`/`id` przy polu e-mail.
- ✅ Grafika OG 1200×630 (`OgImageGenerator` + job + `og:generate`), Figtree w repo. **Bez podpisu przy wariancie z logo** (decyzja Rafała po obejrzeniu renderu).
- ✅ Box „SEO" (produkt + sklep): własny opis, licznik znaków ostrzegający (nie blokujący), podpowiedź pokazująca opis automatyczny, przycisk „Wygeneruj z AI". Reguła własności: ręczny tekst nietykalny, wyczyszczenie pola oddaje kontrolę automatowi.
- ✅ Opisy od AI: zadanie `seo_description`, `SeoDescriptionWriter` + job w kolejce, próg 120 znaków źródła, sprzątanie i limit długości w KODZIE, generowanie tylko po zmianie treści.

**Priorytet opisu — USTALONY z Rafałem 2026-07-28 (commit `2ee6cef`):**
- SKLEP: opis SEO (ręczny/AI) → opis sklepu (155 zn.) → nazwa + miasto.
- PRODUKT: opis SEO produktu → opis produktu → **nazwa i cena + opis sklepu** → nazwa i cena + „Kup online w sklepie X".
  Hybryda w przedostatnim kroku jest CELOWA: sam opis sklepu byłby identyczny na wszystkich produktach bez opisu, a zduplikowany meta opis Google traktuje jak jego brak. Nazwa z ceną gwarantuje unikalność strony. Test `test_two_products_without_descriptions_still_differ` tego pilnuje.
- STRONA TEKSTOWA: opis SEO (tylko ręczny, BEZ AI) → treść strony (155 zn.) → tytuł + nazwa sklepu. Brak AI to decyzja Rafała: Regulamin i PP są długie i niewyszukiwane, generowanie = przepalanie tokenów. Brak przycisku jest TESTOWANY, nie tylko skomentowany. Na `pages` nie ma też znacznika `meta_description_manual` — bez automatu byłby martwą kolumną.

**LUKA DOMKNIĘTA 2026-07-31 (`af7a412`):** audyt dotyczył storefrontu, więc cała praca 27–28.07 objęła SKLEPY — a sama **centrala nie miała żadnych znaczników OG**. Wyszło dopiero, gdy Rafał zrobił grafikę i spytał, gdzie ją wgrać. Grafika (jego, nie generowana) leży w `public/images/` pod nazwą z losowym ciągiem, ścieżka w `config/seo.php`, czytana przez `Seo::platformImage()`; znaczniki na landingu (+ brakujący `canonical`) i w layoucie `layouts/public` (regulamin, PP). Test `PlatformOgTest` pilnuje, czy plik z configu istnieje i ma 1200×630.
**Wniosek na przyszłość:** naprawa opisana jako „platformowa" potrafi objąć wyłącznie storefronty — przy każdym takim temacie sprawdzać OSOBNO strony centrali (landing, logowanie, regulamin), bo audyt ich nie próbkował.

**NIE zrobione (świadomie, do rozważenia później):** dane strukturalne `Product`/`Offer` (rich snippets), LCP 2996 ms (najpierw ZMIERZYĆ), „cienkie treści" — to działka sprzedawcy, wspierana przez AI. Masowe uzupełnienie opisów istniejącym produktom — celowo NIE uruchomione, bo to wywołania AI, a limity nie istnieją ([[plan-ai-usage-limits]]); istniejące produkty korzystają z fallbacku do pierwszej edycji.

## KOLEJNOŚĆ NAPRAW (ustalona z Rafałem 2026-07-27)
Rafał: „od SEO będziemy mogli zacząć zmiany w tych małych poprawkach. **Wdrożymy to, co jest oczywiste**, nad resztą zastanowimy się przy pracy nad tym."
1. **Nagłówki bezpieczeństwa** (middleware) — największy skok oceny za najmniejszą pracę, jedno miejsce.
2. **`meta description` + `canonical` + Open Graph** — to, co widać w Google i przy wklejaniu linku w social media.
3. **Skip link + punktowe poprawki dostępności** — domykają WCAG do A.
4. **Schema `Product`/`Offer`** — rich snippets z ceną i dostępnością.
5. **LCP** — dopiero po zmierzeniu, nie na czuja.
6. **AI do opisów produktów** — osobny temat produktowy, nie naprawa.

Powiązane: [[priorities-launch-first]] (SEO wchodzi jako pierwsze po domknięciu drobiazgów), [[plan-storefront-theming]], [[shared-hosting-constraints]].
