---
name: plan-nip-autofill-and-editor
description: NIP→dane firmy WDROŻONE (GUS REGON primary + Biała lista fallback); lekki edytor opisu (Trix) wciąż PENDING — następny element.
metadata: 
  node_type: memory
  type: project
  originSessionId: ad2d01af-7396-446a-bc53-3263cd9693a7
---

**1. Auto-fill danych firmowych z NIP — WDROŻONE (2026-06-28).** Dwa źródła, koordynator `App\Services\CompanyLookup::byNip()`:
- **GUS REGON BIR1.1 (primary, dokładne)** — `App\Services\GusRegonClient`. Daje PEŁNĄ nazwę firmy (w tym nazwę handlową JDG, np. „RED PAPRIKA RAFAŁ KWAŚNIAK") + adres ROZBITY na pola WRAZ z województwem. SOAP 1.2 + WS-Addressing przez czysty `Http` (nie SoapClient). Odpowiedź GUS jest w MTOM (multipart/related), a koperta SOAP bywa NIEparsowalna dla simplexml (przestrzenie WS-Addressing) → wartości elementów (`ZalogujResult`, `DaneSzukajPodmiotyResult`) wyciągamy BEZPOŚREDNIM regexem + `html_entity_decode` (DaneSzukajPodmiotyResult to zXML-owany string z `<root><dane>...`, który dopiero potem parsujemy simplexml-em). Flow: Zaloguj (klucz→sid) → DaneSzukajPodmioty (NIP) → Wyloguj; sid w nagłówku HTTP `sid`. **DWA bugi, które kosztowały czas:** (1) Content-Type MUSI iść drugim argumentem `Http::withBody($body,$ct)` — inaczej leci application/json → GUS 415; (2) simplexml na całej kopercie pada → stąd direct-regex. Cache per-NIP 24h. **Zweryfikowane LIVE na produkcji 2026-06-28** kluczem Rafała (pożyczony, testowy): NIP 6252118589 → „Red Paprika Rafał Kwaśniak" + Okrzei 73, 42-582 Rogoźnik, śląskie. **Klucz produkcyjny ZAŁATWIONY (2026-06-30):** Rafał dostał własny produkcyjny klucz BIR (z api.stat.gov.pl), wpięty w `.env` `GUS_REGON_KEY`, pożyczony testowy usunięty. Zweryfikowany live na nowym kluczu (NIP 7342867148 → pełne dane CD Projekt). Klucz: `services.gus.key` ← `GUS_REGON_KEY` (gdy pusty → GUS pomijany).
- **Biała lista MF (fallback, działa zawsze bez klucza)** — `App\Services\WhiteListClient`. Dla JDG zwraca imię+nazwisko (nie nazwę handlową), adres jako jeden string (parser best-effort), bez województwa. `services.mf.base_url` ← `MF_WHITELIST_URL`.
- Koordynator: próbuje GUS, gdy null/niedostępny → Biała lista. Zwraca `source` = gus|whitelist. Bez klucza GUS wszystko działa od razu (mniej dokładnie); po wgraniu klucza dane stają się dokładne — zero zmian w kontrolerze/froncie.
- Kontroler `Seller\CompanyLookupController` (`POST /sprzedawca/firma/z-nip`, `seller.company.lookup`, throttle:20,1), JS `resources/js/company-lookup.js` (przycisk `[data-nip-lookup]` aktywny przy 10 cyfrach, wypełnia pola po id w tym `province`).
- Live: login GUS testowy potwierdzony (zwraca SID); mapowanie pól pokryte testem z nagraną odpowiedzią MTOM (`GusRegonClientTest`). Stan klucza prod: Rafał ma klucz z zaprzyjaźnionej firmy, wkleja w `.env` `GUS_REGON_KEY=` (config nie jest cache'owany → działa od razu po wklejeniu).

**2. Edytor opisu (Trix) — ZROBIONE dla sklepu, NASTĘPNE: produkt.** Trix wdrożony dla opisu sklepu: własny `HtmlSanitizer` (wąska whitelista, h1→h2, links rel nofollow), custom polski toolbar z ikonami SVG, wklejanie jako czysty tekst, tryb HTML w AI (`improveHtml`). NIE użyto mews/purifier — własny sanitizer.

**ZROBIONE (potwierdzone 2026-06-29):** opis PRODUKTU ma ten sam bogaty edytor. Komponent `<x-rich-editor>` istnieje i jest używany w `seller/products/form.blade.php` (`name="description"`, `ai-field="product_description"`, limit z `config('shop.product_description_max')`), z sanityzacją przez HtmlSanitizer (test `ProductTest::test_product_description_html_is_sanitised`) i przyciskiem „Popraw przez AI". Moduł produktu domknięty: lista/formularz/zdjęcia (8 szt., siatka 4, WebP q82 z `config('shop.product_images')`)/tagi/edytor — scommitowane (208cc89, d6dd1ce, 4c45601). Po zapisie produktu (store/update) wracamy do edycji, nie na listę.

Powiązane: [[deepseek-ai-improve]], [[plan-shop-settings-storage]], [[frontend-stack-decision]].
