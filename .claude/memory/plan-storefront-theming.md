---
name: plan-storefront-theming
description: "USTALONE 2026-07-02 — architektura motywów storefrontu: 3 osie (szablon/układ/adaptacja), bloki, limit promocji na głównej ≤6; v1 = picker w panelu."
metadata: 
  node_type: memory
  type: project
  originSessionId: 5dd13c1f-9d32-4910-9b9a-5587fda156a6
  modified: 2026-07-27T08:12:25.481Z
---

**Architektura motywów storefrontu ustalona z Rafałem 2026-07-02.** Rozwija [[storefront-theme-system]] (tylko storefront jest motywowany; panel = jeden brand systemu).

## Trzy niezależne osie (kluczowa decyzja)
Wygląd i sposób układania treści to RÓŻNE rzeczy — rozdzielone, żeby nie mnożyć pracy:
1. **Szablon (szablon)** = skóra + osobowość (kolory, typografia, styl układu). Sprzedawca wybiera z siatki. Definicja w KODZIE (`config/themes.php` + widoki/CSS), nie w DB — dodanie = robota dev, nie funkcja panelu. Laik WYBIERA gotowca, nie buduje.
2. **Układ / scenariusz** = jak ułożona jest treść (landing 1 produktu / katalog / …). Dobierany do POTRZEBY, inferowany + nadpisywalny. **v1: NIE budujemy** (jeden przepis głównej z adaptacją wystarcza).
3. **Adaptacja do ilości** = automatyczne zachowanie przy 0/1/kilku/wielu produktach. Sprzedawca nic nie robi.

## Kolory — model USTALONY
- **Uniwersalne tokeny na poziomie sklepu** (nie per szablon) → przełączenie szablonu ZACHOWUJE kolory.
- Każdy szablon wysyła **gotowe palety-presety** (startery). Sprzedawca klika gotowca, nie dłubie pojedynczych kolorów.
- Wybór nowego szablonu nakłada jego domyślną paletę → od razu ładnie, zero dalszych decyzji.
- **Wersja prosta v1**: kontrakt 4 tokenów `brand`/`brand_ink`/`surface`/`ink`; reszta odcieni WYLICZANA w CSS, nie wybierana. E-booki vs inne produkty — NIE rozróżniamy w v1 (za dużo decyzji naraz).

## Render (kręgosłup techniczny)
Tokeny → **zmienne CSS w :root wstrzykiwane serwerowo**. BEZ kompilacji Tailwinda per-najemca (poległaby na shared-hoście — [[shared-hosting-constraints]]). Zmiana koloru = zmiana wartości zmiennych; zmiana szablonu = inny zestaw widoków+CSS. Wszystkie szablony gadają tym samym kontraktem zmiennych → dodanie szablonu jest tanie. Storefront = wciąż zakomentowany `Route::domain` (render przyjdzie z tenancy).

## Bloki (jak spiąć osie bez eksplozji)
Storefront składany z **bloków** (hero, karta produktu, siatka, box „kup"…) = wspólny słownik. Szablon tylko MALUJE bloki; układ/scenariusz to PRZEPIS (który blok, w jakiej kolejności); każdy blok ma stan pusty/niski → adaptacja sama. „Zielony zakątek jako landing" i „…jako katalog" = ten sam CSS + inny przepis.

## Limit promocji na stronie głównej — DECYZJA + panel ZROBIONY (2026-07-02)
**Główna promuje maks 6 produktów, NIE cały katalog.** Bez limitu 42 produkty zamieniają główną w drugi listing — a główna ma być WOW. Adaptacja (render, TODO): 0 → „już wkrótce" ([[storefront-draft-preview]]); 1 → pełny landing (produkt bohaterem, nie samotny kafelek); 2–6 → witryna „polecane"; katalog żyje na wykazie.
- **Panel: ZROBIONE.** Wykorzystaliśmy istniejące `products.show_on_homepage` (bool, checkbox na formularzu) — NIE dodawaliśmy `is_featured`. Sufit `config('shop.homepage_promoted_limit')` = 6. Egzekwowanie w `ProductRequest::withValidator()` (blokuje PRZEKROCZENIE przy zapisie; edycja pomija sam produkt przez whereKeyNot). Formularz pokazuje „Zajęte X z 6 miejsc" (czerwone, gdy pełno) + błąd. Testy w ProductTest.
- **Render na głównej** (który z wyróżnionych i jak, adaptacja do liczby) → z storefrontem.

## Nazwy szablonów (PL widoczne / EN slug stały) — jak przy pakietach
| PL (zmienialne) | slug (stały) | ton |
|---|---|---|
| Aksamitna chmurka | `velvet_cloud` | jasny, biało-błękitny |
| Zielony zakątek | `green_nook` | eko, zieleń/brąz/len |
| Grafitowy wieczór | `graphite_dusk` | ciemny, elegancki |

## Stan wykonania (2026-07-02)
- **v1 połowa A (wybór w panelu) ZROBIONA i przetestowana** (192 testy): `config/themes.php` (3 szablony × 2 palety, kontrakt 4 tokenów, `default_template`); migracja `shops.template`+`shops.theme` (JSON, wykonana na prod DB); resolver `Shop::templateSlug/templateName/themePalette/themeTokens` z fallbackami (mirror wzorca `package`/`entitlements`, [[plan-packages]]); zakładka „Wygląd" (`seller.appearance.*`) = picker 3 szablonów + palety ze swatchami + żywy mini-podgląd (vanilla JS). Paleta trzymana per szablon (`palettes[<slug>]`) → każdy szablon pamięta swój wybór. Walidacja w `AppearanceRequest` (para szablon/paleta musi się zgadzać).
- **Połowa B (render) — RUSZONA 2026-07-02** (commit `2067ecf`): storefront żyje na `{shop}.{central_domain}`. Middleware `ResolveShop` (alias `tenant`, 404 gdy slug bez sklepu, dzieli Shop z widokami, usuwa `{shop}` z parametrów trasy); grupa `Route::domain` włączona w routes/web.php (centrala nietknięta — łapie tylko subdomeny); layout `x-layouts.storefront` wstrzykuje `themeTokens()` jako zmienne CSS `:root` (`--brand/--brand-ink/--surface/--ink`, klasy `.st-brand/.st-btn/.st-border`); minimalna główna (nazwa/logo/opis + placeholder „Zobacz produkty") + ekran `coming-soon`; bramka widoczności w `HomeController` (szkic → coming-soon dla gości, podgląd dla właściciela/admina via `isVisible()`/`isAdmin()`). Testy: `tests/Feature/Storefront/HomeTest.php`. Brak cache tras/configu na prod → działa na żywo. Zweryfikowane na `ilikemybike.kramio.pl`.
- **Warstwa PRZEGLĄDANIA — KOMPLETNA 2026-07-02** (commity `2067ecf`→`1c29eb6`):
  - Główna z adaptacją do liczby wyróżnionych (`show_on_homepage`, ≤6): 1=hero, 2=para, 3=tryptyk, 4–6=siatka; fallback do najnowszych aktywnych; link „Zobacz wszystkie" gdy jest więcej. Kafel `x-storefront.product-card` = wspólny klocek.
  - Strona produktu `/produkt/{id}-{slug}` (szukamy po id, zły slug → 301 kanoniczny; `Product::storefrontPath()`); scope do sklepu (obcy → 404), bramka szkic/nieaktywny (`Shop::canBePreviewedBy`); galeria z miniaturami, cena, Omnibus, opis, placeholder „Dodaj do koszyka".
  - Wykaz `/produkty`: aktywne, paginacja; własny omotywowany pager `storefront/pagination.blade.php` („Wyświetlono od X do Y z Z" + numery stron). **UWAGA — zniesione (2026-07-05):** paginacja per szablon (`per_page` 9/12, `Shop::productsPerPage()`) zastąpiona globalną adaptacyjną gęstością — patrz niżej „Adaptacja do skali".
  - Testy: HomeTest, HomeProductsTest, ProductPageTest, ProductListingTest. Zweryfikowane na ilikemybike.kramio.pl (skopiowano jego 4 produkty → 16, żeby testować paginację).
- **Wykaz — sortowanie + filtry + chmura tagów: ZROBIONE 2026-07-02** (commit `f46e35a`, 235 testów). Sortowanie: 4 opcje whitelist `?sortowanie=` (`najnowsze`/`cena-rosnaco`/`cena-malejaco`/`nazwa`, nieznane → najnowsze). Tagi: filtr **AND** `?tagi=a,b` (`whereHas` per slug; z URL bierzemy tylko realne slugi sklepu). Chmura **fasetowa** (commit `423c96a`): po wyborze pokazuje tylko tagi współwystępujące z aktualnym filtrem (martwe kombinacje 0-produktowe znikają), wybrane tagi na początku jako aktywne „×", kandydaci z liczbą współwystąpień liczoną po CAŁYM przefiltrowanym zbiorze — jedno zapytanie `product_tag⋈tags` z podzapytaniem `IN(filtered.id)` sprzed paginacji (nie ze strony), `COUNT(DISTINCT)`, bez N+1. „Wyczyść", pusty wynik z filtrem = własny komunikat. Powrót: kafel niesie `?powrot=<request()->getRequestUri()>` (zakodowany), `ProductController::safeBack()` przyjmuje TYLKO lokalną ścieżkę (`/`, nie `//`,`/\`,`http`) inaczej → `/`; 301 kanoniczny slugu zachowuje query. Wszystkie URL-e budowane BEZ `page` (reset do 1); `withQueryString()` niesie filtry przez pager. Kod: `ProductController` (SORTS, resolveSort/sortOptions/resolveTags/tagCloud/listUrl/safeBack), widoki `storefront/products.blade` + `product-card` (prop `back`) + `product.blade`.
- **Adaptacja do skali katalogu (3. oś motywów) — ZROBIONE 2026-07-05** (commit `65ad6eb`, 336 testów). Wykaz `/produkty` sam skaluje układ do liczby AKTYWNYCH produktów sklepu. **Kluczowy wgląd:** wielkość kafla robią KOLUMNY (3 = duże/wyraziste, 4 = gęstsze), wiersze sterują tylko długością strony (`per_page = columns × rows`). Reguła: najmniejszy układ z drabinki, przy którym katalog mieści się w `max_pages` (=3) podstronach — najpierw rosną wiersze przy 3 kol. (3→5), potem skok na 4 kol. (4→6). Tabela: ≤27→3×3(9), ≤36→3×4(12), ≤45→3×5(15), ≤48→4×4(16), ≤60→4×5(20), ≤72→4×6(24), >72→sufit 24. Drabinka+`max_pages` w `config themes.listing`; `Shop::listingDensity()` liczy aktywne CAŁEGO sklepu (nie przefiltrowany zbiór → filtr/sort NIE zmieniają układu, treść nie „pływa"); widok maluje `lg:grid-cols-3/-4` (klasy statyczne). Zniosło `per_page` per szablon (`productsPerPage()` usunięte). Progi/kolumny/wiersze dostrajalne w configu bez kodu.
- **Breadcrumbs — TODO potwierdzone (Rafał, 2026-07-02):** okruszki na CAŁYM storefroncie OPRÓCZ strony głównej (główna = korzeń, bez breadcrumbs). Kandydaci: wykaz (`Sklep / Produkty`), karta produktu (`Sklep / Produkty / {nazwa}`), a przy filtrze tagiem sensownie odzwierciedlić kontekst. Spójne z motywem (klasy `st-*`).
- **SPROSTOWANIE 2026-07-27 (sprawdzone w kodzie):** koszyk, kasa i konto klienta SĄ już umotywowane (klasy `st-card`/`st-border`/`st-brand` w `livewire/cart`, `livewire/checkout`, `storefront/account/*`) — powstały motywowane razem z tymi modułami. Zdanie „dalej TODO: koszyk → kasa → konto" było nieaktualne. Wygląd/„prawdziwe szablony" (font, rama, ornament) = finalny szlif z projektantem; architektura gotowa (per-slug widoki/CSS). Domyka [[plan-shop-edit-tabs]].

Powiązane: [[storefront-theme-system]], [[plan-shop-edit-tabs]], [[multitenant-subdomain-architecture]], [[incremental-checkpoints-per-element]].
